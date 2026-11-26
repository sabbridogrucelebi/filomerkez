<?php

namespace App\Services;

use App\Exceptions\TelegramConnectionProblem;
use App\Exceptions\TelegramRateLimited;
use App\Models\Fleet\Vehicle;
use App\Models\TelegramAlertBot;
use App\Models\TelegramKaskoNotificationLog;
use App\Models\TelegramKaskoNotificationSetting;
use Carbon\Carbon;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class TelegramKaskoNotificationService
{
    public function sendScheduledNotifications(bool $scheduled = false): int
    {
        $sentCount = 0;
        $today = now(TelegramAlertSchedule::TIMEZONE)->startOfDay();

        TelegramKaskoNotificationSetting::query()
            ->where('is_enabled', true)
            ->get()
            ->each(function (TelegramKaskoNotificationSetting $setting) use ($today, $scheduled, &$sentCount) {
                $sentCount += TelegramScheduledCheck::run($setting, $scheduled,
                    fn (TelegramScheduledCheck $check) => $this->sendForSetting($setting, $today, $check));
            });

        return $sentCount;
    }

    private function sendForSetting(TelegramKaskoNotificationSetting $setting, Carbon $today, TelegramScheduledCheck $check): void
    {
        $days = TelegramAlertSchedule::days($setting->reminder_days ?? []);
        try {
            $this->sendUpcomingSummary($setting, $today, max($days ?: TelegramAlertSchedule::DEFAULT_DAYS), $check);
        } catch (Throwable $exception) {
            $check->failed($exception);
        }

        Vehicle::query()->with('kaskoDocuments')
            ->where('company_id', $setting->company_id)
            ->where('is_active', true)
            ->eachById(function (Vehicle $vehicle) use ($setting, $today, $days, $check) {
                if ($check->shouldPause()) {
                    return false;
                }
                $policy = TelegramPolicyAlerts::latestUploaded($vehicle, 'kaskoDocuments');
                $expiryDate = $policy?->end_date?->copy()->startOfDay();
                if (!$expiryDate) {
                    return;
                }
                $daysLeft = TelegramPolicyAlerts::daysUntil($today, $expiryDate);

                $threshold = TelegramAlertSchedule::policyThreshold($days, $daysLeft);
                if ($threshold === null || $this->wasAlreadySent($setting, $vehicle, $expiryDate, $threshold)) {
                    return;
                }

                try {
                    $messageId = $this->send($setting, $vehicle, $daysLeft, $expiryDate, $policy->start_date);

                    TelegramKaskoNotificationLog::create([
                        'company_id' => $setting->company_id,
                        'vehicle_id' => $vehicle->id,
                        'kasko_end_date' => $expiryDate->toDateString(),
                        'reminder_day' => $threshold,
                        'telegram_message_id' => $messageId,
                        'sent_at' => now(),
                    ]);

                    $check->sent++;
                } catch (Throwable $exception) {
                    $check->failed($exception);
                    Log::warning('Telegram kasko bildirimi gönderilemedi.', [
                        'company_id' => $setting->company_id,
                        'vehicle_id' => $vehicle->id,
                        'reminder_day' => $daysLeft,
                    ]);
                }
            });
    }

    public function sendTestForCompany(TelegramKaskoNotificationSetting $setting): ?string
    {
        $today = now(TelegramAlertSchedule::TIMEZONE)->startOfDay();
        $vehicle = Vehicle::query()->with('kaskoDocuments')
            ->where('company_id', $setting->company_id)->where('is_active', true)
            ->get()->map(function (Vehicle $vehicle) {
                $vehicle->setRelation('telegramKaskoPolicy', TelegramPolicyAlerts::latestUploaded($vehicle, 'kaskoDocuments'));

                return $vehicle;
            })->filter(fn (Vehicle $vehicle) => $vehicle->getRelation('telegramKaskoPolicy') !== null)
            ->sortBy(fn (Vehicle $vehicle) => abs(TelegramPolicyAlerts::daysUntil($today, $vehicle->getRelation('telegramKaskoPolicy')->end_date)))->first();

        if (!$vehicle) {
            $this->sendTextMessage($setting, "✅ <b>Filo Merkez bağlantı testi</b>\nMesaj bu gruba ulaştı. Yüklü kasko belgesi bulunan araç yok.");
            return null;
        }

        $policy = $vehicle->getRelation('telegramKaskoPolicy');
        $this->sendTest($setting, $vehicle, $policy->end_date, $policy->start_date);
        return $vehicle->plate;
    }

    public function sendTest(TelegramKaskoNotificationSetting $setting, Vehicle $vehicle, ?Carbon $expiryDate = null, ?Carbon $startDate = null): string
    {
        $expiryDate = ($expiryDate ?? $vehicle->kaskoExpiryDate())?->copy()->startOfDay();
        if (!$expiryDate) {
            throw new RuntimeException('Test için geçerli bir kasko poliçesi bulunamadı.');
        }
        $daysLeft = TelegramPolicyAlerts::daysUntil(now(TelegramAlertSchedule::TIMEZONE)->startOfDay(), $expiryDate);

        return $this->send($setting, $vehicle, $daysLeft, $expiryDate, $startDate);
    }

    public function renderMessage(TelegramKaskoNotificationSetting $setting, Vehicle $vehicle, int $daysLeft, ?Carbon $expiryDate = null, ?Carbon $startDate = null): string
    {
        $expiryDate ??= $vehicle->kaskoExpiryDate();
        if (!$expiryDate) {
            throw new RuntimeException('Kasko bitiş tarihi bulunamadı.');
        }
        $template = filled($setting->message_template)
            ? $setting->message_template
            : TelegramKaskoNotificationSetting::defaultMessageTemplate();

        // Eski, varsayılan şablonları da süresi geçmiş araçlar için doğru metne dönüştür.
        if ($daysLeft <= 0 && !str_contains($template, '{{kasko_status}}')) {
            $template = str_replace(
                "Aşağıdaki aracın kasko poliçesinin sona ermesine\n⚠️ <b>{{days_left}} GÜN</b> kaldı.",
                'Aşağıdaki aracın kasko poliçesi {{kasko_status}}',
                $template
            );
        }

        // Eski veya özel şablonlar bu alanları içermese bile, kasko uyarısında
        // ruhsat sahibi ve vergi/T.C. bilgisi her zaman gösterilsin.
        $template = TelegramPolicyAlerts::withOptionalStart($this->ensureOwnershipFields($template), $startDate?->format('d.m.Y'), '{{kasko_end_date}}');

        return strtr($template, [
            '{{company_name}}' => e($setting->company?->name ?? 'Filo Merkez'),
            '{{days_left}}' => (string) abs($daysLeft),
            '{{kasko_status}}' => $this->kaskoStatus($daysLeft),
            '{{plate}}' => e($vehicle->plate ?: '-'),
            '{{brand_model}}' => e(trim(($vehicle->brand ?? '') . ' ' . ($vehicle->model ?? '')) ?: '-'),
            '{{model_year}}' => e($vehicle->model_year ?: '-'),
            '{{license_serial_no}}' => e($vehicle->license_serial_no ?: '-'),
            '{{chassis_no}}' => e($vehicle->chassis_no ?: '-'),
            '{{license_owner}}' => e($vehicle->license_owner ?: '-'),
            '{{owner_tax_or_tc_no}}' => e($vehicle->owner_tax_or_tc_no ?: '-'),
            '{{kasko_end_date}}' => e($expiryDate->format('d.m.Y')),
        ]);
    }

    private function send(TelegramKaskoNotificationSetting $setting, Vehicle $vehicle, int $daysLeft, Carbon $expiryDate, ?Carbon $startDate): string
    {
        return $this->sendTextMessage($setting, $this->renderMessage($setting, $vehicle, $daysLeft, $expiryDate, $startDate));
    }

    public function sendTextMessage(TelegramKaskoNotificationSetting|TelegramAlertBot $setting, string $message, ?int $replyTo = null, ?int $thread = null): string
    {
        if (!$setting->hasCredentials()) {
            throw new RuntimeException('Telegram bot anahtarı ve sohbet kimliği tanımlanmalıdır.');
        }

        $token = $setting->bot_token;
        $wait = TelegramSendCooldown::remaining($token);
        if ($wait > 0) {
            throw new TelegramRateLimited($wait);
        }

        $response = $this->postMessage($token, $setting->chat_id, $message, $replyTo, $thread);

        if ($response->status() === 429 || (int) $response->json('error_code') === 429) {
            $retryAfter = (int) $response->json('parameters.retry_after');
            if ($retryAfter <= 0) {
                $retryAfter = (int) $response->header('Retry-After');
            }

            throw new TelegramRateLimited(TelegramSendCooldown::defer($token, $retryAfter > 0 ? $retryAfter : 60));
        }

        if (!$response->successful() || !$response->json('ok')) {
            throw new RuntimeException($response->json('description') ?: 'Telegram mesajı gönderilemedi.', (int) ($response->json('error_code') ?: $response->status()));
        }

        return (string) $response->json('result.message_id');
    }

    private function postMessage(string $token, string $chatId, string $message, ?int $replyTo, ?int $thread): Response
    {
        $request = Http::connectTimeout(5)->timeout(15)->withoutRedirecting()->acceptJson();
        $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
        $payload = ['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
        if ($thread !== null) {
            $payload['message_thread_id'] = $thread;
        }
        if ($replyTo !== null) {
            $payload['reply_parameters'] = ['message_id' => $replyTo, 'allow_sending_without_reply' => true];
        }

        try {
            return $request->post($url, $payload);
        } catch (ConnectionException $exception) {
            $context = $this->connectionContext($exception);
            if (!$this->failedBeforeSend($context)) {
                throw new TelegramConnectionProblem($context['errno'] ?? null);
            }
        }

        // An address-family fallback is safe only if the first attempt sent no request.
        try {
            return $request->withOptions(['force_ip_resolve' => 'v4'])->post($url, $payload);
        } catch (ConnectionException $exception) {
            $context = $this->connectionContext($exception);

            throw new TelegramConnectionProblem($context['errno'] ?? null, $this->failedBeforeSend($context));
        }
    }

    private function connectionContext(ConnectionException $exception): array
    {
        $cause = $exception->getPrevious();

        return ($cause instanceof ConnectException || $cause instanceof RequestException) ? $cause->getHandlerContext() : [];
    }

    private function failedBeforeSend(array $context): bool
    {
        $code = $context['errno'] ?? null;

        return in_array($code, [6, 7], true) || ($code === 28
            && isset($context['request_size'], $context['size_upload'], $context['pretransfer_time'])
            && $context['request_size'] === 0 && (float) $context['size_upload'] === 0.0
            && (float) $context['pretransfer_time'] === 0.0);
    }

    private function wasAlreadySent(TelegramKaskoNotificationSetting $setting, Vehicle $vehicle, Carbon $expiryDate, int $daysLeft): bool
    {
        return TelegramKaskoNotificationLog::query()
            ->where('company_id', $setting->company_id)
            ->where('vehicle_id', $vehicle->id)
            ->whereDate('kasko_end_date', $expiryDate)
            ->where('reminder_day', $daysLeft)
            ->exists();
    }

    private function sendUpcomingSummary(TelegramKaskoNotificationSetting $setting, Carbon $today, int $summaryDays, TelegramScheduledCheck $check): void
    {
        if (!$setting->upcoming_summary_enabled
            || $setting->last_upcoming_summary_date?->isSameDay($today)) {
            return;
        }

        $entries = TelegramPolicyAlerts::upcoming($setting->company_id, 'kasko', $today, $summaryDays);
        $messages = TelegramPolicyAlerts::summaryMessages("{$summaryDays} GÜN İÇİNDE BİTECEK KASKOLAR", $entries);
        $key = 'telegram:kasko:summary:' . $setting->id . ':' . $today->toDateString() . ':' . hash('sha256', $setting->chat_id . serialize($messages));
        $next = (int) Cache::get($key, 0);
        foreach (array_slice($messages, $next) as $message) {
            $this->sendTextMessage($setting, $message);
            Cache::put($key, ++$next, $today->copy()->addDay());
            $check->sent++;
        }

        $setting->last_upcoming_summary_date = $today;
        $setting->save();

        Cache::forget($key);
    }

    private function ensureOwnershipFields(string $template): string
    {
        $fields = [];
        if (!str_contains($template, '{{license_owner}}')) {
            $fields[] = '👤 <b>Ruhsat Sahibi:</b> {{license_owner}}';
        }
        if (!str_contains($template, '{{owner_tax_or_tc_no}}')) {
            $fields[] = '🏢 <b>Vergi / T.C. No:</b> {{owner_tax_or_tc_no}}';
        }

        if ($fields === []) {
            return $template;
        }

        $footerOffset = strpos($template, "\n<i>");
        if ($footerOffset !== false) {
            return substr_replace($template, "\n" . implode("\n", $fields), $footerOffset, 0);
        }

        return rtrim($template) . "\n\n" . implode("\n", $fields);
    }

    private function kaskoStatus(int $daysLeft): string
    {
        if ($daysLeft > 0) {
            return 'sona ermesine ⚠️ <b>' . $daysLeft . ' GÜN</b> kaldı.';
        }

        if ($daysLeft === 0) {
            return '🚨 <b>BUGÜN</b> sona eriyor.';
        }

        return '🚨 <b>' . abs($daysLeft) . ' GÜN</b> önce sona erdi.';
    }
}
