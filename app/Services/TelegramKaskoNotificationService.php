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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class TelegramKaskoNotificationService
{
    public function sendScheduledNotifications(): int
    {
        $sentCount = 0;
        $today = now(TelegramAlertSchedule::TIMEZONE)->startOfDay();

        TelegramKaskoNotificationSetting::query()
            ->where('is_enabled', true)
            ->get()
            ->each(function (TelegramKaskoNotificationSetting $setting) use ($today, &$sentCount) {
                if (!$setting->hasCredentials()) {
                    return;
                }

                $days = TelegramAlertSchedule::days($setting->reminder_days ?? []);

                try {
                    $sentCount += $this->sendUpcomingSummary($setting, $today, max($days ?: TelegramAlertSchedule::DEFAULT_DAYS));
                } catch (Throwable $exception) {
                    Log::warning('Telegram kasko özeti gönderilemedi.', [
                        'company_id' => $setting->company_id,
                        'error_type' => $exception::class,
                    ]);
                }

                Vehicle::query()->with('kaskoDocuments')
                    ->where('company_id', $setting->company_id)
                    ->where('is_active', true)
                    ->eachById(function (Vehicle $vehicle) use ($setting, $today, $days, &$sentCount) {
                        $policy = $this->latestUploadedPolicy($vehicle);
                        $expiryDate = $policy?->end_date?->copy()->startOfDay();
                        if (!$expiryDate) {
                            return;
                        }
                        $daysLeft = $this->daysUntil($today, $expiryDate);

                        $threshold = $daysLeft >= 0
                            ? TelegramAlertSchedule::dueThreshold($days, $daysLeft)
                            : (in_array($daysLeft, [-1, -5], true) ? $daysLeft : null);
                        if ($threshold === null || $this->wasAlreadySent($setting, $vehicle, $expiryDate, $threshold)) {
                            return;
                        }

                        try {
                            $messageId = $this->send($setting, $vehicle, $daysLeft, $expiryDate);

                            TelegramKaskoNotificationLog::create([
                                'company_id' => $setting->company_id,
                                'vehicle_id' => $vehicle->id,
                                'kasko_end_date' => $expiryDate->toDateString(),
                                'reminder_day' => $threshold,
                                'telegram_message_id' => $messageId,
                                'sent_at' => now(),
                            ]);

                            $sentCount++;
                        } catch (Throwable $exception) {
                            report($exception);
                            Log::warning('Telegram kasko bildirimi gönderilemedi.', [
                                'company_id' => $setting->company_id,
                                'vehicle_id' => $vehicle->id,
                                'reminder_day' => $daysLeft,
                            ]);
                        }
                    });
            });

        return $sentCount;
    }

    public function sendTestForCompany(TelegramKaskoNotificationSetting $setting): ?string
    {
        $today = now(TelegramAlertSchedule::TIMEZONE)->startOfDay();
        $vehicle = Vehicle::query()->with('kaskoDocuments')
            ->where('company_id', $setting->company_id)->where('is_active', true)
            ->get()->map(function (Vehicle $vehicle) {
                $vehicle->setRelation('telegramKaskoPolicy', $this->latestUploadedPolicy($vehicle));

                return $vehicle;
            })->filter(fn (Vehicle $vehicle) => $vehicle->getRelation('telegramKaskoPolicy') !== null)
            ->sortBy(fn (Vehicle $vehicle) => abs($this->daysUntil($today, $vehicle->getRelation('telegramKaskoPolicy')->end_date)))->first();

        if (!$vehicle) {
            $this->sendTextMessage($setting, "✅ <b>Filo Merkez bağlantı testi</b>\nMesaj bu gruba ulaştı. Yüklü kasko belgesi bulunan araç yok.");
            return null;
        }

        $this->sendTest($setting, $vehicle, $vehicle->getRelation('telegramKaskoPolicy')->end_date);
        return $vehicle->plate;
    }

    public function sendTest(TelegramKaskoNotificationSetting $setting, Vehicle $vehicle, ?Carbon $expiryDate = null): string
    {
        $expiryDate = ($expiryDate ?? $vehicle->kaskoExpiryDate())?->copy()->startOfDay();
        if (!$expiryDate) {
            throw new RuntimeException('Test için geçerli bir kasko poliçesi bulunamadı.');
        }
        $daysLeft = $this->daysUntil(now(TelegramAlertSchedule::TIMEZONE)->startOfDay(), $expiryDate);

        return $this->send($setting, $vehicle, $daysLeft, $expiryDate);
    }

    public function renderMessage(TelegramKaskoNotificationSetting $setting, Vehicle $vehicle, int $daysLeft, ?Carbon $expiryDate = null): string
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
        $template = $this->ensureOwnershipFields($template);

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

    private function send(TelegramKaskoNotificationSetting $setting, Vehicle $vehicle, int $daysLeft, Carbon $expiryDate): string
    {
        return $this->sendTextMessage($setting, $this->renderMessage($setting, $vehicle, $daysLeft, $expiryDate));
    }

    public function sendTextMessage(TelegramKaskoNotificationSetting|TelegramAlertBot $setting, string $message): string
    {
        if (!$setting->hasCredentials()) {
            throw new RuntimeException('Telegram bot anahtarı ve sohbet kimliği tanımlanmalıdır.');
        }

        $token = $setting->bot_token;
        $wait = TelegramSendCooldown::remaining($token);
        if ($wait > 0) {
            throw new TelegramRateLimited($wait);
        }

        $response = $this->postMessage($token, $setting->chat_id, $message);

        if ($response->status() === 429 || (int) $response->json('error_code') === 429) {
            $retryAfter = (int) $response->json('parameters.retry_after');
            if ($retryAfter <= 0) {
                $retryAfter = (int) $response->header('Retry-After');
            }

            throw new TelegramRateLimited(TelegramSendCooldown::defer($token, $retryAfter > 0 ? $retryAfter : 60));
        }

        if (!$response->successful() || !$response->json('ok')) {
            throw new RuntimeException($response->json('description') ?: 'Telegram mesajı gönderilemedi.');
        }

        return (string) $response->json('result.message_id');
    }

    private function postMessage(string $token, string $chatId, string $message): Response
    {
        $request = Http::connectTimeout(5)->timeout(15)->withoutRedirecting()->acceptJson();
        $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
        $payload = ['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];

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

    private function latestUploadedPolicy(Vehicle $vehicle): ?\App\Models\Document
    {
        return $vehicle->kaskoDocuments->first(fn ($document) => (int) $document->company_id === (int) $vehicle->company_id
            && filled($document->file_path) && $document->end_date);
    }

    private function sendUpcomingSummary(TelegramKaskoNotificationSetting $setting, Carbon $today, int $summaryDays): int
    {
        if (!$setting->upcoming_summary_enabled
            || $setting->last_upcoming_summary_date?->isSameDay($today)) {
            return 0;
        }

        $entries = Vehicle::query()->with('kaskoDocuments')
            ->where('company_id', $setting->company_id)
            ->where('is_active', true)
            ->get()
            ->map(function (Vehicle $vehicle) {
                $policy = $this->latestUploadedPolicy($vehicle);
                if (!$policy) {
                    return null;
                }

                return ['vehicle' => $vehicle, 'policy' => $policy];
            })
            ->filter()
            ->filter(function (array $item) use ($today, $summaryDays) {
                $remainingDays = $this->daysUntil($today, $item['policy']->end_date);

                return $remainingDays >= 0 && $remainingDays <= $summaryDays;
            })
            ->sortBy(fn (array $item) => $item['policy']->end_date)
            ->values();

        if ($entries->isEmpty()) {
            $setting->last_upcoming_summary_date = $today;
            $setting->save();

            return 0;
        }

        $lines = $entries->map(function (array $item) use ($today) {
            $vehicle = $item['vehicle'];
            $policy = $item['policy'];
            $remainingDays = $this->daysUntil($today, $policy->end_date);
            $startDate = $policy->start_date?->format('d.m.Y') ?? 'Kayıtlı değil';

            return "🚘 <b>" . e($vehicle->plate ?: '-') . '</b> — ' . e(trim(($vehicle->brand ?? '') . ' ' . ($vehicle->model ?? '')) ?: '-')
                . "\n👤 Ruhsat sahibi: " . e($vehicle->license_owner ?: '-')
                . "\n🏢 Vergi / T.C. No: " . e($vehicle->owner_tax_or_tc_no ?: '-')
                . "\n📅 Poliçe başlangıcı: {$startDate}"
                . "\n🛡️ Bitiş: " . $policy->end_date->format('d.m.Y')
                . "\n⏳ <b>{$remainingDays} gün kaldı</b>";
        })->all();

        $heading = "📋 <b>{$summaryDays} GÜN İÇİNDE BİTECEK KASKOLAR</b>\nToplam: <b>" . $entries->count() . ' araç</b>';
        $sent = 0;
        foreach ($this->chunkMessages($heading, $lines) as $message) {
            $this->sendTextMessage($setting, $message);
            $sent++;
        }

        $setting->last_upcoming_summary_date = $today;
        $setting->save();

        return $sent;
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

    private function chunkMessages(string $heading, array $lines): array
    {
        $messages = [];
        $current = $heading;
        foreach ($lines as $line) {
            $candidate = $current . "\n\n" . $line;
            if (mb_strlen($candidate) > 3500 && $current !== $heading) {
                $messages[] = $current;
                $current = $heading . "\n\n" . $line;
            } else {
                $current = $candidate;
            }
        }

        $messages[] = $current;

        return $messages;
    }

    private function daysUntil(Carbon $today, Carbon $expiryDate): int
    {
        $end = Carbon::parse($expiryDate->toDateString(), $today->timezone);

        return (int) $today->diffInDays($end, false);
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
