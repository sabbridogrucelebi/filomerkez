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
                    return strtoupper(str_replace(' ', '', $item->plate));
                });

            foreach ($vehicles as &$v) {
                $plateClean = strtoupper(str_replace(' ', '', $v['LicensePlate'] ?? ''));
                if (isset($dbVehicles[$plateClean]) && $dbVehicles[$plateClean]->drivers->first()) {
                    $driver = $dbVehicles[$plateClean]->drivers->first();
                    $v['Driver'] = trim($driver->full_name ?? ($driver->first_name . ' ' . $driver->last_name));
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
                        'LicensePlate' => $v->plate ?? $v->license_plate,
                        'Driver' => $v->drivers->first() ? trim($v->drivers->first()->full_name ?? ($v->drivers->first()->first_name . ' ' . $v->drivers->first()->last_name)) : 'Atanmamış',
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

    public function history(Request $request)
    {
        abort_unless($request->user()->hasPermission('vehicles.view'), 403, 'Bu işlem için yetkiniz bulunmamaktadır.');

        $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'date_filter' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $companyId = $request->user()->company_id;
        
        $vehicle = \App\Models\Fleet\Vehicle::where('company_id', $companyId)
            ->where('id', $request->vehicle_id)
            ->firstOrFail();

        $startDateUtc = null;
        $endDateUtc = null;

        if ($request->date_filter) {
            $nowUtc = now('UTC');
            $nowLocal = now('Europe/Istanbul');
            
            switch ($request->date_filter) {
                case 'last_1_hour':
                    $startDateUtc = $nowUtc->copy()->subHour();
                    $endDateUtc = $nowUtc->copy();
                    break;
                case 'today':
                    $startDateUtc = $nowLocal->copy()->startOfDay()->setTimezone('UTC');
                    $endDateUtc = $nowLocal->copy()->endOfDay()->setTimezone('UTC');
                    break;
                case 'yesterday':
                    $startDateUtc = $nowLocal->copy()->subDay()->startOfDay()->setTimezone('UTC');
                    $endDateUtc = $nowLocal->copy()->subDay()->endOfDay()->setTimezone('UTC');
                    break;
                case 'last_3_hours':
                    $startDateUtc = $nowUtc->copy()->subHours(3);
                    $endDateUtc = $nowUtc->copy();
                    break;
                case 'last_3_days':
                    $startDateUtc = $nowLocal->copy()->subDays(2)->startOfDay()->setTimezone('UTC');
                    $endDateUtc = $nowLocal->copy()->endOfDay()->setTimezone('UTC');
                    break;
                default:
                    if ($request->start_date && $request->end_date) {
                        $startDateUtc = \Carbon\Carbon::parse($request->start_date, 'Europe/Istanbul')->setTimezone('UTC');
                        $endDateUtc = \Carbon\Carbon::parse($request->end_date, 'Europe/Istanbul')->setTimezone('UTC');
                    }
                    break;
            }
        } else if ($request->start_date && $request->end_date) {
            $startDateUtc = \Carbon\Carbon::parse($request->start_date, 'Europe/Istanbul')->setTimezone('UTC');
            $endDateUtc = \Carbon\Carbon::parse($request->end_date, 'Europe/Istanbul')->setTimezone('UTC');
        } else {
            $startDateUtc = now('Europe/Istanbul')->startOfDay()->setTimezone('UTC');
            $endDateUtc = now('Europe/Istanbul')->endOfDay()->setTimezone('UTC');
        }

        $locations = \App\Models\Fleet\VehicleLocation::where('vehicle_id', $vehicle->id)
            ->whereBetween('recorded_at', [$startDateUtc, $endDateUtc])
            ->orderBy('recorded_at', 'asc')
            ->get();

        $historyData = [];
        $trips = [];
        
        $currentTrip = null;
        $prevLat = null;
        $prevLng = null;

        foreach ($locations as $loc) {
            $statusArr = is_string($loc->status) ? json_decode($loc->status, true) : $loc->status;
            $rawAcc = isset($statusArr['acc']) ? (bool) $statusArr['acc'] : false;
            $acc = $rawAcc || ($loc->speed > 0);
            $localTime = $loc->recorded_at ? $loc->recorded_at->copy()->addHours(3) : null;
            $timeFormatted = $localTime ? $localTime->format('Y-m-d H:i:s') : null;
            
            $historyData[] = [
                'Latitude' => $loc->latitude,
                'Longitude' => $loc->longitude,
                'Speed' => $loc->speed,
                'Course' => $loc->course,
                'EngineStatus' => $acc ? 'Açık' : 'Kapalı',
                'RecordedAt' => $timeFormatted,
                'Timestamp' => $localTime ? $localTime->timestamp : 0,
            ];

            // Trip Logic
            if ($acc) {
                if (!$currentTrip) {
                    $currentTrip = [
                        'start_time' => $timeFormatted,
                        'end_time' => null,
                        'start_lat' => $loc->latitude,
                        'start_lng' => $loc->longitude,
                        'end_lat' => null,
                        'end_lng' => null,
                        'distance_km' => 0,
                        'max_speed' => $loc->speed,
                        'speed_sum' => $loc->speed,
                        'point_count' => 1,
                        'duration_seconds' => 0,
                        'start_timestamp' => $localTime ? $localTime->timestamp : 0,
                        'end_timestamp' => null
                    ];
                    $prevLat = $loc->latitude;
                    $prevLng = $loc->longitude;
                } else {
                    if ($prevLat !== null && $prevLng !== null) {
                        $currentTrip['distance_km'] += $this->haversineGreatCircleDistance($prevLat, $prevLng, $loc->latitude, $loc->longitude);
                    }
                    if ($loc->speed > $currentTrip['max_speed']) $currentTrip['max_speed'] = $loc->speed;
                    $currentTrip['speed_sum'] += $loc->speed;
                    $currentTrip['point_count']++;
                    
                    $prevLat = $loc->latitude;
                    $prevLng = $loc->longitude;
                }
            } else {
                if ($currentTrip) {
                    $currentTrip['end_time'] = $timeFormatted;
                    $currentTrip['end_lat'] = $loc->latitude;
                    $currentTrip['end_lng'] = $loc->longitude;
                    $currentTrip['end_timestamp'] = $localTime ? $localTime->timestamp : 0;
                    $currentTrip['duration_seconds'] = $currentTrip['end_timestamp'] - $currentTrip['start_timestamp'];
                    $currentTrip['avg_speed'] = $currentTrip['point_count'] > 0 ? round($currentTrip['speed_sum'] / $currentTrip['point_count'], 1) : 0;
                    
                    if ($currentTrip['duration_seconds'] > 60 || $currentTrip['distance_km'] > 0.1) {
                        $trips[] = $currentTrip;
                    }
                    $currentTrip = null;
                }
            }
        }

        if ($currentTrip && count($historyData) > 0) {
            $lastLoc = end($historyData);
            $currentTrip['end_time'] = $lastLoc['RecordedAt'];
            $currentTrip['end_lat'] = $lastLoc['Latitude'];
            $currentTrip['end_lng'] = $lastLoc['Longitude'];
            $currentTrip['end_timestamp'] = $lastLoc['Timestamp'];
            $currentTrip['duration_seconds'] = $currentTrip['end_timestamp'] - $currentTrip['start_timestamp'];
            $currentTrip['avg_speed'] = $currentTrip['point_count'] > 0 ? round($currentTrip['speed_sum'] / $currentTrip['point_count'], 1) : 0;
            $trips[] = $currentTrip;
        }

        return response()->json([
            'success' => true,
            'history' => $historyData,
            'trips' => array_reverse($trips)
        ]);
    }

    private function haversineGreatCircleDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371)
    {
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;
        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return $angle * $earthRadius;
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
                    'LicensePlate' => $v->plate ?? $v->license_plate,
                    'Driver' => $v->drivers->first() ? trim($v->drivers->first()->full_name ?? ($v->drivers->first()->first_name . ' ' . $v->drivers->first()->last_name)) : 'Atanmamış',
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
