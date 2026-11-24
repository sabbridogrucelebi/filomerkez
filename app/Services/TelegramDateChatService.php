<?php

namespace App\Services;

use App\Exceptions\TelegramRateLimited;
use App\Models\TelegramAlertBot;
use App\Models\TelegramKaskoNotificationSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TelegramDateChatService
{
    public const FLEET_CHANNELS = ['imm', 'insurance', 'inspection', 'exhaust'];
    private const REPLIES_PER_RUN = 8;

    public function __construct(
        private readonly TelegramAlertBotSettings $settings,
        private readonly TelegramKaskoNotificationService $telegram,
        private readonly TelegramFleetAlertMessage $messages,
    ) {
    }

    public function connectGroup(TelegramAlertBot|TelegramKaskoNotificationSetting $bot): string
    {
        $channel = $this->channel($bot);
        if (!$bot->hasCredentials() || !preg_match('/^-\d+$/', $bot->chat_id)
            || ($channel !== 'kasko' && !in_array($channel, self::FLEET_CHANNELS, true))) {
            throw new RuntimeException('Bot anahtarını ve grubun başında eksi olan sayısal kimliğini kaydedin.');
        }

        $route = match ($channel) {
            'kasko' => 'telegram-kasko.receive',
            'imm' => 'telegram-imm.receive',
            default => 'telegram-date-bots.receive',
        };
        $label = $this->label($channel);
        $url = TelegramWebhookAddress::forRoute($route, $bot->id);
        $api = Http::baseUrl('https://api.telegram.org/bot' . $bot->bot_token)->connectTimeout(5)->timeout(10)->withoutRedirecting()->acceptJson();
        try {
            $identity = $api->get('getMe');
            if (!$identity->successful() || !$identity->json('ok') || !is_int($identity->json('result.id'))) {
                throw new RuntimeException("Telegram {$label} bot anahtarını doğrulayamadı (HTTP {$identity->status()}).");
            }
            $member = $api->get('getChatMember', ['chat_id' => $bot->chat_id, 'user_id' => $identity->json('result.id')]);
            $status = $member->json('result.status');
            if (!$member->successful() || !$member->json('ok') || !in_array($status, ['member', 'administrator', 'creator', 'restricted'], true)
                || ($status === 'restricted' && (!$member->json('result.is_member') || !$member->json('result.can_send_messages')))) {
                throw new RuntimeException("Telegram {$label} botunun gruba üye olduğunu ve mesaj gönderebildiğini doğrulayamadı. Grup kimliğini ve botun grup üyeliğini kontrol edin.");
            }
            $info = $api->get('getWebhookInfo');
            if (!$info->successful() || !$info->json('ok')) {
                throw new RuntimeException("Telegram {$label} grubunun komut bağlantısını doğrulayamadı (HTTP {$info->status()}).");
            }
            if (filled($info->json('result.url')) && $info->json('result.url') !== $url) {
                throw new RuntimeException("Bu bot başka bir uygulamaya bağlı. {$label} için Filo Merkez’e ait ayrı bir bot kullanın.");
            }

            $key = $this->cacheKey($bot);
            $username = $identity->json('result.username');
            $hasUsername = is_string($username) && preg_match('/^[A-Za-z0-9_]{5,32}$/', $username);
            if ($hasUsername) {
                Cache::forever($key . ':username', Str::lower($username));
            }
            $registered = $api->post('setWebhook', ['url' => $url, 'secret_token' => $this->webhookSecret($bot),
                'allowed_updates' => ['message'], 'max_connections' => 1]);
            if (!$registered->successful() || !$registered->json('ok')) {
                throw new RuntimeException("Telegram {$label} komut adresini kabul etmedi (HTTP {$registered->status()}). Sunucunun HTTPS adresini kontrol edin.");
            }
        } catch (ConnectionException) {
            throw new RuntimeException('Sunucu Telegram’a bağlanamadı. Hosting desteğinden api.telegram.org bağlantısını kontrol etmesini isteyin.');
        }

        Cache::put($key . ':webhook', true, now()->addHours(6));
        $readsAll = $identity->json('result.can_read_all_group_messages') || in_array($status, ['administrator', 'creator'], true);
        if ($readsAll) {
            return "{$label} grup bağlantısı kuruldu. Telegram grubuna kontrol yazın.";
        }

        $command = $hasUsername ? ' Telegram grubunda /kontrol@' . $username . ' komutunu kullanın.' : '';

        return "{$label} grup bağlantısı kuruldu." . $command . ' Düz kontrol mesajı için BotFather’da Group Privacy ayarını kapatıp botu gruba yeniden ekleyin.';
    }

    public function webhookSecret(TelegramAlertBot|TelegramKaskoNotificationSetting $bot): string
    {
        return hash_hmac('sha256', $this->channel($bot) . ':' . $bot->id . ':' . $bot->bot_token, config('app.key'));
    }

    public function receiveWebhook(TelegramAlertBot $bot, array $update): bool
    {
        Cache::put($this->cacheKey($bot) . ':webhook', true, now()->addHours(6));

        return $this->handleControl($bot, $update) ?? true;
    }

    /** Returns null for other commands, false while a recognized request is pending. */
    public function handleControl(TelegramAlertBot|TelegramKaskoNotificationSetting $bot, array $update): ?bool
    {
        if (!$bot->hasCredentials()) {
            return null;
        }
        $key = $this->cacheKey($bot);
        $id = $update['update_id'] ?? null;
        if (!is_int($id) || !$this->isControlMessage($bot, $update, $key)) {
            return null;
        }

        return Cache::lock($key . ':lock', 180)->get(function () use ($bot, $key, $id) {
            $budget = self::REPLIES_PER_RUN;

            return $this->sendUpdate($bot, $key, $id, $budget);
        });
    }

    public function processIncomingCommands(): int
    {
        if (!$this->settings->available()) {
            return 0;
        }

        $handled = 0;
        TelegramAlertBot::with('company')->whereIn('channel', self::FLEET_CHANNELS)->eachById(function (TelegramAlertBot $bot) use (&$handled) {
            if (!$bot->hasCredentials() || !preg_match('/^-\d+$/', $bot->chat_id)
                || !$bot->company?->is_active || !$bot->company->isLicenseActive()) {
                return;
            }

            $token = $bot->bot_token;
            if (TelegramSendCooldown::remaining($token) > 0) {
                return;
            }

            $key = $this->cacheKey($bot);
            if (Cache::has($key . ':webhook')) {
                return;
            }
            try {
                $handled += (int) Cache::lock($key . ':lock', 180)->get(fn () => $this->poll($bot, $key));
            } catch (TelegramRateLimited) {
                // The transport records the wait; the same update resumes on a later run.
            } catch (Throwable $exception) {
                Log::warning('Telegram tarih grubu komutu işlenemedi.', ['bot_id' => $bot->id, 'error_type' => $exception::class]);
            }
        });

        return $handled;
    }

    private function poll(TelegramAlertBot $bot, string $key): int
    {
        $offsetKey = $key . ':offset';
        $offset = (int) Cache::get($offsetKey, 0);
        $query = ['offset' => $offset, 'allowed_updates' => json_encode(['message']), 'limit' => 100];
        $url = 'https://api.telegram.org/bot' . $bot->bot_token . '/getUpdates';
        $request = Http::connectTimeout(5)->timeout(10)->withoutRedirecting()->acceptJson();
        try {
            $response = $request->get($url, $query);
        } catch (ConnectionException) {
            // Reading updates again with the same offset does not send another chat message.
            $response = $request->withOptions(['force_ip_resolve' => 'v4'])->get($url, $query);
        }
        if (!$response->successful() || !$response->json('ok')) {
            if ($response->status() === 429) {
                TelegramSendCooldown::defer($bot->bot_token, (int) ($response->json('parameters.retry_after') ?: 60));
            } elseif ($response->status() === 409 && str_contains(Str::lower((string) $response->json('description')), 'webhook')) {
                Cache::put($key . ':webhook', true, now()->addMinutes(10));
            }
            Log::warning('Telegram tarih grubu güncellemeleri alınamadı.', ['bot_id' => $bot->id, 'status' => $response->status()]);

            return 0;
        }

        $handled = 0;
        $budget = self::REPLIES_PER_RUN;
        foreach ($response->json('result', []) as $update) {
            $id = $update['update_id'] ?? null;
            if (!is_int($id) || $id < $offset) {
                continue;
            }

            if ($this->isControlMessage($bot, $update, $key)) {
                if (!$this->sendUpdate($bot, $key, $id, $budget)) {
                    break;
                }
                $handled++;
            }

            $offset = $id + 1;
            Cache::put($offsetKey, $offset, now()->addDays(2));
        }

        return $handled;
    }

    private function channel(TelegramAlertBot|TelegramKaskoNotificationSetting $bot): string
    {
        return $bot instanceof TelegramKaskoNotificationSetting ? 'kasko' : $bot->channel;
    }

    private function label(string $channel): string
    {
        return $channel === 'kasko' ? 'Kasko' : TelegramAlertBot::CHANNELS[$channel]['label'];
    }

    private function cacheKey(TelegramAlertBot|TelegramKaskoNotificationSetting $bot): string
    {
        return 'telegram:' . $this->channel($bot) . ':chat:' . hash('sha256', $bot->id . ':' . Str::before($bot->bot_token, ':') . ':' . $bot->chat_id);
    }

    private function isControlMessage(TelegramAlertBot|TelegramKaskoNotificationSetting $bot, array $update, string $key): bool
    {
        $text = data_get($update, 'message.text');
        if (!is_string($text) || (string) data_get($update, 'message.chat.id') !== $bot->chat_id
            || !in_array(data_get($update, 'message.chat.type'), ['group', 'supergroup'], true)
            || data_get($update, 'message.from.is_bot', false)) {
            return false;
        }
        $command = Str::lower(trim($text));
        if (in_array($command, ['kontrol', '/kontrol'], true)) {
            return true;
        }

        $username = Cache::get($key . ':username');

        return is_string($username) && $command === '/kontrol@' . $username;
    }

    private function sendUpdate(TelegramAlertBot|TelegramKaskoNotificationSetting $bot, string $key, int $id, int &$budget): bool
    {
        $progressKey = $key . ':update:' . $id;
        if (Cache::has($progressKey . ':done')) {
            return true;
        }
        if (!$this->sendReplies($bot, $progressKey, $budget)) {
            return false;
        }

        Cache::put($progressKey . ':done', true, now()->addDays(2));

        return true;
    }

    private function sendReplies(TelegramAlertBot|TelegramKaskoNotificationSetting $bot, string $key, int &$budget): bool
    {
        if ($budget <= 0) {
            return false;
        }

        $delivered = Cache::get($key, []);
        $today = today(TelegramAlertSchedule::TIMEZONE);
        $window = max(TelegramAlertSchedule::days($bot->reminder_days ?? []) ?: TelegramAlertSchedule::DEFAULT_DAYS);
        $channel = $this->channel($bot);
        $entries = TelegramPolicyAlerts::upcoming($bot->company_id, $channel, $today, $window);
        if ($entries->isEmpty() && $delivered === []) {
            $label = $this->label($channel);
            $this->telegram->sendTextMessage($bot, "📋 <b>" . e($label) . " kontrolü</b>\nÖnümüzdeki {$window} gün içinde süresi dolacak araç kaydı bulunamadı.");
            Cache::put($key, ['empty' => true], now()->addDays(2));
            $budget--;
        }

        foreach ($entries as ['vehicle' => $vehicle, 'policy' => $policy, 'end' => $end, 'days' => $days]) {
            if (isset($delivered[$vehicle->id])) {
                continue;
            }
            if ($budget <= 0) {
                return false;
            }

            $message = $bot instanceof TelegramKaskoNotificationSetting
                ? $this->telegram->renderMessage($bot, $vehicle, $days, $end, $policy?->start_date)
                : $this->messages->render($bot, $vehicle, TelegramFleetAlertMessage::dateEvent($end, $days, $policy));
            $this->telegram->sendTextMessage($bot, $message);
            $delivered[$vehicle->id] = true;
            Cache::put($key, $delivered, now()->addDays(2));
            $budget--;
        }

        return true;
    }
}
