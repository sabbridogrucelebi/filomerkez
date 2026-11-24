<?php

namespace App\Services;

use App\Exceptions\TelegramRateLimited;
use App\Models\TelegramAlertBot;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class TelegramImmChatService
{
    private const REPLIES_PER_RUN = 8;

    public function __construct(
        private readonly TelegramAlertBotSettings $settings,
        private readonly TelegramKaskoNotificationService $telegram,
        private readonly TelegramFleetAlertMessage $messages,
    ) {
    }

    public function processIncomingCommands(): int
    {
        if (!$this->settings->available()) {
            return 0;
        }

        $handled = 0;
        TelegramAlertBot::with('company')->where('channel', 'imm')->eachById(function (TelegramAlertBot $bot) use (&$handled) {
            if (!$bot->hasCredentials() || !preg_match('/^-\d+$/', $bot->chat_id)
                || !$bot->company?->is_active || !$bot->company->isLicenseActive()) {
                return;
            }

            $token = $bot->bot_token;
            if (TelegramSendCooldown::remaining($token) > 0) {
                return;
            }

            $key = 'telegram:imm:chat:' . hash('sha256', $bot->id . ':' . Str::before($token, ':') . ':' . $bot->chat_id);
            try {
                $handled += (int) Cache::lock($key . ':lock', 180)->get(fn () => $this->poll($bot, $key));
            } catch (TelegramRateLimited) {
                // The transport records the wait; the same update resumes on a later run.
            } catch (Throwable $exception) {
                Log::warning('Telegram İMM grup komutu işlenemedi.', ['bot_id' => $bot->id, 'error_type' => $exception::class]);
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
            }
            Log::warning('Telegram İMM güncellemeleri alınamadı.', ['bot_id' => $bot->id, 'status' => $response->status()]);

            return 0;
        }

        $handled = 0;
        $budget = self::REPLIES_PER_RUN;
        foreach ($response->json('result', []) as $update) {
            $id = $update['update_id'] ?? null;
            if (!is_int($id) || $id < $offset) {
                continue;
            }

            if ($this->isControlMessage($bot, $update)) {
                $progressKey = $key . ':update:' . $id;
                if (!$this->sendReplies($bot, $progressKey, $budget)) {
                    break;
                }
                $handled++;
            }

            $offset = $id + 1;
            Cache::put($offsetKey, $offset, now()->addDays(2));
        }

        return $handled;
    }

    private function isControlMessage(TelegramAlertBot $bot, array $update): bool
    {
        $text = data_get($update, 'message.text');

        return is_string($text) && in_array(Str::lower(trim($text)), ['kontrol', '/kontrol'], true)
            && (string) data_get($update, 'message.chat.id') === $bot->chat_id
            && in_array(data_get($update, 'message.chat.type'), ['group', 'supergroup'], true)
            && !data_get($update, 'message.from.is_bot', false);
    }

    private function sendReplies(TelegramAlertBot $bot, string $key, int &$budget): bool
    {
        if ($budget <= 0) {
            return false;
        }

        $delivered = Cache::get($key, []);
        $today = today(TelegramAlertSchedule::TIMEZONE);
        $window = max(TelegramAlertSchedule::days($bot->reminder_days ?? []) ?: TelegramAlertSchedule::DEFAULT_DAYS);
        $entries = TelegramPolicyAlerts::upcoming($bot->company_id, 'immDocuments', $today, $window);
        if ($entries->isEmpty() && $delivered === []) {
            $this->telegram->sendTextMessage($bot, "☂️ Önümüzdeki {$window} gün içinde biten yüklü İMM poliçesi bulunamadı.");
            Cache::put($key, ['empty' => true], now()->addDays(2));
            $budget--;
        }

        foreach ($entries as ['vehicle' => $vehicle, 'policy' => $policy, 'days' => $days]) {
            if (isset($delivered[$vehicle->id])) {
                continue;
            }
            if ($budget <= 0) {
                return false;
            }

            $event = TelegramFleetAlertMessage::dateEvent($policy->end_date, $days, $policy);
            $this->telegram->sendTextMessage($bot, $this->messages->render($bot, $vehicle, $event));
            $delivered[$vehicle->id] = true;
            Cache::put($key, $delivered, now()->addDays(2));
            $budget--;
        }

        return true;
    }
}
