<?php

namespace App\Http\Controllers;

use App\Exceptions\TelegramRateLimited;
use App\Models\TelegramKaskoNotificationSetting;
use App\Services\TelegramKaskoChatService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelegramKaskoWebhookController extends Controller
{
    public function __invoke(Request $request, int $id, TelegramKaskoChatService $chat): Response
    {
        $token = $request->header('X-Telegram-Bot-Api-Secret-Token');
        abort_unless(is_string($token) && strlen($token) === 64, 403);
        $setting = TelegramKaskoNotificationSetting::find($id);
        abort_unless($setting && $setting->hasCredentials() && hash_equals($chat->webhookSecret($setting), $token), 403);
        if ($setting->company?->is_active && $setting->company->isLicenseActive()) {
            try {
                if ($chat->handleUpdate($setting, $request->json()->all()) === null) {
                    return response('', 503);
                }
            } catch (TelegramRateLimited $exception) {
                return response('', 429, ['Retry-After' => (string) $exception->retryAfter]);
            }
        }

        return response()->noContent();
    }
}
