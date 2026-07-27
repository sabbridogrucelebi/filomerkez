<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Fleet\Vehicle;
use App\Models\User;
use App\Jobs\SendFcmpushNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

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
            
            // Muayene Kontrolü (10 Gün)
            $docTypes = ['Muayene', 'Egzoz', 'Sigorta', 'Kasko', 'İMM Poliçesi', 'İMM POLİÇESİ'];
            $latestDocs = $vehicle->documents()
                ->whereIn('document_type', $docTypes)
                ->whereNotNull('end_date')
                ->whereNull('archived_at')
                ->get()
                ->groupBy('document_type')
                ->map(fn($g) => $g->sortByDesc('end_date')->first());

            $inspectionDoc = $latestDocs->get('Muayene');
            $inspectionDate = $inspectionDoc?->end_date ?? $vehicle->inspection_date;
            
            if ($inspectionDate) {
                $inspectionDays = round(now()->diffInDays($inspectionDate, false));
                if ($inspectionDays <= 10) {
                    $cacheKey = 'inspection_notified_' . $vehicle->id . '_' . now()->format('Y-m-d');
                    if (!Cache::has($cacheKey)) {
                        $this->sendNotificationToAdmins(
                            $vehicle->company_id,
                            '⚠️ Muayene Yaklaşıyor',
                            "{$vehicle->plate} plakalı aracın muayenesine " . ($inspectionDays < 0 ? abs($inspectionDays) . " gün geçti!" : $inspectionDays . " gün kaldı!")
                        );
                        Cache::put($cacheKey, true, now()->endOfDay());
                    }
                }
            }

            // Sigorta Kontrolü (10 Gün)
            $insuranceDoc = $latestDocs->get('Sigorta');
            $insuranceDate = $insuranceDoc?->end_date ?? $vehicle->insurance_end_date;

            if ($insuranceDate) {
                $insuranceDays = round(now()->diffInDays($insuranceDate, false));
                if ($insuranceDays <= 10) {
                    $cacheKey = 'insurance_notified_' . $vehicle->id . '_' . now()->format('Y-m-d');
                    if (!Cache::has($cacheKey)) {
                        $this->sendNotificationToAdmins(
                            $vehicle->company_id,
                            '⚠️ Sigorta Yaklaşıyor',
                            "{$vehicle->plate} plakalı aracın sigorta bitişine " . ($insuranceDays < 0 ? abs($insuranceDays) . " gün geçti!" : $insuranceDays . " gün kaldı!")
                        );
                        Cache::put($cacheKey, true, now()->endOfDay());
                    }
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
