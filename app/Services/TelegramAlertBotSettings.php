<?php

namespace App\Services;

use App\Models\TelegramAlertBot;
use App\Models\TelegramAlertDelivery;
use App\Models\TelegramKaskoNotificationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TelegramAlertBotSettings
{
    public function available(): bool
    {
        return Schema::hasTable('telegram_alert_bots') && Schema::hasTable('telegram_alert_deliveries');
    }

    public function dashboard(int $companyId): array
    {
        $available = $this->available();
        $bots = $available ? TelegramAlertBot::where('company_id', $companyId)->get()->keyBy('channel') : collect();
        $lastSent = $available ? TelegramAlertDelivery::where('company_id', $companyId)
            ->selectRaw('bot_id, MAX(created_at) as last_sent_at')->groupBy('bot_id')->pluck('last_sent_at', 'bot_id') : collect();

        $channels = [];
        foreach (TelegramAlertBot::CHANNELS as $key => $meta) {
            $bot = $bots->get($key);
            $channels[$key] = $meta + [
                'is_enabled' => (bool) $bot?->is_enabled,
                'has_bot_token' => filled($bot?->bot_token),
                'chat_id' => $bot?->chat_id,
                'reminder_days' => $bot?->reminder_days ?? TelegramAlertSchedule::DEFAULT_DAYS,
                'maintenance_km' => $bot?->maintenance_km ?? 200,
                'last_sent_at' => $bot ? $lastSent->get($bot->id) : null,
            ];
        }

        return ['fleet_ready' => $available, 'channels' => $channels, 'schedule_time' => TelegramAlertSchedule::TIME];
    }

    public function bot(int $companyId, string $channel): TelegramAlertBot
    {
        abort_unless(isset(TelegramAlertBot::CHANNELS[$channel]), 404);
        abort_unless($this->available(), 503, 'Telegram modülleri için veritabanı güncellemesi gerekiyor.');

        return TelegramAlertBot::firstOrNew(['company_id' => $companyId, 'channel' => $channel]);
    }

    public function save(Request $request, int $companyId, string $channel): void
    {
        $bot = $this->bot($companyId, $channel);
        $kind = TelegramAlertBot::CHANNELS[$channel]['kind'];
        $data = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'bot_token' => ['nullable', 'string', 'regex:/^\d+:[A-Za-z0-9_-]+$/', 'max:255'],
            'chat_id' => ['nullable', 'string', 'regex:/^(?:-?\d+|@[A-Za-z0-9_]{5,})$/', 'max:100'],
            'reminder_days_text' => [$kind === 'date' ? 'required' : 'nullable', 'string', 'max:500'],
            'maintenance_km' => [$kind === 'maintenance' ? 'required' : 'nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $days = $kind === 'date' ? TelegramAlertSchedule::parseDays($data['reminder_days_text']) : null;
        if (filled($data['bot_token'] ?? null)) {
            $this->ensureDistinctToken($companyId, $channel, $data['bot_token']);
            $bot->bot_token = $data['bot_token'];
        }
        if (filled($data['chat_id'] ?? null)) {
            $bot->chat_id = $data['chat_id'];
        }
        if ($request->boolean('is_enabled') && !$bot->hasCredentials()) {
            throw ValidationException::withMessages(['is_enabled' => 'Etkinleştirmek için bot anahtarı ve sohbet kimliği girin.']);
        }
        $bot->fill(['is_enabled' => $request->boolean('is_enabled'), 'reminder_days' => $days,
            'maintenance_km' => $kind === 'maintenance' ? $data['maintenance_km'] : null])->save();
    }

    public function ensureDistinctToken(int $companyId, string $channel, string $token): void
    {
        if (!$this->available()) {
            return;
        }

        // BotFather yeni anahtar ürettiğinde ':' öncesindeki bot kimliği aynı kalır.
        $botId = str($token)->before(':')->toString();
        $others = TelegramAlertBot::where('company_id', $companyId)->where('channel', '!=', $channel)->get();
        if ($channel !== 'kasko' && ($kasko = TelegramKaskoNotificationSetting::where('company_id', $companyId)->first())) {
            $others = $others->toBase()->push($kasko);
        }
        foreach ($others as $other) {
            if (filled($other->bot_token) && $botId === str($other->bot_token)->before(':')->toString()) {
                throw ValidationException::withMessages(['bot_token' => 'Bu Telegram botu başka bir uyarı türüne bağlı. Ayrı bir bot kullanın.']);
            }
        }
    }
}
