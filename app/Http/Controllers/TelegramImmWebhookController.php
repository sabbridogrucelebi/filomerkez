<?php

namespace App\Http\Controllers;

use App\Exceptions\TelegramRateLimited;
use App\Models\TelegramAlertBot;
use App\Services\TelegramAlertBotSettings;
use App\Services\TelegramImmChatService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramImmWebhookController extends Controller
{
    public function __invoke(Request $request, int $id, TelegramAlertBotSettings $settings, TelegramImmChatService $chat): Response
    {
        $secret = $request->header('X-Telegram-Bot-Api-Secret-Token');
        abort_unless(is_string($secret) && strlen($secret) === 64 && $settings->available(), 403);
        $bot = TelegramAlertBot::with('company')->where('channel', 'imm')->find($id);
        abort_unless($bot && $bot->hasCredentials() && hash_equals($chat->webhookSecret($bot), $secret), 403);
        if (!$bot->company?->is_active || !$bot->company->isLicenseActive()) {
            return response()->noContent();
        }

        try {
            return $chat->receiveWebhook($bot, $request->json()->all()) ? response()->noContent() : response('', 503);
        } catch (TelegramRateLimited $exception) {
            return response('', 429, ['Retry-After' => (string) $exception->retryAfter]);
        } catch (Throwable $exception) {
            Log::warning('Telegram İMM webhook yanıtı işlenemedi.', ['bot_id' => $bot->id, 'error_type' => $exception::class]);

            return response('', 503);
        }
    }
}
