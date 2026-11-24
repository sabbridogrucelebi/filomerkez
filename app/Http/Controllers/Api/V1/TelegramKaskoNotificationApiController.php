<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\TelegramKaskoNotificationSetting;
use App\Models\TelegramKaskoNotificationLog;
use App\Services\TelegramAlertBotSettings;
use App\Services\TelegramAlertSchedule;
use App\Services\TelegramFleetAlertService;
use App\Services\TelegramKaskoNotificationService;
use App\Services\TelegramKaskoChatService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class TelegramKaskoNotificationApiController extends BaseApiController
{
    public function show(Request $request, TelegramAlertBotSettings $bots)
    {
        $this->ensureCanManage($request);

        return $this->successResponse([
            'settings' => $this->payload($this->setting()),
        ] + $bots->dashboard($this->getCompanyId()));
    }

    public function update(Request $request, TelegramAlertBotSettings $bots)
    {
        $this->ensureCanManage($request);
        $setting = $this->setting();
        $validated = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'upcoming_summary_enabled' => ['nullable', 'boolean'],
            'upcoming_summary_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'bot_token' => ['nullable', 'string', 'max:255'],
            'chat_id' => ['nullable', 'string', 'max:100'],
            'reminder_days' => ['required', 'array', 'min:1'],
            'reminder_days.*' => ['integer', 'min:0', 'max:365'],
            'message_template' => ['required', 'string', 'max:4000'],
        ]);

        $days = collect($validated['reminder_days'])->map(fn ($day) => (int) $day)->unique()->sort()->values()->all();
        $botToken = filled($validated['bot_token'] ?? null) ? trim($validated['bot_token']) : $setting->bot_token;
        $chatId = filled($validated['chat_id'] ?? null) ? trim($validated['chat_id']) : $setting->chat_id;

        if ($validated['is_enabled'] && (!filled($botToken) || !filled($chatId))) {
            throw ValidationException::withMessages([
                'is_enabled' => 'Bildirimleri etkinleştirmek için Telegram bot anahtarı ve sohbet kimliği gereklidir.',
            ]);
        }

        $setting->fill([
            'is_enabled' => $validated['is_enabled'],
            'upcoming_summary_enabled' => $request->boolean('upcoming_summary_enabled', $setting->upcoming_summary_enabled ?? true),
            'upcoming_summary_days' => max(1, max($days)),
            'reminder_days' => $days,
            'message_template' => $validated['message_template'],
        ]);
        if (filled($validated['bot_token'] ?? null)) {
            $bots->ensureDistinctToken($this->getCompanyId(), 'kasko', $botToken);
            $setting->bot_token = trim($validated['bot_token']);
        }
        if (filled($validated['chat_id'] ?? null)) {
            $setting->chat_id = trim($validated['chat_id']);
        }
        $setting->save();

        return $this->successResponse([
            'settings' => $this->payload($setting),
        ], 'Telegram kasko bildirim ayarları kaydedildi.');
    }

    public function sendTest(Request $request, TelegramKaskoNotificationService $telegram)
    {
        $this->ensureCanManage($request);
        $setting = $this->setting();

        if (!$setting->hasCredentials()) {
            return $this->errorResponse('Önce bot anahtarı ve sohbet kimliğini kaydedin.', 422);
        }

        try {
            $plate = $telegram->sendTestForCompany($setting);

            return $this->successResponse([
                'vehicle_plate' => $plate,
            ], 'Test mesajı Telegram\'a gönderildi.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->errorResponse('Test mesajı gönderilemedi: ' . $exception->getMessage(), 422);
        }
    }

    public function connect(Request $request, TelegramKaskoChatService $chat)
    {
        $this->ensureCanManage($request);
        try {
            $chat->connectGroup($this->setting());
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }
        return $this->successResponse([], 'Telegram grup bağlantısını kabul etti. Gruba “kaskosu olmayan araçlar” yazın.');
    }

    public function updateChannel(Request $request, string $channel, TelegramAlertBotSettings $bots)
    {
        $this->ensureCanManage($request);
        $bots->save($request, $this->getCompanyId(), $channel);

        return $this->successResponse($bots->dashboard($this->getCompanyId()), 'Bot ayarları kaydedildi.');
    }

    public function testChannel(Request $request, string $channel, TelegramAlertBotSettings $bots, TelegramFleetAlertService $alerts)
    {
        $this->ensureCanManage($request);
        $bot = $bots->bot($this->getCompanyId(), $channel);
        try {
            $alerts->sendTest($bot);
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }

        return $this->successResponse([], 'Örnek şablon Telegram’a gönderildi.');
    }

    private function ensureCanManage(Request $request): void
    {
        abort_unless($this->userHasPermission($request->user(), 'telegram.manage'), 403, 'Bu işlem için yetkiniz bulunmamaktadır.');
        abort_unless($this->getCompanyId() > 0, 404, 'Firma kaydı bulunamadı.');
    }

    private function setting(): TelegramKaskoNotificationSetting
    {
        return TelegramKaskoNotificationSetting::firstOrNew(
            ['company_id' => $this->getCompanyId()],
            [
                'reminder_days' => TelegramAlertSchedule::DEFAULT_DAYS,
                'upcoming_summary_enabled' => true,
                'upcoming_summary_days' => 20,
                'message_template' => TelegramKaskoNotificationSetting::defaultMessageTemplate(),
            ]
        );
    }

    private function payload(TelegramKaskoNotificationSetting $setting): array
    {
        $days = TelegramAlertSchedule::days($setting->reminder_days ?: TelegramAlertSchedule::DEFAULT_DAYS);
        return [
            'is_enabled' => (bool) $setting->is_enabled,
            'upcoming_summary_enabled' => $setting->upcoming_summary_enabled ?? true,
            'upcoming_summary_days' => max($days ?: TelegramAlertSchedule::DEFAULT_DAYS),
            'has_bot_token' => filled($setting->bot_token),
            'chat_id' => $setting->chat_id,
            'reminder_days' => $days,
            'last_sent_at' => TelegramKaskoNotificationLog::where('company_id', $setting->company_id)->max('sent_at'),
            'message_template' => $setting->message_template ?: TelegramKaskoNotificationSetting::defaultMessageTemplate(),
        ];
    }
}
