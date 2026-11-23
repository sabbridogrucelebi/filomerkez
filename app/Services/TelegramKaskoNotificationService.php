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

                $dates = array_map(fn (int $day) => $today->copy()->addDays($day)->toDateString(), $days);
                Vehicle::query()->with('kaskoDocuments')
                    ->where('company_id', $setting->company_id)
                    ->where(fn ($q) => $q->whereIn('kasko_end_date', $dates)
                        ->orWhereHas('kaskoDocuments', fn ($d) => $d->whereIn('documents.end_date', $dates)))
                    ->eachById(function (Vehicle $vehicle) use ($setting, $today, $days, &$sentCount) {
                        $expiryDate = $vehicle->kaskoExpiryDate()?->copy()->startOfDay();
                        if (!$expiryDate) {
                            return;
                        }
                        $daysLeft = $this->daysUntil($today, $expiryDate);

                        if (!in_array($daysLeft, $days, true)
                            || $this->wasAlreadySent($setting, $vehicle, $expiryDate, $daysLeft)) {
                            return;
                        }

                        try {
                            $messageId = $this->send($setting, $vehicle, $daysLeft);

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
            });

        return $sentCount;
    }

    public function sendTestForCompany(TelegramKaskoNotificationSetting $setting): ?string
    {
        $today = now(config('app.timezone'))->startOfDay();
        $vehicle = Vehicle::query()->with('kaskoDocuments')
            ->where('company_id', $setting->company_id)
            ->get()->filter(fn ($v) => $v->kaskoExpiryDate() !== null)
            ->sortBy(fn ($v) => abs($this->daysUntil($today, $v->kaskoExpiryDate())))->first();

        if (!$vehicle) {
            $this->sendTextMessage($setting, "✅ <b>Filo Merkez bağlantı testi</b>\nMesaj bu gruba ulaştı. Kritik Takvim’de kasko bitiş tarihi bulunan araç yok.");
            return null;
        }

        $this->sendTest($setting, $vehicle);
        return $vehicle->plate;
    }

    public function sendTest(TelegramKaskoNotificationSetting $setting, Vehicle $vehicle): string
    {
        $expiryDate = $vehicle->kaskoExpiryDate()->copy()->startOfDay();
        $daysLeft = $this->daysUntil(now(config('app.timezone'))->startOfDay(), $expiryDate);

        return $this->send($setting, $vehicle, $daysLeft);
    }

    public function renderMessage(TelegramKaskoNotificationSetting $setting, Vehicle $vehicle, int $daysLeft): string
    {
        $expiryDate = $vehicle->kaskoExpiryDate();
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

    private function send(TelegramKaskoNotificationSetting $setting, Vehicle $vehicle, int $daysLeft): string
    {
        return $this->sendTextMessage($setting, $this->renderMessage($setting, $vehicle, $daysLeft));
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
