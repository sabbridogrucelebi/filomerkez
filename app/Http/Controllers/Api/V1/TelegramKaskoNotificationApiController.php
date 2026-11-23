<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Fleet\Vehicle;
use App\Models\TelegramKaskoNotificationSetting;
use App\Services\TelegramKaskoNotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class TelegramKaskoNotificationApiController extends BaseApiController
{
    public function show(Request $request)
    {
        $this->ensureCanManage($request);

        return $this->successResponse([
            'settings' => $this->payload($this->setting()),
        ]);
    }

    public function update(Request $request)
    {
        $this->ensureCanManage($request);
        $setting = $this->setting();
        $validated = $request->validate([
            'is_enabled' => ['required', 'boolean'],
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
            'reminder_days' => $days,
            'message_template' => $validated['message_template'],
        ]);
        if (filled($validated['bot_token'] ?? null)) {
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

        $vehicle = Vehicle::query()
            ->where('company_id', $this->getCompanyId())
            ->where('is_active', true)
            ->whereNotNull('kasko_end_date')
            ->whereDate('kasko_end_date', '>=', now(config('app.timezone'))->toDateString())
            ->orderBy('kasko_end_date')
            ->first();

        if (!$vehicle) {
            return $this->errorResponse('Test mesajı için gelecekte kasko bitiş tarihi tanımlı aktif bir araç bulunamadı.', 422);
        }

        try {
            $telegram->sendTest($setting, $vehicle);

            return $this->successResponse([
                'vehicle_plate' => $vehicle->plate,
            ], 'Test mesajı Telegram\'a gönderildi.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->errorResponse('Test mesajı gönderilemedi: ' . $exception->getMessage(), 422);
        }
    }

    private function ensureCanManage(Request $request): void
    {
        abort_unless($this->userHasPermission($request->user(), 'telegram.manage'), 403, 'Bu işlem için yetkiniz bulunmamaktadır.');
    }

    private function setting(): TelegramKaskoNotificationSetting
    {
        TelegramKaskoNotificationSetting::ensureStorage();

        return TelegramKaskoNotificationSetting::firstOrNew(
            ['company_id' => $this->getCompanyId()],
            [
                'reminder_days' => [10, 15, 20],
                'message_template' => TelegramKaskoNotificationSetting::defaultMessageTemplate(),
            ]
        );
    }

    private function payload(TelegramKaskoNotificationSetting $setting): array
    {
        return [
            'is_enabled' => (bool) $setting->is_enabled,
            'has_bot_token' => filled($setting->bot_token),
            'chat_id' => $setting->chat_id,
            'reminder_days' => $setting->reminder_days ?: [10, 15, 20],
            'message_template' => $setting->message_template ?: TelegramKaskoNotificationSetting::defaultMessageTemplate(),
        ];
    }
}
