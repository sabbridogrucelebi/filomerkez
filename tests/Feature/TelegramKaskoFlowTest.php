<?php

namespace Tests\Feature;

use App\Models\Fleet\Vehicle;
use App\Models\TelegramAlertBot;
use App\Models\TelegramKaskoNotificationLog;
use App\Models\TelegramKaskoNotificationSetting;
use App\Models\User;
use App\Services\TelegramAlertBotSettings;
use App\Services\TelegramFleetAlertService;
use App\Services\TelegramImmChatService;
use App\Services\TelegramKaskoChatService;
use App\Services\TelegramKaskoNotificationService;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\Request;
use Illuminate\Console\Scheduling\Schedule;
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
        DB::statement('CREATE TABLE vehicles (id INTEGER PRIMARY KEY, company_id INTEGER, plate TEXT, brand TEXT, model TEXT, license_owner TEXT, owner_tax_or_tc_no TEXT, kasko_end_date TEXT, is_active INTEGER, deleted_at TEXT, inspection_date TEXT, insurance_end_date TEXT, exhaust_date TEXT, min_km_per_liter REAL, current_km INTEGER)');
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
        $this->document($this->vehicle('NO-ATTACHMENT', [
            'license_owner' => 'Mehmet Taşımacılık Tic. Ltd. Şti.',
            'owner_tax_or_tc_no' => '6130942149',
        ]), 20, ['file_path' => null]);
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
        foreach (['UNDEFINED', 'NO-ATTACHMENT', 'CARD-ONLY', 'UNDATED', '4 araç', 'Mehmet Taşımacılık Tic. Ltd. Şti.', '6130942149'] as $value) {
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
        $this->setting->update(['upcoming_summary_enabled' => true, 'upcoming_summary_days' => 8, 'reminder_days' => [1, 5, 20]]);
        $this->document($this->vehicle('ONE-DAY', [
            'license_owner' => 'Mehmet Taşımacılık Tic. Ltd. Şti.',
            'owner_tax_or_tc_no' => '6130942149',
        ]), 1, ['start_date' => '2026-01-01']);
        $this->document($this->vehicle('TWENTY-DAY'), 20, ['start_date' => '2026-02-01']);
        $this->document($this->vehicle('OUTSIDE-WINDOW'), 21);
        $this->document($this->vehicle('NO-FILE'), 5, ['file_path' => null]);
        $this->document($this->vehicle('INACTIVE', ['is_active' => 0]), 5);

        $service = app(TelegramKaskoNotificationService::class);
        $this->assertSame(3, $service->sendScheduledNotifications());
        $message = Http::recorded()->first()[0]['text'];
        foreach (['20 GÜN İÇİNDE BİTECEK KASKOLAR', 'Toplam: <b>2 araç</b>', 'ONE-DAY', 'TWENTY-DAY', '1 gün kaldı', '20 gün kaldı', 'Mehmet Taşımacılık Tic. Ltd. Şti.', '6130942149'] as $value) {
            $this->assertStringContainsString($value, $message);
        }
        foreach (['OUTSIDE-WINDOW', 'NO-FILE', 'INACTIVE'] as $value) {
            $this->assertStringNotContainsString($value, $message);
        }
        $this->assertSame(today()->toDateString(), $this->setting->refresh()->last_upcoming_summary_date->toDateString());
        $this->assertSame(0, $service->sendScheduledNotifications());
    }

    public function test_missed_threshold_sends_once_with_actual_days_and_renewal_replaces_it(): void
    {
        $this->travelTo(now('Europe/Istanbul')->setDate(2026, 11, 24)->setTime(7, 50));
        $this->setting->update(['reminder_days' => [1, 3, 5, 7, 10, 15, 20]]);
        $vehicle = $this->vehicle('DEMO-0850');
        $this->document($vehicle, 4);
        $this->document($this->vehicle('OUTSIDE'), 21);
        $this->document($this->vehicle('ANOTHER-FIVE'), 5);

        $service = app(TelegramKaskoNotificationService::class);
        $this->assertSame(2, $service->sendScheduledNotifications());
        $this->assertSame(0, $service->sendScheduledNotifications());
        Http::assertSent(fn (Request $request) => str_contains($request['text'], 'DEMO-0850') && str_contains($request['text'], '4 GÜN'));
        $this->assertSame(5, TelegramKaskoNotificationLog::where('vehicle_id', $vehicle)->value('reminder_day'));

        $this->document($vehicle, 365, ['start_date' => today()->addDays(5)->toDateString()]);
        $this->travel(1)->days();
        $this->assertSame(1, $service->sendScheduledNotifications());
        $this->assertStringContainsString('OUTSIDE', Http::recorded()->last()[0]['text']);
        $this->assertSame(1, TelegramKaskoNotificationLog::where('vehicle_id', $vehicle)->count());

        $this->travel(344)->days();
        $this->assertSame(1, $service->sendScheduledNotifications());
        $this->assertStringContainsString('DEMO-0850', Http::recorded()->last()[0]['text']);
        $this->assertSame(2, TelegramKaskoNotificationLog::where('vehicle_id', $vehicle)->count());
    }

    public function test_fleet_bots_use_separate_credentials_and_deduplicate_document_maintenance_and_fuel_alerts(): void
    {
        $this->travelTo(now('Europe/Istanbul')->setDate(2026, 11, 24)->setTime(7, 50));
        DB::statement('CREATE TABLE fuels (id INTEGER PRIMARY KEY, company_id INTEGER, vehicle_id INTEGER, date TEXT, km INTEGER, liters REAL)');
        DB::statement('CREATE TABLE vehicle_maintenance_settings (id INTEGER PRIMARY KEY, company_id INTEGER, vehicle_id INTEGER, oil_change_interval_km INTEGER, under_lubrication_interval_km INTEGER)');
        DB::statement('CREATE TABLE vehicle_maintenances (id INTEGER PRIMARY KEY, company_id INTEGER, vehicle_id INTEGER, maintenance_type TEXT, service_date TEXT, km INTEGER, next_service_km INTEGER, next_service_date TEXT, status TEXT)');
        (require database_path('migrations/2026_11_24_110000_create_telegram_fleet_alert_tables.php'))->up();
        $vehicle = $this->vehicle('FLEET-ONE', [
            'inspection_date' => today()->addDays(3)->toDateString(),
            'insurance_end_date' => today()->addDays(5)->toDateString(),
            'exhaust_date' => today()->addDays(7)->toDateString(),
            'min_km_per_liter' => 8, 'current_km' => 1100,
        ]);
        $this->document($vehicle, 11, ['document_type' => 'İMM POLİÇESİ']);
        $this->document($vehicle, 2, ['document_type' => 'Sigorta']);
        $this->document($vehicle, 1, ['document_type' => 'Muayene', 'company_id' => 2]);
        DB::table('fuels')->insert([
            ['company_id' => 1, 'vehicle_id' => $vehicle, 'date' => today()->subDays(4)->toDateString(), 'km' => 1000, 'liters' => 20],
            ['company_id' => 1, 'vehicle_id' => $vehicle, 'date' => today()->subDay()->toDateString(), 'km' => 1100, 'liters' => 20],
        ]);
        DB::table('vehicle_maintenances')->insert(['company_id' => 1, 'vehicle_id' => $vehicle, 'maintenance_type' => 'PERİYODİK BAKIM',
            'service_date' => today()->subMonth()->toDateString(), 'km' => 900, 'next_service_km' => 1200, 'status' => 'completed']);

        foreach (array_keys(TelegramAlertBot::CHANNELS) as $i => $channel) {
            TelegramAlertBot::create(['company_id' => 1, 'channel' => $channel, 'bot_token' => (100 + $i) . ':demo',
                'chat_id' => (string) (-200 - $i), 'is_enabled' => true]);
        }

        $service = app(TelegramFleetAlertService::class);
        $this->assertSame(6, $service->sendScheduledNotifications());
        $this->assertSame(0, $service->sendScheduledNotifications());
        foreach (['11 gün kaldı', '2 gün kaldı', '3 gün kaldı', '7 gün kaldı', '100 km kaldı', '5,00 km/L'] as $i => $text) {
            Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/bot' . (100 + $i) . ':demo/sendMessage')
                && $request['chat_id'] === (string) (-200 - $i) && str_contains($request['text'], $text));
        }
        $dashboard = app(TelegramAlertBotSettings::class)->dashboard(1);
        $this->assertTrue($dashboard['fleet_ready']);
        $this->assertArrayNotHasKey('bot_token', $dashboard['channels']['fuel']);
        $this->assertNotNull($dashboard['channels']['fuel']['last_sent_at']);
    }

    public function test_telegram_sends_at_0750_in_turkey(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains($event->command, 'telegram:kasko-alerts')
            || str_contains($event->command, 'telegram:fleet-alerts'));
        $this->assertCount(2, $events);
        foreach ($events as $event) {
            $this->assertSame('50 7 * * *', $event->expression);
            $this->assertSame('Europe/Istanbul', $event->timezone);
        }
    }

    public function test_fleet_settings_keep_bot_tokens_separate_even_after_rotation(): void
    {
        (require database_path('migrations/2026_11_24_110000_create_telegram_fleet_alert_tables.php'))->up();
        $settings = app(TelegramAlertBotSettings::class);
        $this->setting->update(['bot_token' => '123:demo']);
        $request = \Illuminate\Http\Request::create('/', 'PUT', [
            'is_enabled' => true, 'bot_token' => '123:rotated', 'chat_id' => '-555', 'reminder_days_text' => '20, 5',
        ]);

        try {
            $settings->save($request, 1, 'imm');
            $this->fail('A bot already assigned to Kasko cannot be shared with İMM.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('bot_token', $exception->errors());
        }
        $this->assertSame(0, TelegramAlertBot::count());
        $request->merge(['bot_token' => '124:demo']);
        $settings->save($request, 1, 'imm');
        $this->assertSame([5, 20], $settings->dashboard(1)['channels']['imm']['reminder_days']);
        $this->assertFalse($settings->dashboard(2)['channels']['imm']['has_bot_token']);
        $this->assertNotSame('124:demo', DB::table('telegram_alert_bots')->value('bot_token'));
    }

    public function test_fleet_templates_use_defaults_and_save_test_and_send_per_bot(): void
    {
        (require database_path('migrations/2026_11_24_110000_create_telegram_fleet_alert_tables.php'))->up();
        $settings = app(TelegramAlertBotSettings::class);
        $this->assertFalse($settings->dashboard(1)['fleet_templates_ready']);
        $this->assertStringContainsString('İMM POLİÇESİ YENİLEME UYARISI', $settings->dashboard(1)['channels']['imm']['message_template']);
        (require database_path('migrations/2026_11_24_120000_add_message_template_to_telegram_alert_bots.php'))->up();
        $request = \Illuminate\Http\Request::create('/', 'PUT', [
            'is_enabled' => true, 'bot_token' => '124:demo', 'chat_id' => '-555', 'reminder_days_text' => '20, 5',
            'message_template' => '📌 <b>{{plate}}</b> · {{expiry_status}} · {{license_owner}}',
        ]);
        $settings->save($request, 1, 'imm');
        $dashboard = $settings->dashboard(1);
        $this->assertTrue($dashboard['fleet_templates_ready']);
        $this->assertSame($request->input('message_template'), $dashboard['channels']['imm']['message_template']);
        $this->assertContains('expiry_status', $dashboard['channels']['imm']['template_fields']);
        $this->assertStringContainsString('TRAFİK SİGORTASI', $dashboard['channels']['insurance']['message_template']);

        $vehicle = $this->vehicle('TEMPLATE-ONE', ['license_owner' => 'A & B <Filo>']);
        $this->document($vehicle, 5, ['document_type' => 'İMM Poliçesi']);
        $service = app(TelegramFleetAlertService::class);
        $this->assertSame(1, $service->sendTest($settings->bot(1, 'imm')));
        Http::assertSent(fn (Request $message) => str_contains($message['text'], 'İMM KONTROLÜ')
            && str_contains($message['text'], 'TEMPLATE-ONE') && str_contains($message['text'], '5 gün kaldı')
            && !str_contains($message['text'], 'ÖRNEK-42'));
        $this->assertSame(1, $service->sendScheduledNotifications());
        Http::assertSent(fn (Request $message) => $message['text'] === "📌 <b>TEMPLATE-ONE</b> · 5 gün kaldı · A &amp; B &lt;Filo&gt;\n\n📅 <b>Poliçe başlangıcı:</b> 01.01.2025");

        $request->merge(['message_template' => '']);
        $settings->save($request, 1, 'imm');
        $this->assertNull($settings->bot(1, 'imm')->message_template);
        $this->assertStringContainsString('İMM POLİÇESİ YENİLEME UYARISI', $settings->dashboard(1)['channels']['imm']['message_template']);
    }

    public function test_imm_control_only_reports_uploaded_current_policies_inside_the_saved_window(): void
    {
        (require database_path('migrations/2026_11_24_110000_create_telegram_fleet_alert_tables.php'))->up();
        $this->travelTo(now('Europe/Istanbul')->setDate(2026, 11, 24)->setTime(10, 0));
        $this->withoutMiddleware()->actingAs(new User(['company_id' => 1, 'role' => 'company_admin']));
        $bot = TelegramAlertBot::create(['company_id' => 1, 'channel' => 'imm', 'bot_token' => '124:demo', 'chat_id' => '-555', 'reminder_days' => [5, 1, 20]]);
        $url = '/api/v1/telegram-kasko-settings/bots/imm/test';
        $this->postJson($url)->assertOk()->assertJsonPath('data.vehicle_count', 0);
        Http::assertNothingSent();

        $this->document($this->vehicle('IMM-TODAY'), 0, ['document_type' => 'İMM POLİÇESİ']);
        $this->document($this->vehicle('IMM-IN-WINDOW'), 8, ['document_type' => 'İMM Poliçesi']);
        $this->document($this->vehicle('IMM-END-OF-WINDOW'), 20, ['document_type' => 'İMM Poliçesi']);
        $this->document($this->vehicle('IMM-OUTSIDE'), 21, ['document_type' => 'İMM Poliçesi']);
        $this->document($this->vehicle('IMM-OTHER-TENANT', ['company_id' => 2]), 1, ['document_type' => 'İMM Poliçesi', 'company_id' => 2]);
        $this->document($this->vehicle('IMM-INACTIVE', ['is_active' => 0]), 1, ['document_type' => 'İMM Poliçesi']);
        $this->document($this->vehicle('IMM-NO-FILE'), 2, ['document_type' => 'İMM Poliçesi', 'file_path' => null]);
        $this->document($this->vehicle('IMM-EXPIRED'), -1, ['document_type' => 'İMM Poliçesi']);
        $renewed = $this->vehicle('IMM-RENEWED');
        $this->document($renewed, 2, ['document_type' => 'İMM Poliçesi']);
        $this->document($renewed, 367, ['document_type' => 'İMM Poliçesi', 'start_date' => today()->addDays(3)->toDateString()]);

        $this->postJson($url)->assertOk()->assertJsonPath('data.vehicle_count', 3);
        Http::assertSentCount(1);
        $text = Http::recorded()->first()[0]['text'];
        foreach (['İMM KONTROLÜ', 'IMM-TODAY', 'IMM-IN-WINDOW', 'IMM-END-OF-WINDOW', '8 gün kaldı', '20 gün kaldı'] as $value) {
            $this->assertStringContainsString($value, $text);
        }
        foreach (['IMM-OUTSIDE', 'IMM-OTHER-TENANT', 'IMM-INACTIVE', 'IMM-NO-FILE', 'IMM-EXPIRED', 'IMM-RENEWED', 'ÖRNEK-42'] as $value) {
            $this->assertStringNotContainsString($value, $text);
        }
        $this->assertSame(0, DB::table('telegram_alert_deliveries')->where('bot_id', $bot->id)->count());
    }

    public function test_imm_daily_summary_and_expiry_alerts_follow_the_same_policy_rules_as_kasko(): void
    {
        (require database_path('migrations/2026_11_24_110000_create_telegram_fleet_alert_tables.php'))->up();
        (require database_path('migrations/2026_11_24_130000_add_imm_summary_to_telegram_alert_bots.php'))->up();
        $this->travelTo(now('Europe/Istanbul')->setDate(2026, 11, 24)->setTime(7, 50));
        $bot = TelegramAlertBot::create(['company_id' => 1, 'channel' => 'imm', 'bot_token' => '124:demo', 'chat_id' => '-555',
            'is_enabled' => true, 'reminder_days' => [1, 3, 5, 20], 'upcoming_summary_enabled' => true]);
        $near = $this->vehicle('IMM-CATCH-UP');
        $this->document($near, 4, ['document_type' => 'İMM Poliçesi']);
        $this->document($this->vehicle('IMM-POST-EXPIRY'), -1, ['document_type' => 'İMM POLİÇESİ']);

        $service = app(TelegramFleetAlertService::class);
        $this->assertSame(3, $service->sendScheduledNotifications());
        $this->assertSame(0, $service->sendScheduledNotifications());
        $this->assertSame(today()->toDateString(), $bot->refresh()->last_upcoming_summary_date->toDateString());
        $this->assertStringContainsString('20 GÜN İÇİNDE BİTECEK İMM POLİÇELERİ', Http::recorded()->first()[0]['text']);
        Http::assertSent(fn (Request $request) => str_contains($request['text'], 'IMM-CATCH-UP') && str_contains($request['text'], '4 gün kaldı'));
        Http::assertSent(fn (Request $request) => str_contains($request['text'], 'IMM-POST-EXPIRY') && str_contains($request['text'], '1 gün önce sona erdi'));
        $this->assertStringEndsWith(':5', DB::table('telegram_alert_deliveries')->where('vehicle_id', $near)->value('event_key'));

        $this->document($near, 370, ['document_type' => 'İMM Poliçesi', 'start_date' => today()->addDays(5)->toDateString()]);
        $this->travel(1)->days();
        $this->assertSame(0, $service->sendScheduledNotifications());
        $this->travel(3)->days();
        $this->assertSame(1, $service->sendScheduledNotifications());
        $this->assertStringContainsString('5 gün önce sona erdi', Http::recorded()->last()[0]['text']);
        $this->travel(1)->days();
        $this->assertSame(0, $service->sendScheduledNotifications());
    }

    public function test_imm_control_resumes_after_a_rate_limit_without_resending_delivered_pages(): void
    {
        (require database_path('migrations/2026_11_24_110000_create_telegram_fleet_alert_tables.php'))->up();
        $this->travelTo(now('Europe/Istanbul')->setDate(2026, 11, 24)->setTime(10, 0));
        $this->withoutMiddleware()->actingAs(new User(['company_id' => 1, 'role' => 'company_admin']));
        TelegramAlertBot::create(['company_id' => 1, 'channel' => 'imm', 'bot_token' => '124:demo', 'chat_id' => '-555']);
        for ($i = 1; $i <= 8; $i++) {
            $this->document($this->vehicle('IMM-PAGE-' . $i, ['license_owner' => str_repeat('A', 500)]), $i,
                ['document_type' => 'İMM Poliçesi']);
        }
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake(['*/sendMessage' => Http::sequence()
            ->push(['ok' => true, 'result' => ['message_id' => 1]])
            ->push(['ok' => false, 'error_code' => 429, 'parameters' => ['retry_after' => 32]], 429)
            ->push(['ok' => true, 'result' => ['message_id' => 2]])]);

        $url = '/api/v1/telegram-kasko-settings/bots/imm/test';
        $this->postJson($url)->assertStatus(429);
        $this->travel(32)->seconds();
        $this->postJson($url)->assertOk()->assertJsonPath('data.vehicle_count', 8);
        Http::assertSentCount(3);
        $texts = Http::recorded()->map(fn ($record) => $record[0]['text'])->all();
        $this->assertNotSame($texts[0], $texts[2]);
        $this->assertSame($texts[1], $texts[2]);
        $this->assertStringContainsString('IMM-PAGE-1', $texts[0]);
        $this->assertStringContainsString('IMM-PAGE-8', $texts[2]);
    }

    public function test_telegram_control_sends_individual_imm_messages_only_for_the_configured_group(): void
    {
        (require database_path('migrations/2026_11_24_110000_create_telegram_fleet_alert_tables.php'))->up();
        $this->travelTo(now('Europe/Istanbul')->setDate(2026, 11, 24)->setTime(10, 0));
        TelegramAlertBot::create(['company_id' => 1, 'channel' => 'imm', 'bot_token' => '124:demo', 'chat_id' => '-555', 'reminder_days' => [5, 20]]);
        $known = $this->vehicle('IMM-WITH-START', ['brand' => 'ISUZU', 'model' => 'ROYBUS']);
        $this->document($known, 5, ['document_type' => 'İMM Poliçesi', 'start_date' => '2025-11-29']);
        $this->document($this->vehicle('IMM-MISSING-START'), 7, ['document_type' => 'İMM POLİÇESİ', 'start_date' => null]);
        $this->document($this->vehicle('IMM-OUTSIDE-CONTROL'), 21, ['document_type' => 'İMM Poliçesi']);
        $this->document($this->vehicle('IMM-OTHER-COMPANY', ['company_id' => 2]), 5, ['document_type' => 'İMM Poliçesi', 'company_id' => 2]);

        Http::fake(['*/getUpdates*' => Http::response(['ok' => true, 'result' => [
            ['update_id' => 100, 'message' => ['text' => 'kontrol', 'chat' => ['id' => -888, 'type' => 'group']]],
            ['update_id' => 101, 'message' => ['text' => 'kontrol', 'chat' => ['id' => -555, 'type' => 'supergroup'], 'from' => ['is_bot' => true]]],
            ['update_id' => 102, 'message' => ['text' => '  KONTROL  ', 'chat' => ['id' => -555, 'type' => 'group']]],
        ]])]);

        $service = app(TelegramImmChatService::class);
        $this->assertSame(1, $service->processIncomingCommands());
        $this->assertSame(0, $service->processIncomingCommands());
        $sent = Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/sendMessage'));
        $this->assertCount(2, $sent);
        [$first, $second] = $sent->map(fn (array $pair) => $pair[0]['text'])->values()->all();
        foreach (['☂️ İMM POLİÇESİ YENİLEME UYARISI', 'IMM-WITH-START', 'ISUZU ROYBUS', 'Poliçe başlangıcı:</b> 29.11.2025', '5 gün kaldı'] as $value) {
            $this->assertStringContainsString($value, $first);
        }
        $this->assertStringNotContainsString('IMM-MISSING-START', $first);
        $this->assertStringContainsString('IMM-MISSING-START', $second);
        $this->assertStringContainsString('7 gün kaldı', $second);
        $this->assertStringNotContainsString('Poliçe başlangıcı', $second);
        $this->assertStringNotContainsString('policy_start_line', $second);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/getUpdates?') && str_contains($request->url(), 'offset=103'));
    }

    public function test_imm_group_connection_verifies_the_bot_and_routes_signed_webhook_commands(): void
    {
        (require database_path('migrations/2026_11_24_110000_create_telegram_fleet_alert_tables.php'))->up();
        $this->travelTo(now('Europe/Istanbul')->setDate(2026, 11, 24)->setTime(11, 50));
        config(['app.url' => 'https://fleet.example/app']);
        $bot = TelegramAlertBot::create(['company_id' => 1, 'channel' => 'imm', 'bot_token' => '124:demo', 'chat_id' => '-555']);
        $this->document($this->vehicle('IMM-IMMEDIATE'), 3, ['document_type' => 'İMM Poliçesi', 'start_date' => null]);
        Http::fake([
            '*/getMe' => Http::response(['ok' => true, 'result' => ['id' => 124, 'username' => 'CelebiImmBot', 'can_read_all_group_messages' => false]]),
            '*/getChatMember*' => Http::response(['ok' => true, 'result' => ['status' => 'member']]),
        ]);

        $chat = app(TelegramImmChatService::class);
        $this->assertStringContainsString('/kontrol@CelebiImmBot', $chat->connectGroup($bot));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/getChatMember?')
            && str_contains($request->url(), 'chat_id=-555') && str_contains($request->url(), 'user_id=124'));
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/setWebhook')
            && $request['url'] === 'https://fleet.example/app/api/telegram-imm/incoming/' . $bot->id
            && $request['secret_token'] === $chat->webhookSecret($bot));

        $url = '/api/telegram-imm/incoming/' . $bot->id;
        $headers = ['X-Telegram-Bot-Api-Secret-Token' => $chat->webhookSecret($bot)];
        $update = ['update_id' => 300, 'message' => ['text' => '/kontrol@CelebiImmBot', 'chat' => ['id' => -555, 'type' => 'group']]];
        $this->postJson($url, $update)->assertForbidden();
        $this->postJson($url, $update, ['X-Telegram-Bot-Api-Secret-Token' => str_repeat('0', 64)])->assertForbidden();
        $this->postJson($url, array_replace_recursive($update, ['message' => ['text' => '/kontrol@AnotherBot']]), $headers)->assertNoContent();
        $this->postJson($url, array_replace_recursive($update, ['message' => ['chat' => ['id' => -666]]]), $headers)->assertNoContent();
        $this->postJson($url, $update, $headers)->assertNoContent();
        $this->postJson($url, $update, $headers)->assertNoContent();
        $this->assertSame(0, $chat->processIncomingCommands());
        $this->assertCount(1, Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/sendMessage')));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/getUpdates'));
    }

    public function test_imm_group_connection_reports_a_bot_outside_the_group_without_registering_a_webhook(): void
    {
        (require database_path('migrations/2026_11_24_110000_create_telegram_fleet_alert_tables.php'))->up();
        config(['app.url' => 'https://fleet.example/app']);
        $this->withoutMiddleware()->actingAs(new User(['company_id' => 1, 'role' => 'company_admin']));
        TelegramAlertBot::create(['company_id' => 1, 'channel' => 'imm', 'bot_token' => '124:demo', 'chat_id' => '-555']);
        Http::fake([
            '*/getMe' => Http::response(['ok' => true, 'result' => ['id' => 124, 'username' => 'CelebiImmBot']]),
            '*/getChatMember*' => Http::response(['ok' => true, 'result' => ['status' => 'left']]),
        ]);

        $this->postJson('/api/v1/telegram-kasko-settings/bots/imm/connect')->assertStatus(422)
            ->assertJsonPath('message', 'Telegram İMM botunun gruba üye olduğunu ve mesaj gönderebildiğini doğrulayamadı. Grup kimliğini ve botun grup üyeliğini kontrol edin.');
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/setWebhook'));
    }

    public function test_telegram_control_resumes_after_a_rate_limit_without_duplicating_vehicle_messages(): void
    {
        (require database_path('migrations/2026_11_24_110000_create_telegram_fleet_alert_tables.php'))->up();
        $this->travelTo(now('Europe/Istanbul')->setDate(2026, 11, 24)->setTime(10, 0));
        TelegramAlertBot::create(['company_id' => 1, 'channel' => 'imm', 'bot_token' => '124:demo', 'chat_id' => '-555']);
        $this->document($this->vehicle('IMM-FIRST'), 3, ['document_type' => 'İMM Poliçesi']);
        $this->document($this->vehicle('IMM-SECOND'), 4, ['document_type' => 'İMM Poliçesi']);
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake([
            '*/getUpdates*' => Http::response(['ok' => true, 'result' => [
                ['update_id' => 42, 'message' => ['text' => 'kontrol', 'chat' => ['id' => -555, 'type' => 'group']]],
            ]]),
            '*/sendMessage' => Http::sequence()
                ->push(['ok' => true, 'result' => ['message_id' => 1]])
                ->push(['ok' => false, 'error_code' => 429, 'parameters' => ['retry_after' => 32]], 429)
                ->push(['ok' => true, 'result' => ['message_id' => 2]]),
        ]);

        $service = app(TelegramImmChatService::class);
        $this->assertSame(0, $service->processIncomingCommands());
        $this->assertSame(0, $service->processIncomingCommands());
        $this->travel(60)->seconds();
        $this->assertSame(1, $service->processIncomingCommands());
        $this->assertSame(0, $service->processIncomingCommands());
        $texts = Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/sendMessage'))
            ->map(fn (array $pair) => $pair[0]['text'])->values()->all();
        $this->assertCount(3, $texts);
        $this->assertStringContainsString('IMM-FIRST', $texts[0]);
        $this->assertStringContainsString('IMM-SECOND', $texts[1]);
        $this->assertSame($texts[1], $texts[2]);
    }

    public function test_telegram_retry_after_blocks_repeat_requests_until_the_limit_expires(): void
    {
        (require database_path('migrations/2026_11_24_110000_create_telegram_fleet_alert_tables.php'))->up();
        $this->freezeTime();
        $this->withoutMiddleware()->actingAs(new User(['company_id' => 1, 'role' => 'company_admin']));
        TelegramAlertBot::create(['company_id' => 1, 'channel' => 'imm', 'bot_token' => '124:demo', 'chat_id' => '-555']);
        TelegramAlertBot::create(['company_id' => 1, 'channel' => 'insurance', 'bot_token' => '125:demo', 'chat_id' => '-556']);
        $this->document($this->vehicle('IMM-LIMIT'), 5, ['document_type' => 'İMM Poliçesi']);
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake([
            '*/bot124:demo/sendMessage' => Http::sequence()
                ->push(['ok' => false, 'error_code' => 429, 'parameters' => ['retry_after' => 32]], 429)
                ->push(['ok' => true, 'result' => ['message_id' => 1]]),
            '*/bot125:demo/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 2]]),
        ]);

        $url = '/api/v1/telegram-kasko-settings/bots/imm/test';
        $this->postJson($url)->assertStatus(429)->assertHeader('Retry-After', '32')->assertJsonPath('retry_after', 32);
        $this->travel(10)->seconds();
        $this->getJson('/api/v1/telegram-kasko-settings')->assertOk()->assertJsonPath('data.channels.imm.retry_after', 22);
        $this->postJson($url)->assertStatus(429)->assertHeader('Retry-After', '22');
        $this->from(route('telegram-kasko.index', ['channel' => 'imm']))
            ->post(route('telegram-kasko.bots.test', ['channel' => 'imm']))
            ->assertRedirect(route('telegram-kasko.index', ['channel' => 'imm']))
            ->assertSessionHas('telegram_rate_limited', true);
        Http::assertSentCount(1);

        $this->postJson('/api/v1/telegram-kasko-settings/bots/insurance/test')->assertOk();
        $this->travel(22)->seconds();
        $this->postJson($url)->assertOk();
        Http::assertSentCount(3);
    }

    public function test_fleet_test_recovers_with_ipv4_when_the_first_connection_fails_before_send(): void
    {
        (require database_path('migrations/2026_11_24_110000_create_telegram_fleet_alert_tables.php'))->up();
        $this->withoutMiddleware()->actingAs(new User(['company_id' => 1, 'role' => 'company_admin']));
        TelegramAlertBot::create(['company_id' => 1, 'channel' => 'imm', 'bot_token' => '124:demo', 'chat_id' => '-555']);
        $this->document($this->vehicle('IMM-CONNECTION'), 5, ['document_type' => 'İMM Poliçesi']);
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        $attempts = [];
        Http::fake(function (Request $request, array $options) use (&$attempts) {
            $attempts[] = $options['force_ip_resolve'] ?? null;
            if (count($attempts) === 1) {
                throw new ConnectException('Could not connect', $request->toPsrRequest(), null, ['errno' => 7]);
            }

            return Http::response(['ok' => true, 'result' => ['message_id' => 1]]);
        });

        $this->postJson('/api/v1/telegram-kasko-settings/bots/imm/test')->assertOk();
        $this->assertSame([null, 'v4'], $attempts);
    }

    public function test_fleet_test_reports_response_timeout_without_a_duplicate_send_or_token_leak(): void
    {
        (require database_path('migrations/2026_11_24_110000_create_telegram_fleet_alert_tables.php'))->up();
        $this->withoutMiddleware()->actingAs(new User(['company_id' => 1, 'role' => 'company_admin']));
        TelegramAlertBot::create(['company_id' => 1, 'channel' => 'imm', 'bot_token' => '124:demo', 'chat_id' => '-555']);
        $this->document($this->vehicle('IMM-TIMEOUT'), 5, ['document_type' => 'İMM Poliçesi']);
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        $attempts = 0;
        Http::fake(function (Request $request) use (&$attempts) {
            $attempts++;
            throw new ConnectException('Timeout for ' . $request->url(), $request->toPsrRequest(), null, [
                'errno' => 28, 'pretransfer_time' => 0.3, 'request_size' => 240, 'size_upload' => 80,
            ]);
        });

        $response = $this->postJson('/api/v1/telegram-kasko-settings/bots/imm/test')->assertStatus(503);
        $response->assertJsonPath('message', 'Telegram yanıtı zamanında gelmedi. Tekrar göndermeden önce mesajın gruba ulaşıp ulaşmadığına bakın. (cURL 28)');
        $response->assertDontSee('124:demo');
        $this->assertSame(1, $attempts);
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
