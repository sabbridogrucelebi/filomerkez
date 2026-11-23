<?php

namespace App\Services;

use App\Models\Fleet\Vehicle;
use App\Models\TelegramKaskoNotificationSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class TelegramKaskoChatService
{
    public function __construct(private readonly TelegramKaskoNotificationService $telegram)
    {
    }

    /** Gruba yazılan desteklenen komutları en fazla bir kez işler. */
    public function processIncomingCommands(): int
    {
        $handled = 0;

        TelegramKaskoNotificationSetting::withoutGlobalScopes()
            ->where('is_enabled', true)
            ->get()
            ->each(function (TelegramKaskoNotificationSetting $setting) use (&$handled) {
                if (!$setting->hasCredentials()) {
                    return;
                }

                try {
                    $response = Http::timeout(15)->acceptJson()->get(
                        'https://api.telegram.org/bot' . $setting->bot_token . '/getUpdates',
                        [
                            'offset' => ((int) $setting->last_telegram_update_id) + 1,
                            'allowed_updates' => json_encode(['message']),
                        ]
                    );

                    if (!$response->successful() || !$response->json('ok')) {
                        Log::warning('Telegram grup komutları alınamadı.', ['company_id' => $setting->company_id]);
                        return;
                    }

                    foreach ($response->json('result', []) as $update) {
                        $updateId = (int) data_get($update, 'update_id', 0);
                        $message = data_get($update, 'message', []);
                        $text = data_get($message, 'text');
                        $chatId = (string) data_get($message, 'chat.id', '');

                        if ($updateId > 0) {
                            $setting->last_telegram_update_id = $updateId;
                        }

                        if ($chatId !== (string) $setting->chat_id || !is_string($text)) {
                            continue;
                        }

                        $command = $this->normaliseCommand($text);
                        if ($this->isStartCommand($command)) {
                            $this->telegram->sendTextMessage(
                                $setting,
                                "👋 <b>Filo Merkez Kasko Asistanı</b>\n\nKaskosu olmayan veya süresi geçen araçları görmek için şunu yazın:\n\n<code>kaskosu olmayan araçlar</code>"
                            );
                            $handled++;
                        } elseif (in_array($command, ['kaskosu olmayan araclar', 'kaskosu olmayan', 'kasko raporu', 'kasko listesi'], true)) {
                            foreach ($this->missingKaskoReportMessages($setting->company_id) as $report) {
                                $this->telegram->sendTextMessage($setting, $report);
                            }
                            $handled++;
                        }
                    }

                    $setting->save();
                } catch (Throwable $exception) {
                    report($exception);
                    Log::warning('Telegram grup komutu işlenemedi.', ['company_id' => $setting->company_id]);
                }
            });

        return $handled;
    }

    private function missingKaskoReportMessages(int $companyId): array
    {
        $today = now(config('app.timezone'))->startOfDay();
        $vehicles = Vehicle::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->with(['documents' => fn ($query) => $query
                ->whereNull('archived_at')
                ->where('document_type', 'Kasko')
                ->whereNotNull('end_date')
                ->orderByDesc('end_date')])
            ->orderBy('plate')
            ->get();

        $entries = [];
        foreach ($vehicles as $vehicle) {
            $document = $vehicle->documents->first();
            $endDate = $document?->end_date
                ? Carbon::parse($document->end_date)->startOfDay()
                : ($vehicle->kasko_end_date ? Carbon::parse($vehicle->kasko_end_date)->startOfDay() : null);

            if ($endDate && $endDate->gt($today)) {
                continue;
            }

            $entry = "🚘 <b>" . e($vehicle->plate ?: '-') . "</b> — " . e(trim(($vehicle->brand ?? '') . ' ' . ($vehicle->model ?? '')) ?: '-') . "\n";
            if (!$endDate) {
                $entry .= "❌ <b>Kasko kaydı bulunmuyor.</b>\n📅 Son başlangıç: -\n🛡️ Son bitiş: -";
            } else {
                $startDate = $document?->start_date ? Carbon::parse($document->start_date)->format('d.m.Y') : '-';
                $daysOverdue = abs($today->diffInDays($endDate, false));
                $entry .= "⚠️ <b>Kasko süresi geçmiş.</b>\n📅 Son başlangıç: {$startDate}\n🛡️ Son bitiş: " . $endDate->format('d.m.Y') . " ({$daysOverdue} gün önce)";
            }
            $entries[] = $entry;
        }

        if ($entries === []) {
            return ['✅ <b>Kasko durumu</b>\n\nKaskosu olmayan veya süresi geçen aktif araç bulunmuyor.'];
        }

        return $this->chunkMessages('📋 <b>KASKOSU OLMAYAN / SÜRESİ GEÇEN ARAÇLAR</b>', $entries);
    }

    private function chunkMessages(string $heading, array $entries): array
    {
        $messages = [];
        $current = $heading;
        foreach ($entries as $entry) {
            $candidate = $current . "\n\n" . $entry;
            if (strlen($candidate) > 3000 && $current !== $heading) {
                $messages[] = $current;
                $current = $heading . "\n\n" . $entry;
            } else {
                $current = $candidate;
            }
        }
        $messages[] = $current;

        return $messages;
    }

    private function normaliseCommand(string $text): string
    {
        return trim(Str::ascii(Str::lower($text)));
    }

    private function isStartCommand(string $command): bool
    {
        return $command === '/start' || str_starts_with($command, '/start@');
    }
}
