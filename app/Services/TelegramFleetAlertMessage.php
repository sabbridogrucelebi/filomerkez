<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Fleet\Vehicle;
use App\Models\TelegramAlertBot;
use Carbon\Carbon;

final class TelegramFleetAlertMessage
{
    public const MAX_TEMPLATE_LENGTH = 3000;
    private const OLD_IMM_LEAD = 'Aracınızın ihtiyari mali mesuliyet poliçesinin bitişi yaklaşıyor.';
    private const IMM_LEAD = 'Aşağıda aracınızın ihtiyari mali mesuliyet poliçesinin güncel durumu yer alıyor.';
    private const START_LINE = '{{policy_start_line}}';

    private const VEHICLE_FIELDS = ['company_name', 'plate', 'brand_model', 'model_year', 'license_serial_no', 'chassis_no', 'license_owner', 'owner_tax_or_tc_no'];

    public static function fields(string $kind, ?string $channel = null): array
    {
        $fields = array_merge(self::VEHICLE_FIELDS, match ($kind) {
            'date' => ['end_date', 'days_left', 'expiry_status'],
            'maintenance' => ['maintenance_type', 'maintenance_status', 'current_km', 'next_service_date'],
            'fuel' => ['fuel_date', 'distance_km', 'liters', 'measured_km_per_liter', 'minimum_km_per_liter'],
        });

        if ($channel === 'imm') {
            $fields[] = 'policy_start_line';
        }

        return $fields;
    }

    public static function dateEvent(Carbon $end, int $days, ?Document $policy = null): array
    {
        return [
            'end_date' => $end->format('d.m.Y'),
            'start_date' => $policy?->start_date?->format('d.m.Y'),
            'days_left' => abs($days),
            'expired' => $days < 0,
            'expiry_status' => match (true) {
                $days < 0 => abs($days) . ' gün önce sona erdi',
                $days === 0 => 'Bugün sona eriyor',
                default => "{$days} gün kaldı",
            },
        ];
    }

    public static function defaultTemplate(string $channel): string
    {
        [$heading, $lead, $action] = match ($channel) {
            'imm' => ['☂️ İMM POLİÇESİ YENİLEME UYARISI', self::OLD_IMM_LEAD, 'Teminatın sürmesi için yeni poliçeyi düzenletip Filo Merkez’e yükleyin.'],
            'insurance' => ['🛡️ TRAFİK SİGORTASI YENİLEME UYARISI', 'Aracınızın zorunlu trafik sigortasının bitişi yaklaşıyor.', 'Poliçenizi zamanında yenileyip Filo Merkez’e yükleyin.'],
            'inspection' => ['🔍 ARAÇ MUAYENESİ HATIRLATMASI', 'Aracınızın muayene geçerlilik süresi sona ermek üzere.', 'Muayene randevusunu planlayıp yeni belgeyi Filo Merkez’e yükleyin.'],
            'exhaust' => ['💨 EGZOZ EMİSYON ÖLÇÜMÜ HATIRLATMASI', 'Aracınızın egzoz emisyon ölçümünün geçerlilik süresi sona ermek üzere.', 'Yeni ölçümü planlayıp sonuç belgesini Filo Merkez’e yükleyin.'],
            'maintenance' => ['🛠️ ARAÇ BAKIM UYARISI', 'Aşağıdaki araç için bakım planını kontrol edin.', 'Servis randevusunu planlayıp tamamlanan bakımı Filo Merkez’e kaydedin.'],
            'fuel' => ['⛽ YÜKSEK YAKIT TÜKETİMİ UYARISI', 'Son yakıt alımında ölçülen verim, aracın kayıtlı alt sınırının altında kaldı.', 'Yakıt ve kilometre kayıtlarını kontrol edin. Ölçüm doğrulanırsa tüketimi inceleyin.'],
        };

        $details = match (TelegramAlertBot::CHANNELS[$channel]['kind']) {
            'date' => "📅 <b>Geçerlilik sonu:</b> {{end_date}}\n⏳ <b>{{expiry_status}}</b>",
            'maintenance' => "🔧 <b>Bakım türü:</b> {{maintenance_type}}\n📍 <b>Güncel km:</b> {{current_km}}\n🗓️ <b>Planlanan tarih:</b> {{next_service_date}}\n⚠️ <b>{{maintenance_status}}</b>",
            'fuel' => "📅 <b>Yakıt alımı:</b> {{fuel_date}}\n🛣️ <b>Mesafe:</b> {{distance_km}} km · <b>Alım:</b> {{liters}} L\n📉 <b>Ölçülen:</b> {{measured_km_per_liter}} km/L\n🎯 <b>Araç alt sınırı:</b> {{minimum_km_per_liter}} km/L",
        };
        if ($channel === 'imm') {
            $details = self::START_LINE . "\n" . $details;
        }

        return <<<TEXT
<b>{$heading}</b>

Sayın <b>{{company_name}}</b> yetkilisi,
{$lead}

🚘 <b>Plaka:</b> {{plate}}
🏷️ <b>Marka / Model:</b> {{brand_model}} · {{model_year}}
🔢 <b>Seri No:</b> {{license_serial_no}}
🧾 <b>Şase No:</b> {{chassis_no}}
👤 <b>Ruhsat sahibi:</b> {{license_owner}}
🏢 <b>Vergi / T.C. No:</b> {{owner_tax_or_tc_no}}

{$details}

📌 {$action}
<i>Filo Merkez | Filo Takip Sistemleri</i>
TEXT;
    }

    public function render(TelegramAlertBot $bot, Vehicle $vehicle, array $event): string
    {
        $data = [
            'company_name' => $bot->company?->name ?: 'Filo Merkez',
            'plate' => $vehicle->plate,
            'brand_model' => trim(($vehicle->brand ?? '') . ' ' . ($vehicle->model ?? '')),
            'model_year' => $vehicle->model_year,
            'license_serial_no' => $vehicle->license_serial_no,
            'chassis_no' => $vehicle->chassis_no,
            'license_owner' => $vehicle->license_owner,
            'owner_tax_or_tc_no' => $vehicle->owner_tax_or_tc_no,
        ] + $event;

        $values = [];
        foreach (self::fields(TelegramAlertBot::CHANNELS[$bot->channel]['kind']) as $field) {
            $value = $data[$field] ?? null;
            $values['{{' . $field . '}}'] = filled($value) ? e($value) : '—';
        }

        $template = $bot->message_template ?: self::defaultTemplate($bot->channel);
        if ($bot->channel === 'imm') {
            $template = $this->formatImmTemplate($template, $event);
        }

        return strtr($template, $values);
    }

    private function formatImmTemplate(string $template, array $event): string
    {
        $expired = $event['expired'] ?? false;
        $template = $expired ? str_replace(self::OLD_IMM_LEAD, self::IMM_LEAD, $template)
            : str_replace(self::IMM_LEAD, self::OLD_IMM_LEAD, $template);
        $start = $event['start_date'] ?? null;
        if (!$start) {
            return str_replace([self::START_LINE . "\r\n", self::START_LINE . "\n", self::START_LINE], '', $template);
        }

        $line = '📅 <b>Poliçe başlangıcı:</b> ' . e($start);
        if (str_contains($template, self::START_LINE)) {
            return str_replace(self::START_LINE, $line, $template);
        }

        // Preserve saved templates and place the optional date just above the expiry line.
        $expiry = strpos($template, '{{end_date}}');
        if ($expiry !== false) {
            $lineStart = strrpos(substr($template, 0, $expiry), "\n");
            return substr_replace($template, $line . "\n", $lineStart === false ? 0 : $lineStart + 1, 0);
        }

        return rtrim($template) . "\n\n" . $line;
    }
}
