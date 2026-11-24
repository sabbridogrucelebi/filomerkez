<?php

namespace App\Http\Controllers;

use App\Exceptions\TelegramRateLimited;
use App\Models\TelegramKaskoNotificationSetting;
use App\Models\TelegramKaskoNotificationLog;
use App\Services\TelegramAlertBotSettings;
use App\Services\TelegramAlertSchedule;
use App\Services\TelegramFleetAlertService;
use App\Services\TelegramDateChatService;
use App\Services\TelegramSendCooldown;
use App\Services\TelegramKaskoNotificationService;
use App\Services\TelegramKaskoChatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class TelegramKaskoNotificationController extends Controller
{
    public function migrateStorage(): RedirectResponse
    {
        $user = auth()->user();
        abort_unless(
            $user && ($user->isSuperAdmin() || $user->isCompanyAdmin()),
            403,
            'Bu işlem yalnızca firma yöneticisi veya süper yönetici tarafından yapılabilir.'
        );

        try {
            foreach ([
                'database/migrations/2026_11_23_100000_create_telegram_kasko_notification_settings_table.php',
                'database/migrations/2026_11_23_100100_make_telegram_kasko_reminder_day_signed.php',
                'database/migrations/2026_11_23_100200_add_last_telegram_update_id_to_kasko_settings.php',
                'database/migrations/2026_11_24_090000_add_upcoming_summary_to_telegram_kasko_settings.php',
                'database/migrations/2026_11_24_110000_create_telegram_fleet_alert_tables.php',
                'database/migrations/2026_11_24_120000_add_message_template_to_telegram_alert_bots.php',
                'database/migrations/2026_11_24_130000_add_imm_summary_to_telegram_alert_bots.php',
            ] as $path) {
                if (Artisan::call('migrate', ['--force' => true, '--path' => $path]) !== 0) {
                    throw new \RuntimeException('Telegram migration failed: ' . $path);
                }
            }

            return redirect()->route('telegram-kasko.index')->with('success', 'Telegram uyarı tabloları hazır.');
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('telegram-kasko.index')->with('error', 'Telegram tabloları güncellenemedi. Sunucu günlüğünü kontrol edin.');
        }
    }

    public function index(Request $request, TelegramAlertBotSettings $bots): View
    {
        $company = $this->company();
        $setting = $this->setting($company->id);

        $dashboard = $bots->dashboard($company->id);
        $selected = $request->query('channel', 'kasko');
        abort_unless(is_string($selected) && ($selected === 'kasko' || isset($dashboard['channels'][$selected])), 404);

        return view('telegram-kasko.index', [
            'company' => $company,
            'setting' => $setting,
            'hasBotToken' => filled($setting->bot_token),
            'kaskoRetryAfter' => TelegramSendCooldown::remaining($setting->bot_token),
            'selectedChannel' => $selected,
            'kaskoLastSent' => TelegramKaskoNotificationLog::where('company_id', $company->id)->max('sent_at'),
        ] + $dashboard);
    }

    public function update(Request $request, TelegramAlertBotSettings $bots): RedirectResponse
    {
        $company = $this->company();
        $setting = $this->setting($company->id);
        $validated = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'upcoming_summary_enabled' => ['nullable', 'boolean'],
            'upcoming_summary_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'bot_token' => ['nullable', 'string', 'max:255'],
            'chat_id' => ['nullable', 'string', 'max:100'],
            'reminder_days_text' => ['required', 'string', 'max:500'],
            'message_template' => ['required', 'string', 'max:4000'],
        ]);

        $days = TelegramAlertSchedule::parseDays($validated['reminder_days_text']);
        $isEnabled = $request->boolean('is_enabled');
        $botToken = filled($validated['bot_token'] ?? null) ? trim($validated['bot_token']) : $setting->bot_token;
        $chatId = filled($validated['chat_id'] ?? null) ? trim($validated['chat_id']) : $setting->chat_id;

        if ($isEnabled && (!filled($botToken) || !filled($chatId))) {
            throw ValidationException::withMessages([
                'is_enabled' => 'Bildirimleri etkinleştirmek için Telegram bot anahtarı ve sohbet kimliği gereklidir.',
            ]);
        }

        $setting->fill([
            'is_enabled' => $isEnabled,
            'upcoming_summary_enabled' => $request->boolean('upcoming_summary_enabled'),
            'upcoming_summary_days' => max(1, max($days)),
            'reminder_days' => $days,
            'message_template' => $validated['message_template'],
        ]);

        if (filled($validated['bot_token'] ?? null)) {
            $bots->ensureDistinctToken($company->id, 'kasko', $botToken);
            $setting->bot_token = trim($validated['bot_token']);
        }
        if (filled($validated['chat_id'] ?? null)) {
            $setting->chat_id = trim($validated['chat_id']);
        }

        $setting->save();

        return redirect()->route('telegram-kasko.index')->with('success', 'Telegram kasko bildirim ayarları kaydedildi.');
    }

    public function sendTest(TelegramKaskoNotificationService $telegram): RedirectResponse
    {
        $company = $this->company();
        $setting = $this->setting($company->id);

        if (!$setting->hasCredentials()) {
            return redirect()->route('telegram-kasko.index')->with('error', 'Önce bot anahtarı ve sohbet kimliğini kaydedin.');
        }

        try {
            $plate = $telegram->sendTestForCompany($setting);

            return redirect()->route('telegram-kasko.index')->with('success', $plate
                ? "Test mesajı {$plate} aracıyla Telegram'a gönderildi."
                : 'Bağlantı testi Telegram grubuna gönderildi.');
        } catch (TelegramRateLimited $exception) {
            return redirect()->route('telegram-kasko.index')->with('telegram_rate_limited', true);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('telegram-kasko.index')->with('error', 'Test mesajı gönderilemedi: ' . $exception->getMessage());
        }
    }

    public function connect(TelegramKaskoChatService $chat): RedirectResponse
    {
        $setting = $this->setting($this->company()->id);
        try {
            $message = $chat->connectGroup($setting);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }
        return back()->with('success', $message);
    }

    public function connectDateBot(string $channel, TelegramAlertBotSettings $bots, TelegramDateChatService $chat): RedirectResponse
    {
        abort_unless(in_array($channel, TelegramDateChatService::FLEET_CHANNELS, true), 404);
        $bot = $bots->bot($this->company()->id, $channel);
        $page = redirect()->route('telegram-kasko.index', ['channel' => $channel]);
        try {
            return $page->with('success', $chat->connectGroup($bot));
        } catch (\RuntimeException $exception) {
            return $page->with('error', $exception->getMessage());
        }
    }

    public function updateChannel(Request $request, string $channel, TelegramAlertBotSettings $bots): RedirectResponse
    {
        $bots->save($request, $this->company()->id, $channel);

        return redirect()->route('telegram-kasko.index', ['channel' => $channel])->with('success', 'Bot ayarları kaydedildi.');
    }

    public function testChannel(string $channel, TelegramAlertBotSettings $bots, TelegramFleetAlertService $alerts): RedirectResponse
    {
        $bot = $bots->bot($this->company()->id, $channel);
        try {
            $vehicleCount = $alerts->sendTest($bot);
        } catch (TelegramRateLimited $exception) {
            return redirect()->route('telegram-kasko.index', ['channel' => $channel])->with('telegram_rate_limited', true);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', match ($vehicleCount) {
            null => 'Örnek şablon Telegram’a gönderildi.',
            0 => 'Seçili gün aralığında süresi dolacak İMM poliçesi bulunamadı.',
            default => "İMM kontrolü tamamlandı. {$vehicleCount} araç Telegram’a bildirildi.",
        });
    }

    private function company()
    {
        $user = auth()->user();
        abort_unless($user && $user->hasPermission('telegram.manage'), 403, 'Bu sayfaya erişim yetkiniz yok.');
        abort_unless($user->company, 404, 'Firma kaydı bulunamadı.');

        return $user->company;
    }

    private function setting(int $companyId): TelegramKaskoNotificationSetting
    {
        return TelegramKaskoNotificationSetting::firstOrNew(
            ['company_id' => $companyId],
            [
                'reminder_days' => TelegramAlertSchedule::DEFAULT_DAYS,
                'upcoming_summary_enabled' => true,
                'upcoming_summary_days' => 20,
                'message_template' => TelegramKaskoNotificationSetting::defaultMessageTemplate(),
            ]
        );
    }

}
