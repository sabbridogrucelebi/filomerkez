<?php

namespace App\Http\Controllers;

use App\Models\Fleet\Vehicle;
use App\Models\TelegramKaskoNotificationSetting;
use App\Services\TelegramKaskoNotificationService;
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
        abort_unless(auth()->user()?->isSuperAdmin(), 403, 'Bu işlem yalnızca süper yönetici tarafından yapılabilir.');

        try {
            Artisan::call('migrate', [
                '--force' => true,
                '--path' => 'database/migrations/2026_11_23_100000_create_telegram_kasko_notification_settings_table.php',
            ]);

            return redirect()->route('telegram-kasko.index')->with('success', 'Telegram kasko tabloları hazır. ' . trim(Artisan::output()));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('telegram-kasko.index')->with('error', 'Migration çalıştırılamadı: ' . $exception->getMessage());
        }
    }

    public function index(): View
    {
        $company = $this->company();
        $setting = $this->setting($company->id);

        return view('telegram-kasko.index', [
            'company' => $company,
            'setting' => $setting,
            'hasBotToken' => filled($setting->bot_token),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $this->company();
        $setting = $this->setting($company->id);
        $validated = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'bot_token' => ['nullable', 'string', 'max:255'],
            'chat_id' => ['nullable', 'string', 'max:100'],
            'reminder_days_text' => ['required', 'string', 'max:500'],
            'message_template' => ['required', 'string', 'max:4000'],
        ]);

        $days = $this->parseReminderDays($validated['reminder_days_text']);
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

        return redirect()->route('telegram-kasko.index')->with('success', 'Telegram kasko bildirim ayarları kaydedildi.');
    }

    public function sendTest(TelegramKaskoNotificationService $telegram): RedirectResponse
    {
        $company = $this->company();
        $setting = $this->setting($company->id);

        if (!$setting->hasCredentials()) {
            return redirect()->route('telegram-kasko.index')->with('error', 'Önce bot anahtarı ve sohbet kimliğini kaydedin.');
        }

        $vehicle = Vehicle::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNotNull('kasko_end_date')
            ->whereDate('kasko_end_date', '>=', now(config('app.timezone'))->toDateString())
            ->orderBy('kasko_end_date')
            ->first();

        if (!$vehicle) {
            return redirect()->route('telegram-kasko.index')->with('error', 'Test mesajı için gelecekte kasko bitiş tarihi tanımlı aktif bir araç bulunamadı.');
        }

        try {
            $telegram->sendTest($setting, $vehicle);

            return redirect()->route('telegram-kasko.index')->with('success', "Test mesajı {$vehicle->plate} aracıyla Telegram'a gönderildi.");
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('telegram-kasko.index')->with('error', 'Test mesajı gönderilemedi: ' . $exception->getMessage());
        }
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
                'reminder_days' => [10, 15, 20],
                'message_template' => TelegramKaskoNotificationSetting::defaultMessageTemplate(),
            ]
        );
    }

    private function parseReminderDays(string $value): array
    {
        $days = collect(preg_split('/[\s,;]+/', trim($value)))
            ->filter(fn ($day) => $day !== '')
            ->filter(fn ($day) => filter_var($day, FILTER_VALIDATE_INT) !== false)
            ->map(fn ($day) => (int) $day)
            ->filter(fn (int $day) => $day >= 0 && $day <= 365)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($days === []) {
            throw ValidationException::withMessages([
                'reminder_days_text' => 'En az bir uyarı günü girin. Günler 0 ile 365 arasında olmalıdır.',
            ]);
        }

        return $days;
    }
}
