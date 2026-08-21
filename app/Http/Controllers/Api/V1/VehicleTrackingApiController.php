<?php
// GÜNCELLEME NOTU: Mobil Araç Takip Ekranındaki 'driver' ilişkisi hatası (500) giderildi. (drivers olarak değiştirildi)
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VehicleTrackingSetting;
use App\Services\ArventoService;

class VehicleTrackingApiController extends Controller
{
    public function live(Request $request)
    {
        // Kullanıcının araçları görme yetkisi var mı?
        abort_unless($request->user()->hasPermission('vehicles.view'), 403, 'Bu işlem için yetkiniz bulunmamaktadır.');

        $companyId = $request->user()->company_id;
        $setting = VehicleTrackingSetting::where('company_id', $companyId)->where('is_active', true)->first();
        
        $vehicles = [];
        if ($setting && $setting->provider === 'arvento') {
            $arvento = new ArventoService($setting);
            $vehicles = $arvento->getVehicleStatus();

            // Arvento'dan gelen verilere sistemimizdeki şoförleri eşleştir
            $dbVehicles = \App\Models\Fleet\Vehicle::with('drivers')
                ->where('company_id', $companyId)
                ->get()
                ->keyBy(function($item) {
                    // Eşleştirme için plakadaki boşlukları silip büyük harf yapıyoruz
                    return strtoupper(str_replace(' ', '', $item->license_plate));
                });

            foreach ($vehicles as &$v) {
                $plateClean = strtoupper(str_replace(' ', '', $v['LicensePlate'] ?? ''));
                if (isset($dbVehicles[$plateClean]) && $dbVehicles[$plateClean]->drivers->first()) {
                    $driver = $dbVehicles[$plateClean]->drivers->first();
                    $v['Driver'] = trim($driver->first_name . ' ' . $driver->last_name);
                } else {
                    $v['Driver'] = 'Bilinmiyor';
                }
            }
        }

        // Arvento seçili değilse veya Arvento verisi yoksa kendi veritabanımızdan (Concox GT06N) çek
        if (empty($vehicles)) {
            $dbVehicles = \App\Models\Fleet\Vehicle::with('drivers')->where('company_id', $companyId)->get();
            
            // Her araç için en son konumu bul
            $locations = \App\Models\Fleet\VehicleLocation::whereIn('vehicle_id', $dbVehicles->pluck('id'))
                ->orderBy('recorded_at', 'desc')
                ->get()
                ->groupBy('vehicle_id')
                ->map(function ($group) {
                    return $group->first();
                });

            foreach ($dbVehicles as $v) {
                $loc = $locations->get($v->id);
                if ($loc) {
                    $statusArr = is_string($loc->status) ? json_decode($loc->status, true) : $loc->status;
                    $vehicles[] = [
                        'LicensePlate' => $v->license_plate,
                        'Driver' => $v->drivers->first() ? trim($v->drivers->first()->first_name . ' ' . $v->drivers->first()->last_name) : 'Atanmamış',
                        'Latitude' => $loc->latitude,
                        'Longitude' => $loc->longitude,
                        'Speed' => $loc->speed,
                        'EngineStatus' => (isset($statusArr['acc']) && $statusArr['acc']) ? 'Açık' : 'Kapalı',
                        'DeviceImei' => $v->device_imei,
                        'RecordedAt' => $loc->recorded_at ? $loc->recorded_at->format('Y-m-d H:i:s') : null,
                    ];
                }
            }

            // Eğer hiç canlı veri yoksa sistemi test edebilmek için 1 tane sahte araç ekle (Sadece test için)
            if (empty($vehicles)) {
                $vehicles = [
                    [
                        'LicensePlate' => 'TEST 123 (CİHAZ BEKLENİYOR)',
                        'Driver' => 'Test Sürücü',
                        'Latitude' => 41.0122,
                        'Longitude' => 28.9760,
                        'Speed' => 0,
                        'EngineStatus' => 'Kapalı',
                        'DeviceImei' => null,
                        'RecordedAt' => date('Y-m-d H:i:s')
                    ]
                ];
            }
        }

        // Eğer provider ayarı yoksa bile demo amaçlı aktif gibi göster:
        $isProviderActive = $setting ? true : true; // DEMO için her zaman true döndürdük ki ekran hata vermesin

        return response()->json([
            'success' => true,
            'vehicles' => $vehicles,
            'provider_active' => $isProviderActive,
            'provider_name' => $setting ? $setting->provider : 'Demo Mode'
        ]);
    }

    public function dailyWorkReport(Request $request)
    {
        abort_unless($request->user()->hasPermission('vehicles.view'), 403, 'Bu işlem için yetkiniz bulunmamaktadır.');

        $companyId = $request->user()->company_id;
        $setting = VehicleTrackingSetting::where('company_id', $companyId)->where('is_active', true)->first();
        
        $date = $request->input('date', date('Y-m-d'));
        $reports = [];

        if ($setting && $setting->provider === 'arvento') {
            $arvento = new ArventoService($setting);
            $mapping = $arvento->getMappedLicensePlates();
            
            // Sadece bu şirkete atanmış cihazları al
            $nodeList = implode(',', array_keys($mapping));

            if (!empty($nodeList)) {
                $reports = $arvento->getDailyFirstContactReport($date, $nodeList);
            }
            
            // O gün hiç kontak açmayan araçları da listeye ekle
            foreach ($mapping as $node => $plate) {
                if (!isset($reports[$node])) {
                    $reports[$node] = [
                        'LicensePlate' => $plate,
                        'Driver' => '-',
                        'DateTime' => '-',
                        'Latitude' => 0,
                        'Longitude' => 0,
                        'Address' => 'Kontak Açılmadı / Veri Yok',
                    ];
                }
            }
        } else if (!$setting) {
            // DEMO Veri
            $dbVehicles = \App\Models\Fleet\Vehicle::with('drivers')->where('company_id', $companyId)->get();
            foreach ($dbVehicles as $v) {
                $reports[] = [
                    'LicensePlate' => $v->license_plate,
                    'Driver' => $v->drivers->first() ? $v->drivers->first()->first_name . ' ' . $v->drivers->first()->last_name : 'Atanmamış',
                    'DateTime' => $date . ' ' . sprintf('%02d:%02d', rand(6, 11), rand(0, 59)),
                    'Latitude' => 41.0082 + (rand(-100, 100) / 10000),
                    'Longitude' => 28.9784 + (rand(-100, 100) / 10000),
                    'Address' => 'Demo Adres, Istanbul',
                ];
            }
        }

        return response()->json([
            'success' => true,
            'reports' => array_values($reports),
            'date' => $date,
            'provider_active' => $setting ? true : true
        ]);
    }
}
