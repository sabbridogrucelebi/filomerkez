<?php

namespace Tests\Feature;

use App\Models\Fleet\Vehicle;
use App\Models\TelegramKaskoNotificationSetting;
use App\Services\TelegramKaskoChatService;
use App\Services\TelegramKaskoNotificationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramKaskoFlowTest extends TestCase
{
    private TelegramKaskoNotificationSetting $setting;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'app.key' => 'base64:' . base64_encode(str_repeat('t', 32))]);
        DB::purge('sqlite');
        DB::statement('CREATE TABLE companies (id INTEGER PRIMARY KEY, name TEXT, is_active INTEGER, license_expires_at TEXT)');
        DB::statement('CREATE TABLE subscriptions (id INTEGER PRIMARY KEY, company_id INTEGER, status TEXT, ends_at TEXT)');
        DB::statement('CREATE TABLE vehicles (id INTEGER PRIMARY KEY, company_id INTEGER, plate TEXT, kasko_end_date TEXT, is_active INTEGER, deleted_at TEXT)');
        DB::statement('CREATE TABLE documents (id INTEGER PRIMARY KEY, company_id INTEGER, documentable_id INTEGER, documentable_type TEXT, document_type TEXT, start_date TEXT, end_date TEXT, archived_at TEXT, created_at TEXT, file_path TEXT)');
        DB::statement('CREATE TABLE telegram_kasko_notification_settings (id INTEGER PRIMARY KEY, company_id INTEGER, is_enabled INTEGER, upcoming_summary_enabled INTEGER, upcoming_summary_days INTEGER, bot_token TEXT, chat_id TEXT, reminder_days TEXT, last_telegram_update_id INTEGER, last_upcoming_summary_date TEXT, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE telegram_kasko_notification_logs (id INTEGER PRIMARY KEY, company_id INTEGER, vehicle_id INTEGER, kasko_end_date TEXT, reminder_day INTEGER, telegram_message_id TEXT, sent_at TEXT, created_at TEXT, updated_at TEXT)');
        DB::table('companies')->insert(['id' => 1, 'name' => 'Test', 'is_active' => 1, 'license_expires_at' => '2099-01-01']);
        $this->setting = TelegramKaskoNotificationSetting::create([
            'company_id' => 1, 'bot_token' => 'test-token', 'chat_id' => '-12345678901',
            'reminder_days' => [10], 'is_enabled' => true,
            'upcoming_summary_enabled' => false, 'upcoming_summary_days' => 20,
        ]);
        Http::preventStrayRequests();
        Http::fake(['*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
            '*/getWebhookInfo' => Http::response(['ok' => true, 'result' => ['url' => '']]),
            '*/setWebhook' => Http::response(['ok' => true])]);
    }

    private function vehicle(string $plate, array $attributes = []): int
    {
        return DB::table('vehicles')->insertGetId($attributes + ['company_id' => 1, 'plate' => $plate, 'is_active' => 1]);
    }

    private function document(int $vehicleId, int $remaining, array $attributes = []): void
    {
        DB::table('documents')->insert($attributes + [
            'company_id' => 1, 'documentable_type' => Vehicle::class, 'documentable_id' => $vehicleId,
            'document_type' => 'Kasko', 'start_date' => '2025-01-01', 'file_path' => 'documents/policy.pdf',
            'end_date' => today()->addDays($remaining)->toDateString(),
        ]);
    }

    public function test_test_message_reaches_telegram_even_when_all_dates_are_undefined(): void
    {
        $this->vehicle('UNDEFINED');
        $this->assertNull(app(TelegramKaskoNotificationService::class)->sendTestForCompany($this->setting));
        Http::assertSent(fn (Request $r) => $r['chat_id'] === $this->setting->chat_id && str_contains($r['text'], 'bağlantı testi'));
    }

    public function test_signed_group_query_uses_the_calendar_and_replies_once(): void
    {
        $this->vehicle('UNDEFINED', ['is_active' => 0]);
        $this->vehicle('CARD-ONLY', ['kasko_end_date' => today()->addDays(15)->toDateString()]);
        $this->document($this->vehicle('NO-ATTACHMENT'), 20, ['file_path' => null]);
        $this->document($this->vehicle('UNDATED'), 0, ['end_date' => null]);
        $this->vehicle('OTHER-TENANT', ['company_id' => 2]);
        $this->vehicle('DELETED', ['deleted_at' => today()]);
        $this->document($this->vehicle('EXPIRES-TODAY'), 0);
        $this->document($this->vehicle('EXPIRED'), -5, ['archived_at' => today()]);
        $renewed = $this->vehicle('RENEWED', ['kasko_end_date' => today()->subDay()->toDateString()]);
        $this->document($renewed, -8);
        $this->document($renewed, 100);

        $url = route('telegram-kasko.receive', $this->setting->id);
        $update = ['update_id' => 151, 'message' => ['chat' => ['id' => -12345678901], 'text' => 'kaskosu olmayan araçlar']];
        $headers = ['X-Telegram-Bot-Api-Secret-Token' => app(TelegramKaskoChatService::class)->webhookSecret($this->setting)];
        $this->postJson($url, $update)->assertForbidden();
        $this->postJson($url, $update, ['X-Telegram-Bot-Api-Secret-Token' => str_repeat('0', 64)])->assertForbidden();
        $this->postJson($url, array_replace_recursive($update, ['message' => ['chat' => ['id' => 999]]]), $headers)->assertNoContent();
        $this->postJson($url, $update, $headers)->assertNoContent();
        $this->postJson($url, $update, $headers)->assertNoContent();
        Http::assertSentCount(1);
        $text = Http::recorded()->first()[0]['text'];
        foreach (['UNDEFINED', 'NO-ATTACHMENT', 'CARD-ONLY', 'UNDATED', '4 araç'] as $value) {
            $this->assertStringContainsString($value, $text);
        }
        foreach (['EXPIRED', 'EXPIRES-TODAY', 'RENEWED', 'DELETED', 'OTHER-TENANT'] as $value) {
            $this->assertStringNotContainsString($value, $text);
        }
        $update['update_id']++;
        $update['message']['text'] = 'Süresi geçenler';
        $this->postJson($url, $update, $headers)->assertNoContent();
        $expired = Http::recorded()->last()[0]['text'];
        foreach (['EXPIRED', '1 araç', '01.01.2025', today()->subDays(5)->format('d.m.Y'), '5 gün önce bitti'] as $value) {
            $this->assertStringContainsString($value, $expired);
        }
        $this->assertStringNotContainsString('RENEWED', $expired);
        $this->assertStringNotContainsString('UNDEFINED', $expired);
        $update['update_id']++;
        $update['message']['text'] = '  KASKOLU   ARAÇLARIM  ';
        $this->postJson($url, $update, $headers)->assertNoContent();
        $covered = Http::recorded()->last()[0]['text'];
        foreach (['2 araç', 'EXPIRES-TODAY', 'RENEWED', 'Bugün bitiyor', '100 gün kaldı', '01.01.2025', today()->addDays(100)->format('d.m.Y')] as $value) {
            $this->assertStringContainsString($value, $covered);
        }
        foreach (['EXPIRED', 'NO-ATTACHMENT', 'UNDEFINED', 'CARD-ONLY', 'UNDATED'] as $value) {
            $this->assertStringNotContainsString($value, $covered);
        }
    }

    public function test_a_future_policy_is_counted_only_when_its_coverage_starts(): void
    {
        $id = $this->vehicle('NEXT-POLICY');
        $this->document($id, 365, ['start_date' => today()->addDay()->toDateString()]);
        $service = app(TelegramKaskoChatService::class);
        $query = ['update_id' => 21, 'message' => ['chat' => ['id' => -12345678901], 'text' => 'kaskolu araçlarım']];
        $service->handleUpdate($this->setting, $query);
        $this->assertStringContainsString('0 araç', Http::recorded()->last()[0]['text']);
        $this->document($id, 0);
        $query['update_id']++;
        $service->handleUpdate($this->setting, $query);
        $this->assertStringContainsString('Bugün bitiyor', Http::recorded()->last()[0]['text']);
        $this->travel(1)->days();
        $query['update_id']++;
        $service->handleUpdate($this->setting, $query);
        $this->assertStringContainsString('364 gün kaldı', Http::recorded()->last()[0]['text']);
    }

    public function test_scheduled_alerts_follow_uploaded_documents_and_stop_after_renewal(): void
    {
        $vehicle = $this->vehicle('KASKO-DOC');
        $this->document($vehicle, -1);
        $service = app(TelegramKaskoNotificationService::class);
        $this->assertSame('KASKO-DOC', $service->sendTestForCompany($this->setting));
        $this->assertSame(1, $service->sendScheduledNotifications());
        $this->assertSame(0, $service->sendScheduledNotifications());
        $this->travel(4)->days();
        $this->assertSame(1, $service->sendScheduledNotifications());
        $this->travel(1)->days();
        $this->assertSame(0, $service->sendScheduledNotifications());
        $second = $this->vehicle('RENEWED');
        $this->document($second, -5);
        $this->document($second, 360);
        $this->assertSame(0, $service->sendScheduledNotifications());
    }

    public function test_daily_summary_lists_every_uploaded_policy_ending_within_the_configured_window_once(): void
    {
        $this->setting->update(['upcoming_summary_enabled' => true, 'upcoming_summary_days' => 20]);
        $this->document($this->vehicle('ONE-DAY'), 1, ['start_date' => '2026-01-01']);
        $this->document($this->vehicle('TWENTY-DAY'), 20, ['start_date' => '2026-02-01']);
        $this->document($this->vehicle('OUTSIDE-WINDOW'), 21);
        $this->document($this->vehicle('NO-FILE'), 5, ['file_path' => null]);
        $this->document($this->vehicle('INACTIVE', ['is_active' => 0]), 5);

        $service = app(TelegramKaskoNotificationService::class);
        $this->assertSame(1, $service->sendScheduledNotifications());
        $message = Http::recorded()->last()[0]['text'];
        foreach (['20 GÜN İÇİNDE BİTECEK KASKOLAR', 'Toplam: <b>2 araç</b>', 'ONE-DAY', 'TWENTY-DAY', '1 gün kaldı', '20 gün kaldı'] as $value) {
            $this->assertStringContainsString($value, $message);
        }
        foreach (['OUTSIDE-WINDOW', 'NO-FILE', 'INACTIVE'] as $value) {
            $this->assertStringNotContainsString($value, $message);
        }
        $this->assertSame(today()->toDateString(), $this->setting->refresh()->last_upcoming_summary_date->toDateString());
        $this->assertSame(0, $service->sendScheduledNotifications());
    }

    public function test_telegram_registers_the_subdirectory_callback_with_a_secret(): void
    {
        config(['app.url' => 'https://fleet.example/app']);
        app(TelegramKaskoChatService::class)->connectGroup($this->setting);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/setWebhook')
            && $r['url'] === 'https://fleet.example/app/api/telegram-kasko/incoming/' . $this->setting->id
            && strlen($r['secret_token']) === 64);
        foreach (['/telegram-kasko/connect', '/api/v1/telegram-kasko-settings/connect'] as $path) {
            $route = app('router')->getRoutes()->match(\Illuminate\Http\Request::create($path, 'POST'));
            $this->assertTrue(is_callable([$route->getController(), 'connect']));
            $this->assertContains('permission:telegram.manage', $route->gatherMiddleware());
        }
    }

    public function test_cpanel_panel_and_mobile_register_the_same_https_callback_with_default_app_url(): void
    {
        config(['app.url' => 'http://localhost']);
        foreach (['/app/telegram-kasko/connect', '/app/api/v1/telegram-kasko-settings/connect'] as $path) {
            $this->app->instance('request', \Illuminate\Http\Request::create(
                'https://mehmetcelebiturizm.com' . $path, 'POST', [], [], [],
                ['SCRIPT_NAME' => '/app/index.php', 'SCRIPT_FILENAME' => '/var/www/app/index.php']
            ));
            app(TelegramKaskoChatService::class)->connectGroup($this->setting);
        }
        $registrations = Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/setWebhook'));
        $this->assertCount(2, $registrations);
        foreach ($registrations as [$request]) {
            $this->assertSame('https://mehmetcelebiturizm.com/app/api/telegram-kasko/incoming/' . $this->setting->id, $request['url']);
        }
    }

    public function test_unconfigured_site_does_not_register_a_callback_from_the_request_host(): void
    {
        config(['app.url' => 'http://localhost']);
        $this->app->instance('request', \Illuminate\Http\Request::create('https://other.example/app/telegram-kasko/connect'));
        try {
            app(TelegramKaskoChatService::class)->connectGroup($this->setting);
            $this->fail('A callback must have a trusted public address.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('APP_URL', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_calendar_falls_back_to_the_vehicle_field_but_automation_requires_an_uploaded_kasko_document(): void
    {
        $fallback = today()->addDays(10)->toDateString();
        $id = $this->vehicle('CALENDAR', ['kasko_end_date' => $fallback]);
        $this->document($id, 30, ['document_type' => 'Sigorta']);
        $this->assertSame($fallback, Vehicle::findOrFail($id)->kaskoExpiryDate()->toDateString());
        $service = app(TelegramKaskoNotificationService::class);
        $this->assertSame(0, $service->sendScheduledNotifications());
        $this->document($id, 40);
        $this->assertSame(today()->addDays(40)->toDateString(), Vehicle::findOrFail($id)->kaskoExpiryDate()->toDateString());
        $this->assertSame(0, $service->sendScheduledNotifications());
    }
}
