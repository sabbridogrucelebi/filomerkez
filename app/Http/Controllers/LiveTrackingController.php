<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Fleet\Vehicle;
use App\Models\Fleet\VehicleLocation;
use App\Models\VehicleAlarm;
use App\Models\Geofence;
use App\Models\WorkSchedule;

class LiveTrackingController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->hasPermission('vehicle_tracking.view'), 403);

        $companyId = auth()->user()->company_id;
        $vehicles = Vehicle::where('company_id', $companyId)->get();

        return view('live-tracking.index', compact('vehicles'));
    }

    public function liveData()
    {
        abort_unless(auth()->user()->hasPermission('vehicle_tracking.view'), 403);

        $companyId = auth()->user()->company_id;
        
        $vehicles = Vehicle::where('company_id', $companyId)
            ->whereNotNull('device_imei')
            ->where('device_imei', '!=', '')
            ->get();

        $liveData = [];
        foreach ($vehicles as $vehicle) {
            $lastLocation = VehicleLocation::where('vehicle_id', $vehicle->id)
                ->orderBy('recorded_at', 'desc')
                ->first();

            if ($lastLocation) {
                $acc = isset($lastLocation->status['acc']) ? (bool) $lastLocation->status['acc'] : false;
                
                // Veritabanındaki UTC saate 3 saat ekleyerek Türkiye saatini bul
                $localTime = $lastLocation->recorded_at ? $lastLocation->recorded_at->copy()->addHours(3) : null;
                
                // Günlük KM ve Maks Hız Hesaplama (Cache ile 60 saniye)
                $stats = \Illuminate\Support\Facades\Cache::remember('vehicle_daily_stats_'.$vehicle->id, 60, function() use ($vehicle) {
                    $todayStart = \Carbon\Carbon::now()->addHours(3)->startOfDay()->subHours(3);
                    $locations = \App\Models\Fleet\VehicleLocation::where('vehicle_id', $vehicle->id)
                        ->where('recorded_at', '>=', $todayStart)
                        ->orderBy('recorded_at', 'asc')
                        ->get(['latitude', 'longitude', 'speed']);
                        
                    $distance = 0;
                    $maxSpeed = 0;
                    $prevLat = null;
                    $prevLng = null;
                    
                    foreach ($locations as $loc) {
                        if ($loc->speed > $maxSpeed) $maxSpeed = $loc->speed;
                        
                        if ($prevLat !== null && $prevLng !== null) {
                            $latFrom = deg2rad($prevLat);
                            $lonFrom = deg2rad($prevLng);
                            $latTo = deg2rad($loc->latitude);
                            $lonTo = deg2rad($loc->longitude);
                            $latDelta = $latTo - $latFrom;
                            $lonDelta = $lonTo - $lonFrom;
                            $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
                            $distance += $angle * 6371;
                        }
                        $prevLat = $loc->latitude;
                        $prevLng = $loc->longitude;
                    }
                    
                    return [
                        'DailyDistance' => round($distance, 1),
                        'MaxSpeed' => round($maxSpeed, 1)
                    ];
                });
                
                $liveData[] = [
                    'Node' => $vehicle->id,
                    'LicensePlate' => $vehicle->plate,
                    'Latitude' => $lastLocation->latitude,
                    'Longitude' => $lastLocation->longitude,
                    'Speed' => $lastLocation->speed,
                    'Course' => $lastLocation->course,
                    'ACC' => $acc,
                    'Datetime' => $localTime ? $localTime->format('d.m.Y H:i:s') : null,
                    'Address' => 'Konum: ' . $lastLocation->latitude . ', ' . $lastLocation->longitude,
                    'DailyDistance' => $stats['DailyDistance'],
                    'MaxSpeed' => $stats['MaxSpeed'],
                    'Voltage' => isset($lastLocation->status['voltage']) ? (float)$lastLocation->status['voltage'] : null,
                ];
            } else {
                $liveData[] = [
                    'Node' => $vehicle->id,
                    'LicensePlate' => $vehicle->plate,
                    'Latitude' => null,
                    'Longitude' => null,
                    'Speed' => 0,
                    'Course' => 0,
                    'ACC' => false,
                    'Datetime' => null,
                    'Address' => 'Cihazdan veri bekleniyor...',
                    'DailyDistance' => 0,
                    'MaxSpeed' => 0,
                ];
            }
        }

        return response()->json(['vehicles' => $liveData]);
    }

    public function historyData(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('vehicle_tracking.view'), 403);

        $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'date_filter' => 'nullable|string', // e.g. today, yesterday, last_1_hour
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $companyId = auth()->user()->company_id;
        
        $vehicle = Vehicle::where('company_id', $companyId)
            ->where('id', $request->vehicle_id)
            ->firstOrFail();

        $startDateUtc = null;
        $endDateUtc = null;

        if ($request->date_filter) {
            // Sunucu saati ne olursa olsun, şuanın tam UTC karşılığını alalım
            $nowUtc = now('UTC');
            $nowLocal = now('Europe/Istanbul'); // Veya $nowUtc->copy()->addHours(3);
            
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
                        // Gelen custom date 'Europe/Istanbul' olarak parse edilip UTC'ye çevriliyor
                        $startDateUtc = \Carbon\Carbon::parse($request->start_date, 'Europe/Istanbul')->setTimezone('UTC');
                        $endDateUtc = \Carbon\Carbon::parse($request->end_date, 'Europe/Istanbul')->setTimezone('UTC');
                    }
                    break;
            }
        } else if ($request->start_date && $request->end_date) {
            $startDateUtc = \Carbon\Carbon::parse($request->start_date, 'Europe/Istanbul')->setTimezone('UTC');
            $endDateUtc = \Carbon\Carbon::parse($request->end_date, 'Europe/Istanbul')->setTimezone('UTC');
        } else {
            // Default to today
            $startDateUtc = now('Europe/Istanbul')->startOfDay()->setTimezone('UTC');
            $endDateUtc = now('Europe/Istanbul')->endOfDay()->setTimezone('UTC');
        }

        $locations = VehicleLocation::where('vehicle_id', $vehicle->id)
            ->whereBetween('recorded_at', [$startDateUtc, $endDateUtc])
            ->orderBy('recorded_at', 'asc')
            ->get();

        $historyData = [];
        $trips = [];
        
        $currentTrip = null;
        $prevLat = null;
        $prevLng = null;

        foreach ($locations as $loc) {
            $rawAcc = isset($loc->status['acc']) ? (bool) $loc->status['acc'] : false;
            // Eğer cihazdan ACC bilgisi gelmiyorsa ama hız sıfırdan büyükse aracı kontak açık say!
            $acc = $rawAcc || ($loc->speed > 0);
            
            $localTime = $loc->recorded_at ? $loc->recorded_at->copy()->addHours(3) : null;
            $timeFormatted = $localTime ? $localTime->format('d.m.Y H:i:s') : null;
            
            $historyData[] = [
                'lat' => $loc->latitude,
                'lng' => $loc->longitude,
                'speed' => $loc->speed,
                'course' => $loc->course,
                'acc' => $acc,
                'time' => $timeFormatted,
                'timestamp' => $localTime ? $localTime->timestamp : 0,
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
                    // Calculate distance using Haversine
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
                    // Close the trip
                    $currentTrip['end_time'] = $timeFormatted;
                    $currentTrip['end_lat'] = $loc->latitude;
                    $currentTrip['end_lng'] = $loc->longitude;
                    $currentTrip['end_timestamp'] = $localTime ? $localTime->timestamp : 0;
                    $currentTrip['duration_seconds'] = $currentTrip['end_timestamp'] - $currentTrip['start_timestamp'];
                    $currentTrip['avg_speed'] = $currentTrip['point_count'] > 0 ? round($currentTrip['speed_sum'] / $currentTrip['point_count'], 1) : 0;
                    
                    // Only save trips longer than 1 minute or with some distance
                    if ($currentTrip['duration_seconds'] > 60 || $currentTrip['distance_km'] > 0.1) {
                        $trips[] = $currentTrip;
                    }
                    $currentTrip = null;
                }
            }
        }

        // Close any ongoing trip at the end of the query range
        if ($currentTrip && count($historyData) > 0) {
            $lastLoc = end($historyData);
            $currentTrip['end_time'] = $lastLoc['time'];
            $currentTrip['end_lat'] = $lastLoc['lat'];
            $currentTrip['end_lng'] = $lastLoc['lng'];
            $currentTrip['end_timestamp'] = $lastLoc['timestamp'];
            $currentTrip['duration_seconds'] = $currentTrip['end_timestamp'] - $currentTrip['start_timestamp'];
            $currentTrip['avg_speed'] = $currentTrip['point_count'] > 0 ? round($currentTrip['speed_sum'] / $currentTrip['point_count'], 1) : 0;
            $trips[] = $currentTrip;
        }

        $learnedStops = \App\Models\LearnedStop::where('company_id', $companyId)
            ->select('latitude', 'longitude', 'radius_meters', 'is_traffic_light')
            ->get();

        return response()->json([
            'success' => true, 
            'history' => $historyData,
            'trips' => array_reverse($trips), // newest trips first
            'learnedStops' => $learnedStops
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

    // Modal üzerinden araca IMEI atamak için method
    public function assignImei(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('vehicles.edit'), 403);

        $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'device_imei' => 'nullable|string|max:50',
        ]);

        $vehicle = Vehicle::where('company_id', auth()->user()->company_id)
            ->where('id', $request->vehicle_id)
            ->firstOrFail();

        $vehicle->device_imei = $request->device_imei;
        $vehicle->save();

        return back()->with('success', 'Araç takip cihazı (IMEI) başarıyla kaydedildi.');
    }

    // ========================================================
    // TANIMLAMALAR PANELİ
    // ========================================================

    public function definitions()
    {
        abort_unless(auth()->user()->hasPermission('vehicle_tracking.view'), 403);

        $companyId = auth()->user()->company_id;

        $vehiclesWithDevice = Vehicle::where('company_id', $companyId)->whereNotNull('device_imei')->where('device_imei', '!=', '')->orderBy('plate')->get();
        $vehiclesWithoutDevice = Vehicle::where('company_id', $companyId)->where(function($q) {
            $q->whereNull('device_imei')->orWhere('device_imei', '');
        })->orderBy('plate')->get();

        $alarms = VehicleAlarm::where('company_id', $companyId)->with('vehicle')->get();
        $geofences = Geofence::where('company_id', $companyId)->get();
        $schedules = WorkSchedule::where('company_id', $companyId)->get();

        // Her aracın son sinyal zamanını hesapla
        foreach ($vehiclesWithDevice as $vehicle) {
            $lastLoc = VehicleLocation::where('vehicle_id', $vehicle->id)
                ->orderBy('recorded_at', 'desc')
                ->first();
            $vehicle->last_signal = $lastLoc ? $lastLoc->recorded_at : null;
            $vehicle->device_status = $this->getDeviceStatus($vehicle, $lastLoc);
        }

        return view('live-tracking.definitions', compact('vehiclesWithDevice', 'vehiclesWithoutDevice', 'alarms', 'geofences', 'schedules'));
    }

    private function getDeviceStatus(Vehicle $vehicle, $lastLocation): string
    {
        if (empty($vehicle->device_imei)) {
            return 'none'; // Cihaz atanmamış
        }
        if (!$lastLocation) {
            return 'never'; // Hiç bağlanmadı
        }
        // Son 5 dakika içinde sinyal geldiyse çevrimiçi
        if ($lastLocation->recorded_at->diffInMinutes(now()) < 5) {
            return 'online';
        }
        return 'offline';
    }

    // --- ALARM CRUD ---

    public function storeAlarm(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('vehicle_tracking.view'), 403);

        $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'alarm_type' => 'required|in:speed,stop,ignition,geofence',
            'threshold_value' => 'nullable|numeric|min:0',
            'notify_email' => 'boolean',
            'notify_sms' => 'boolean',
            'notify_panel' => 'boolean',
        ]);

        // Aracın kendi şirketine ait olduğunu doğrula
        Vehicle::where('company_id', auth()->user()->company_id)
            ->where('id', $request->vehicle_id)
            ->firstOrFail();

        VehicleAlarm::create([
            'company_id' => auth()->user()->company_id,
            'vehicle_id' => $request->vehicle_id,
            'alarm_type' => $request->alarm_type,
            'threshold_value' => $request->threshold_value,
            'is_active' => true,
            'notify_email' => $request->boolean('notify_email'),
            'notify_sms' => $request->boolean('notify_sms'),
            'notify_panel' => $request->boolean('notify_panel', true),
            'notes' => $request->notes,
        ]);

        return back()->with('success', 'Alarm başarıyla tanımlandı.');
    }

    public function toggleAlarm(VehicleAlarm $alarm)
    {
        abort_unless(auth()->user()->hasPermission('vehicle_tracking.view'), 403);
        abort_unless($alarm->company_id === auth()->user()->company_id, 403);

        $alarm->is_active = !$alarm->is_active;
        $alarm->save();

        return back()->with('success', 'Alarm durumu güncellendi.');
    }

    public function destroyAlarm(VehicleAlarm $alarm)
    {
        abort_unless(auth()->user()->hasPermission('vehicle_tracking.view'), 403);
        abort_unless($alarm->company_id === auth()->user()->company_id, 403);

        $alarm->delete();

        return back()->with('success', 'Alarm silindi.');
    }

    // --- GEOFENCE CRUD ---

    public function storeGeofence(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('vehicle_tracking.view'), 403);

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:restricted,safe',
            'color' => 'required|string|max:7',
            'coordinates' => 'required|json',
            'radius' => 'nullable|numeric|min:0',
        ]);

        Geofence::create([
            'company_id' => auth()->user()->company_id,
            'name' => $request->name,
            'type' => $request->type,
            'color' => $request->color,
            'coordinates' => json_decode($request->coordinates, true),
            'radius' => $request->radius,
            'is_active' => true,
        ]);

        return back()->with('success', 'Bölge başarıyla tanımlandı.');
    }

    public function destroyGeofence(Geofence $geofence)
    {
        abort_unless(auth()->user()->hasPermission('vehicle_tracking.view'), 403);
        abort_unless($geofence->company_id === auth()->user()->company_id, 403);

        $geofence->delete();

        return back()->with('success', 'Bölge silindi.');
    }

    // --- ÇALIŞMA SAATİ CRUD ---

    public function storeSchedule(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('vehicle_tracking.view'), 403);

        $request->validate([
            'name' => 'required|string|max:255',
            'schedule' => 'required|json',
            'alert_outside_hours' => 'boolean',
        ]);

        WorkSchedule::create([
            'company_id' => auth()->user()->company_id,
            'name' => $request->name,
            'schedule' => json_decode($request->schedule, true),
            'alert_outside_hours' => $request->boolean('alert_outside_hours'),
            'is_active' => true,
        ]);

        return back()->with('success', 'Çalışma saati programı oluşturuldu.');
    }

    public function destroySchedule(WorkSchedule $schedule)
    {
        abort_unless(auth()->user()->hasPermission('vehicle_tracking.view'), 403);
        abort_unless($schedule->company_id === auth()->user()->company_id, 403);

        $schedule->delete();

        return back()->with('success', 'Çalışma saati programı silindi.');
    }
}
