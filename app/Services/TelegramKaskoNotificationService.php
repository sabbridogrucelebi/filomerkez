<?php

namespace App\Services;

use App\Models\Fleet\Vehicle;
use App\Models\TelegramKaskoNotificationLog;
use App\Models\TelegramKaskoNotificationSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class TelegramKaskoNotificationService
{
    public function sendScheduledNotifications(): int
    {
        $sentCount = 0;
        $today = now(config('app.timezone'))->startOfDay();

        TelegramKaskoNotificationSetting::query()
            ->where('is_enabled', true)
            ->get()
            ->each(function (TelegramKaskoNotificationSetting $setting) use ($today, &$sentCount) {
                if (!$setting->hasCredentials()) {
                    return;
                }

                $configuredDays = $this->normaliseReminderDays($setting->reminder_days);

                // Süre bittikten sonra yalnızca iki kontrollü hatırlatma yapılır:
                // ertesi gün ve beşinci gün. Yenilenmiş belge varsa ikisi de atlanır.
                $days = collect($configuredDays)->merge([-1, -5])->unique()->sort()->values()->all();

                Vehicle::query()->with('kaskoDocuments')
                    ->where('company_id', $setting->company_id)
                    ->where('is_active', true)
                    ->eachById(function (Vehicle $vehicle) use ($setting, $today, $days, &$sentCount) {
                        $policy = $this->currentKaskoPolicy($vehicle, $today);
                        $expiryDate = $policy?->end_date?->copy()->startOfDay();
                        if (!$expiryDate) {
                            return;
                        }
                        $daysLeft = $this->daysUntil($today, $expiryDate);

                        if (!in_array($daysLeft, $days, true)
                            || ($daysLeft < 0 && $this->hasUploadedRenewal($vehicle, $expiryDate))
                            || $this->wasAlreadySent($setting, $vehicle, $expiryDate, $daysLeft)) {
                            return;
                        }

                        try {
                            $messageId = $this->send($setting, $vehicle, $daysLeft, $expiryDate);

                            TelegramKaskoNotificationLog::create([
                                'company_id' => $setting->company_id,
                                'vehicle_id' => $vehicle->id,
                                'kasko_end_date' => $expiryDate->toDateString(),
                                'reminder_day' => $daysLeft,
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

                $sentCount += $this->sendUpcomingSummary($setting, $today);
            });

        return $sentCount;
    }

    public function sendTestForCompany(TelegramKaskoNotificationSetting $setting): ?string
    {
        $today = now(config('app.timezone'))->startOfDay();
        $vehicle = Vehicle::query()->with('kaskoDocuments')
            ->where('company_id', $setting->company_id)
            ->get()->map(function (Vehicle $vehicle) use ($today) {
                $vehicle->setRelation('telegramKaskoPolicy', $this->currentKaskoPolicy($vehicle, $today));

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
        $daysLeft = $this->daysUntil(now(config('app.timezone'))->startOfDay(), $expiryDate);

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

        return strtr($template, [
            '{{company_name}}' => e($setting->company?->name ?? 'Filo Merkez'),
            '{{days_left}}' => (string) abs($daysLeft),
            '{{kasko_status}}' => $this->kaskoStatus($daysLeft),
            '{{plate}}' => e($vehicle->plate ?: '-'),
            '{{brand_model}}' => e(trim(($vehicle->brand ?? '') . ' ' . ($vehicle->model ?? '')) ?: '-'),
            '{{model_year}}' => e($vehicle->model_year ?: '-'),
            '{{license_serial_no}}' => e($vehicle->license_serial_no ?: '-'),
            '{{chassis_no}}' => e($vehicle->chassis_no ?: '-'),
            '{{kasko_end_date}}' => e($expiryDate->format('d.m.Y')),
        ]);
    }

    private function send(TelegramKaskoNotificationSetting $setting, Vehicle $vehicle, int $daysLeft, Carbon $expiryDate): string
    {
        return $this->sendTextMessage($setting, $this->renderMessage($setting, $vehicle, $daysLeft, $expiryDate));
    }

    public function sendTextMessage(TelegramKaskoNotificationSetting $setting, string $message): string
    {
        if (!$setting->hasCredentials()) {
            throw new RuntimeException('Telegram bot anahtarı ve sohbet kimliği tanımlanmalıdır.');
        }

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->post('https://api.telegram.org/bot' . $setting->bot_token . '/sendMessage', [
                    'chat_id' => $setting->chat_id,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException) {
            throw new RuntimeException('Sunucu Telegram’a bağlanamadı.');
        }

        if (!$response->successful() || !$response->json('ok')) {
            throw new RuntimeException($response->json('description') ?: 'Telegram mesajı gönderilemedi.');
        }

        return (string) $response->json('result.message_id');
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

    private function currentKaskoPolicy(Vehicle $vehicle, Carbon $today): ?\App\Models\Document
    {
        return $vehicle->kaskoDocuments->first(fn ($document) => filled($document->file_path)
            && $document->end_date
            && (!$document->start_date || $document->start_date->lte($today)));
    }

    private function hasUploadedRenewal(Vehicle $vehicle, Carbon $expiryDate): bool
    {
        return $vehicle->kaskoDocuments->contains(fn ($document) => filled($document->file_path)
            && $document->end_date
            && $document->end_date->gt($expiryDate));
    }

    private function sendUpcomingSummary(TelegramKaskoNotificationSetting $setting, Carbon $today): int
    {
        if (!$setting->upcoming_summary_enabled
            || $setting->last_upcoming_summary_date?->isSameDay($today)) {
            return 0;
        }

        $summaryDays = max(1, min(365, (int) ($setting->upcoming_summary_days ?: 20)));
        $entries = Vehicle::query()->with('kaskoDocuments')
            ->where('company_id', $setting->company_id)
            ->where('is_active', true)
            ->get()
            ->map(function (Vehicle $vehicle) use ($today) {
                $policy = $this->currentKaskoPolicy($vehicle, $today);
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
        return (int) $today->copy()->startOfDay()->diffInDays($expiryDate->copy()->startOfDay(), false);
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

    private function normaliseReminderDays(array|null $days): array
    {
        return collect($days ?? [])
            ->filter(fn ($day) => filter_var($day, FILTER_VALIDATE_INT) !== false)
            ->map(fn ($day) => (int) $day)
            ->filter(fn (int $day) => $day >= 0 && $day <= 365)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
