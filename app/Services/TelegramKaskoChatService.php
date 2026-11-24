<?php

namespace App\Services;

use App\Models\Fleet\Vehicle;
use App\Models\TelegramKaskoNotificationSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
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

        // Grup sorguları, "otomatik uyarılar" tercihi kapalı olsa da kullanılabilmelidir.
        TelegramKaskoNotificationSetting::query()
            ->get()
            ->each(function (TelegramKaskoNotificationSetting $setting) use (&$handled) {
                if (!$setting->hasCredentials()) {
                    return;
                }

                try {
                    if (Cache::has($this->webhookCacheKey($setting))) {
                        return;
                    }
                    $response = Http::timeout(15)->acceptJson()->get(
                        'https://api.telegram.org/bot' . $setting->bot_token . '/getUpdates',
                        [
                            'offset' => ((int) $setting->last_telegram_update_id) + 1,
                            'allowed_updates' => json_encode(['message']),
                        ]
                    );

                    if (!$response->successful() || !$response->json('ok')) {
                        if ($response->status() === 409 && str_contains((string) $response->json('description'), 'webhook')) {
                            Cache::put($this->webhookCacheKey($setting), true, now()->addMinutes(10));
                        } else {
                            Log::warning('Telegram grup komutları alınamadı.', ['company_id' => $setting->company_id, 'status' => $response->status()]);
                        }
                        return;
                    }

                    foreach ($response->json('result', []) as $update) {
                        $updateId = (int) data_get($update, 'update_id', 0);
                        $handled += (int) $this->handleUpdate($setting, $update);
                        if ($updateId > 0) {
                            $setting->last_telegram_update_id = $updateId;
                            $setting->save();
                        }
                    }
                } catch (Throwable $exception) {
                    Log::warning('Telegram grup komutu işlenemedi.', ['company_id' => $setting->company_id, 'error_type' => $exception::class]);
                }
            });

        return $handled;
    }

    public function connectGroup(TelegramKaskoNotificationSetting $setting): void
    {
        if (!$setting->hasCredentials()) {
            throw new RuntimeException('Önce bot anahtarını ve grup kimliğini kaydedin.');
        }
        $base = rtrim(config('app.url'), '/');
        if (parse_url($base, PHP_URL_SCHEME) !== 'https' || !parse_url($base, PHP_URL_HOST)) {
            // cPanel kurulumunun dış adresi sabittir; istemciden gelen adresi Telegram'a aktarmayın.
            if (request()->getHost() !== 'mehmetcelebiturizm.com') {
                throw new RuntimeException('Sunucuda APP_URL, Filo Merkez’in tam HTTPS adresi olmalı.');
            }
            $base = 'https://mehmetcelebiturizm.com/app';
        } elseif (!parse_url($base, PHP_URL_PATH)) {
            $base .= rtrim(request()->getBaseUrl(), '/');
        }
        $url = $base . route('telegram-kasko.receive', $setting->id, false);
        $bot = 'https://api.telegram.org/bot' . $setting->bot_token;
        try {
            $info = Http::timeout(10)->get($bot . '/getWebhookInfo');
            if (!$info->successful() || !$info->json('ok')) {
                throw new RuntimeException('Telegram bot bağlantısını doğrulayamadı (HTTP ' . $info->status() . '). Bot anahtarını kontrol edin.');
            }
            $currentUrl = $info->json('result.url');
            if ($currentUrl && $currentUrl !== $url) {
                throw new RuntimeException('Bu bot başka bir adrese bağlı. Filo Merkez için ayrı bir bot kullanın.');
            }
            $result = Http::timeout(15)->post($bot . '/setWebhook', [
                'url' => $url, 'secret_token' => $this->webhookSecret($setting),
                'allowed_updates' => ['message'], 'max_connections' => 1,
            ]);
            if (!$result->successful() || !$result->json('ok')) {
                throw new RuntimeException('Telegram bağlantı adresini kabul etmedi (HTTP ' . $result->status() . '). Sunucunun HTTPS adresini kontrol edin.');
            }
        } catch (ConnectionException) {
            throw new RuntimeException('Sunucu Telegram’a bağlanamadı.');
        }
        Cache::put($this->webhookCacheKey($setting), true, now()->addDay());
    }

    public function webhookSecret(TelegramKaskoNotificationSetting $setting): string
    {
        return hash_hmac('sha256', 'kasko:' . $setting->id . ':' . $setting->bot_token, config('app.key'));
    }

    private function webhookCacheKey(TelegramKaskoNotificationSetting $setting): string
    {
        return 'telegram-kasko:webhook:' . hash('sha256', $this->webhookSecret($setting));
    }

    public function handleUpdate(TelegramKaskoNotificationSetting $setting, array $update): bool
    {
        $text = data_get($update, 'message.text');
        if (!is_int($update['update_id'] ?? null) || !is_string($text)
            || (string) data_get($update, 'message.chat.id') !== (string) $setting->chat_id) {
            return false;
        }
        $command = $this->normaliseCommand($text);
        $report = match ($command) {
            'kaskosu olmayan araclar', 'kaskosu olmayan' => 'missing',
            'suresi gecenler', 'suresi gecen araclar' => 'expired',
            'kaskolu araclarim', 'kaskolu araclar' => 'covered',
            'kasko raporu', 'kasko listesi' => 'attention',
            default => $this->isStartCommand($command) ? 'help' : null,
        };
        if ($report === null) {
            return false;
        }
        $key = $this->webhookCacheKey($setting) . ':update:' . $update['update_id'];
        return Cache::lock($key . ':lock', 180)->block(2, function () use ($key, $setting, $report) {
            if (Cache::get($key) === 'done') {
                return false;
            }
            $replies = $report === 'help'
                ? ["👋 <b>Filo Merkez Kasko Asistanı</b>\n\n❔ <code>kaskosu olmayan araçlar</code>\n⌛ <code>süresi geçenler</code>\n🛡️ <code>kaskolu araçlarım</code>"]
                : $this->kaskoReportMessages($setting->company_id, $report);
            $next = (int) Cache::get($key, 0);
            foreach (array_slice($replies, $next) as $message) {
                $this->telegram->sendTextMessage($setting, $message);
                Cache::put($key, ++$next, now()->addDays(2));
            }
            Cache::put($key, 'done', now()->addDays(2));
            return true;
        });
    }

    private function kaskoReportMessages(int $companyId, string $report): array
    {
        $today = today(config('app.timezone'));
        $vehicles = Vehicle::query()
            ->where('company_id', $companyId)
            ->with('kaskoDocuments')
            ->orderBy('plate')
            ->get();

        $entries = [];
        foreach ($vehicles as $vehicle) {
            $document = $vehicle->kaskoDocuments->first();
            $endDate = $vehicle->kaskoExpiryDate();

            // A renewal may already be uploaded while the previous policy is still in force.
            if ($document?->start_date?->gt($today)) {
                $inForce = $vehicle->kaskoDocuments->first(fn ($doc) => filled($doc->file_path)
                    && (!$doc->start_date || $doc->start_date->lte($today)) && $doc->end_date?->gte($today));
                if ($inForce) {
                    $document = $inForce;
                    $endDate = $inForce->end_date;
                }
            }

            $missingReason = match (true) {
                blank($document?->file_path) => 'Kasko belgesi yüklenmemiş.',
                $endDate === null => 'Kasko bitiş tarihi tanımsız.',
                (bool) $document->start_date?->gt($today) => 'Poliçe ' . $document->start_date->format('d.m.Y') . ' tarihinde başlayacak.',
                default => null,
            };
            $status = $missingReason ? 'missing' : ($endDate->lt($today) ? 'expired' : 'covered');
            if ($report === 'attention' ? $status === 'covered' : $status !== $report) {
                continue;
            }

            $entry = "🚘 <b>" . e($vehicle->plate ?: '-') . "</b> — " . e(trim(($vehicle->brand ?? '') . ' ' . ($vehicle->model ?? '')) ?: '-')
                . "\n👤 Ruhsat sahibi: " . e($vehicle->license_owner ?: '-')
                . "\n🏢 Vergi / T.C. No: " . e($vehicle->owner_tax_or_tc_no ?: '-') . "\n";
            if ($status === 'missing') {
                $entry .= "❔ " . $missingReason;
                $lastDated = $vehicle->kaskoDocuments->first(fn ($doc) => filled($doc->file_path) && $doc->end_date?->lt($today));
                if ($lastDated) {
                    if ($lastDated->start_date) {
                        $entry .= "\n📅 Önceki poliçe başlangıcı: " . $lastDated->start_date->format('d.m.Y');
                    }
                    $entry .= "\n🛡️ Önceki poliçe bitişi: " . $lastDated->end_date->format('d.m.Y');
                }
            } else {
                $days = (int) $today->diffInDays($endDate, false);
                $timing = match (true) {
                    $days < 0 => '⌛ ' . abs($days) . ' gün önce bitti',
                    $days === 0 => '⏳ Bugün bitiyor (0 gün kaldı)',
                    default => "⏳ {$days} gün kaldı",
                };
                if ($document->start_date) {
                    $entry .= '📅 Poliçe başlangıcı: ' . $document->start_date->format('d.m.Y') . "\n";
                }
                $entry .= '🛡️ Bitiş: ' . $endDate->format('d.m.Y') . "\n" . $timing;
            }
            $entries[] = $entry;
        }

        $title = match ($report) {
            'missing' => '❔ Kaskosu olmayan araçlar',
            'expired' => '⌛ Süresi geçen kaskolar',
            'covered' => '🛡️ Kaskolu araçlarım',
            'attention' => '📋 Kaskosu olmayan veya süresi geçen araçlar',
        };
        $heading = '<b>' . $title . "</b>\nToplam: <b>" . count($entries) . ' araç</b>';
        return $this->chunkMessages($heading, $entries);
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

        $total = count($messages);
        if ($total > 1) {
            foreach ($messages as $index => $message) {
                $messages[$index] = '<i>Sayfa ' . ($index + 1) . '/' . $total . "</i>\n" . $message;
            }
        }

        return $messages;
    }

    private function normaliseCommand(string $text): string
    {
        return Str::squish(Str::lower(Str::ascii($text, 'tr')));
    }

    private function isStartCommand(string $command): bool
    {
        return $command === '/start' || str_starts_with($command, '/start@');
    }
}
