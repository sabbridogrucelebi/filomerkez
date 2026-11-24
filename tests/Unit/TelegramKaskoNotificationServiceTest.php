<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Fleet\Vehicle;
use App\Models\TelegramKaskoNotificationSetting;
use App\Services\TelegramKaskoNotificationService;
use Tests\TestCase;

class TelegramKaskoNotificationServiceTest extends TestCase
{
    public function test_it_renders_the_premium_kasko_message_with_vehicle_details(): void
    {
        $setting = new TelegramKaskoNotificationSetting([
            'message_template' => TelegramKaskoNotificationSetting::defaultMessageTemplate(),
        ]);
        $setting->setRelation('company', new Company(['name' => 'Çelebi Turizm']));

        $vehicle = new Vehicle([
            'plate' => '34 ABC 123',
            'brand' => 'Mercedes',
            'model' => 'Sprinter',
            'model_year' => 2023,
            'license_serial_no' => 'SERİ-001',
            'chassis_no' => 'ŞASE-001',
            'license_owner' => 'Mehmet Taşımacılık Tic. Ltd. Şti.',
            'owner_tax_or_tc_no' => '6130942149',
            'kasko_end_date' => '2026-12-03',
        ]);

        $message = (new TelegramKaskoNotificationService())->renderMessage($setting, $vehicle, 10);

        $this->assertStringContainsString('Çelebi Turizm', $message);
        $this->assertStringContainsString('10 GÜN', $message);
        $this->assertStringContainsString('34 ABC 123', $message);
        $this->assertStringContainsString('Mercedes Sprinter', $message);
        $this->assertStringContainsString('2023', $message);
        $this->assertStringContainsString('SERİ-001', $message);
        $this->assertStringContainsString('ŞASE-001', $message);
        $this->assertStringContainsString('Mehmet Taşımacılık Tic. Ltd. Şti.', $message);
        $this->assertStringContainsString('6130942149', $message);
        $this->assertStringContainsString('Filo Merkez | Filo Takip Sistemleri', $message);
    }

    public function test_it_describes_an_expired_kasko_correctly(): void
    {
        $setting = new TelegramKaskoNotificationSetting([
            'message_template' => TelegramKaskoNotificationSetting::defaultMessageTemplate(),
        ]);
        $vehicle = new Vehicle(['plate' => '34 ABC 123', 'kasko_end_date' => '2026-11-20']);

        $message = (new TelegramKaskoNotificationService())->renderMessage($setting, $vehicle, -3);

        $this->assertStringContainsString('3 GÜN</b> önce sona erdi', $message);
    }

    public function test_it_adds_ownership_details_to_an_existing_custom_template(): void
    {
        $setting = new TelegramKaskoNotificationSetting([
            'message_template' => "🚘 {{plate}}\n<i>Filo Merkez</i>",
        ]);
        $vehicle = new Vehicle([
            'plate' => '42 C 0681',
            'license_owner' => 'Mehmet Taşımacılık Tic. Ltd. Şti.',
            'owner_tax_or_tc_no' => '6130942149',
            'kasko_end_date' => '2026-12-03',
        ]);

        $message = (new TelegramKaskoNotificationService())->renderMessage($setting, $vehicle, 10);

        $this->assertStringContainsString('Ruhsat Sahibi:</b> Mehmet Taşımacılık Tic. Ltd. Şti.', $message);
        $this->assertStringContainsString('Vergi / T.C. No:</b> 6130942149', $message);
    }
}
