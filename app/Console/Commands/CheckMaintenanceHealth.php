<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Fleet\Vehicle;
use App\Models\User;
use App\Jobs\SendFcmpushNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CheckMaintenanceHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'maintenance:check-health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks vehicle maintenance health and sends push notifications if within threshold.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking maintenance health...');

        // Tüm araçları ve ayarlarını al
        $vehicles = Vehicle::with(['maintenanceSetting', 'company'])->get();

        foreach ($vehicles as $vehicle) {
            $status = $vehicle->maintenance_status;
            $setting = $vehicle->maintenanceSetting;

            if (!$setting || !$status) continue;

            // Yağ Bakımı Kontrolü
            if ($status['has_oil_setting'] && $status['oil_remaining'] !== null && $status['oil_remaining'] <= 200) {
                // Sadece mevcut KM, son bildirilen KM'den farklı veya bildirim hiç atılmamışsa gönder (spamı önlemek için)
                // Daha iyi bir yaklaşım: Sadece KM daha da düştüyse (örneğin her 50 km'de bir) veya hiç atılmadıysa
                $lastNotifiedKm = $setting->oil_last_notified_km;
                $currentKm = clone $status['current_km']; // Assuming int

                if ($lastNotifiedKm === null || ($lastNotifiedKm - $currentKm >= 50) || $status['oil_remaining'] < 0) {
                    $this->sendNotificationToAdmins(
                        $vehicle->company_id,
                        '⚠️ Yağ Bakımı Uyarısı',
                        "{$vehicle->plate} plakalı aracın Yağ Bakımına {$status['oil_remaining']} KM kaldı!"
                    );
                    $setting->oil_last_notified_km = $currentKm;
                    $setting->save();
                }
            }

            // Alt Yağlama Kontrolü
            if ($status['has_lube_setting'] && $status['lube_remaining'] !== null && $status['lube_remaining'] <= 200) {
                $lastNotifiedKm = $setting->lube_last_notified_km;
                $currentKm = clone $status['current_km'];

                if ($lastNotifiedKm === null || ($lastNotifiedKm - $currentKm >= 50) || $status['lube_remaining'] < 0) {
                    $this->sendNotificationToAdmins(
                        $vehicle->company_id,
                        '⚠️ Alt Yağlama Uyarısı',
                        "{$vehicle->plate} plakalı aracın Alt Yağlamasına {$status['lube_remaining']} KM kaldı!"
                    );
                    $setting->lube_last_notified_km = $currentKm;
                    $setting->save();
                }
            }
        }

        $this->info('Maintenance health check completed.');
    }

    private function sendNotificationToAdmins($companyId, $title, $message)
    {
        // İlgili şirketteki süper admin ve adminleri bul (hasPermission veya role)
        // Varsayalım ki role = company_admin olanlara gidiyor
        $admins = User::where('company_id', $companyId)
                      ->where('role', 'company_admin')
                      ->whereNotNull('expo_push_token')
                      ->get();

        foreach ($admins as $admin) {
            SendFcmpushNotification::dispatch(
                $admin,
                $title,
                $message,
                ['type' => 'maintenance_alert']
            );
        }
    }
}
