<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Fleet\VehicleReportSummary;
use App\Models\Fleet\Vehicle;
use Carbon\Carbon;

class ReportsApiController extends Controller
{
    public function dashboardData(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('vehicle_tracking.view'), 403);
        $companyId = auth()->user()->company_id;
        
        $startDate = $request->input('start_date', Carbon::now()->subDays(7)->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());

        $summaries = VehicleReportSummary::with('vehicle')
            ->whereHas('vehicle', function($q) {
                $q->whereNotNull('device_imei')->where('device_imei', '!=', '');
            })
            ->where('company_id', $companyId)
            ->whereBetween('report_date', [$startDate, $endDate])
            ->get();

        if ($summaries->isEmpty()) {
            return response()->json([
                'overview' => [
                    'avg_eco_score' => 0,
                    'total_loss_tl' => 0,
                    'total_distance' => 0,
                    'total_idle_hours' => 0,
                    'avg_capacity' => 0,
                ],
                'leaderboard' => [],
                'maintenance_alerts' => []
            ]);
        }

        // Genel Özet (Overview)
        $overview = [
            'avg_eco_score' => round($summaries->avg('eco_score')),
            'total_loss_tl' => round($summaries->sum('idle_fuel_loss_tl')),
            'total_distance' => round($summaries->sum('total_distance_km')),
            'total_idle_hours' => round($summaries->sum('idle_minutes') / 60, 1),
            'avg_capacity' => round($summaries->avg('active_capacity_percent'), 1),
        ];

        // Liderlik Tablosu (Araç bazında grupla)
        $leaderboard = $summaries->groupBy('vehicle_id')->map(function ($group) {
            $vehicle = $group->first()->vehicle;
            return [
                'vehicle_id' => $vehicle->id,
                'plate' => $vehicle->plate,
                'driver' => $vehicle->driver,
                'avg_score' => round($group->avg('eco_score')),
                'total_distance' => round($group->sum('total_distance_km')),
                'violations' => $group->sum('speed_violation_count') + $group->sum('harsh_braking_count'),
                'total_loss_tl' => round($group->sum('idle_fuel_loss_tl')),
            ];
        })->sortByDesc('avg_score')->values();

        // Kestirimci Bakım (En çok balata aşınması veya bakım yaklaşanlar)
        $maintenanceAlerts = $summaries->groupBy('vehicle_id')->map(function ($group) {
            $vehicle = $group->first()->vehicle;
            $wear = $group->sum('brake_pad_wear_percent');
            return [
                'plate' => $vehicle->plate,
                'brake_wear_percent' => min(100, round($wear, 1)),
                'status' => $wear > 50 ? 'warning' : 'good'
            ];
        })->filter(fn($v) => $v['status'] === 'warning')->sortByDesc('brake_wear_percent')->values();

        return response()->json([
            'overview' => $overview,
            'leaderboard' => $leaderboard,
            'maintenance_alerts' => $maintenanceAlerts,
            'chart_data' => $this->getChartData($summaries)
        ]);
    }

    private function getChartData($summaries)
    {
        // Günlük kayıp TL ve Ortalama Skor grafiği verisi
        $dailyGroups = $summaries->groupBy('report_date');
        $dates = [];
        $lossData = [];
        $scoreData = [];

        foreach ($dailyGroups as $date => $group) {
            $dates[] = Carbon::parse($date)->format('d M');
            $lossData[] = round($group->sum('idle_fuel_loss_tl'));
            $scoreData[] = round($group->avg('eco_score'));
        }

        return [
            'labels' => $dates,
            'datasets' => [
                'loss' => $lossData,
                'scores' => $scoreData
            ]
        ];
    }

    public function workingReportData(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('vehicle_tracking.view'), 403);
        $companyId = auth()->user()->company_id;
        
        $startDate = $request->input('start_date', Carbon::today()->toDateString());
        $endDate = $request->input('end_date', Carbon::today()->toDateString());
        $vehicleId = $request->input('vehicle_id', 'all');

        // Gelecek günlerin raporlanmasını engelle (Bugünü baz al)
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        if ($end->isFuture()) {
            $end = Carbon::today();
            $endDate = $end->toDateString();
        }
        if ($start->isAfter($end)) {
            $start = $end->copy();
            $startDate = $start->toDateString();
        }
        
        // Güvenlik için maksimum 30 günlük aralık hesaplanabilir
        if ($start->diffInDays($end) <= 30) {
            $currentDate = $start->copy();
            while ($currentDate->lte($end)) {
                $params = ['date' => $currentDate->toDateString()];
                if ($vehicleId !== 'all') {
                    $params['--vehicle'] = $vehicleId;
                }
                \Illuminate\Support\Facades\Artisan::call('reports:calculate-daily', $params);
                $currentDate->addDay();
            }
        }

        $query = VehicleReportSummary::with('vehicle')
            ->whereHas('vehicle', function($q) {
                $q->whereNotNull('device_imei')->where('device_imei', '!=', '');
            })
            ->where('company_id', $companyId)
            ->whereBetween('report_date', [$startDate, $endDate])
            ->where('active_minutes', '>', 0);
            
        if ($vehicleId !== 'all') {
            $query->where('vehicle_id', $vehicleId);
        }

        $summaries = $query->orderBy('report_date', 'desc')->get();

        $rows = $summaries->map(function ($row) {
            return [
                'id' => $row->id,
                'date' => $row->report_date->format('d.m.Y'),
                'plate' => $row->vehicle->plate ?? 'Bilinmeyen',
                'active_mins' => $row->active_minutes,
                'idle_mins' => $row->idle_minutes,
                'idle_loss_tl' => $row->idle_fuel_loss_tl,
                'distance' => $row->total_distance_km
            ];
        });

        return response()->json(['rows' => $rows]);
    }

    public function firstIgnitionReportData(Request $request)
    {
        $companyId = auth()->user()->company_id;
        abort_unless(auth()->user()->hasPermission('vehicle_tracking.view'), 403);

        $startDate = $request->input('start_date', Carbon::today()->toDateString());
        $endDate = $request->input('end_date', Carbon::today()->toDateString());
        $vehicleId = $request->input('vehicle_id', 'all');

        $start = Carbon::parse($startDate, 'Europe/Istanbul');
        $end = Carbon::parse($endDate, 'Europe/Istanbul');
        
        if ($end->isFuture()) {
            $end = Carbon::today('Europe/Istanbul');
        }
        if ($start->isAfter($end)) {
            $start = $end->copy();
        }
        
        $query = \App\Models\Fleet\Vehicle::with('drivers')
            ->whereNotNull('device_imei')
            ->where('device_imei', '!=', '')
            ->where('company_id', $companyId);
            
        if ($vehicleId !== 'all') {
            $query->where('id', $vehicleId);
        }
        
        $vehicles = $query->get();
        $rows = [];

        // Güvenlik için maksimum 30 günlük aralık hesaplanabilir
        if ($start->diffInDays($end) <= 30) {
            $currentDate = $start->copy();
            
            while ($currentDate->lte($end)) {
                $dayStartUtc = $currentDate->copy()->startOfDay()->setTimezone('UTC');
                $dayEndUtc = $currentDate->copy()->endOfDay()->setTimezone('UTC');
                
                foreach ($vehicles as $vehicle) {
                    $firstLocation = \App\Models\Fleet\VehicleLocation::where('vehicle_id', $vehicle->id)
                        ->whereBetween('recorded_at', [$dayStartUtc, $dayEndUtc])
                        ->where(function($q) {
                            $q->where('speed', '>', 2)
                              ->orWhereRaw("JSON_EXTRACT(status, '$.acc') = true");
                        })
                        ->orderBy('recorded_at', 'asc')
                        ->first();
                        
                    if ($firstLocation) {
                        // Veritabanındaki string UTC, ancak Laravel timezone config'i nedeniyle yanlış yorumlanmış olabilir.
                        // getRawOriginal ile ham stringi alıp UTC olarak tanıtıyor, sonra İstanbul saatine çeviriyoruz.
                        $rawDate = $firstLocation->getRawOriginal('recorded_at');
                        $localTime = \Carbon\Carbon::parse($rawDate, 'UTC')->setTimezone('Europe/Istanbul');
                        
                        $driver = $vehicle->drivers->first();
                        $driverName = $driver ? trim(($driver->first_name ?? $driver->name ?? '') . ' ' . ($driver->last_name ?? $driver->surname ?? '')) : 'Şoför Atanmamış';
                        if (empty(trim($driverName))) $driverName = 'Bilinmiyor';
                        
                        $rows[] = [
                            'id' => $vehicle->id . '_' . $currentDate->format('Ymd'),
                            'date' => $currentDate->format('d.m.Y'),
                            'plate' => $vehicle->plate,
                            'driver' => $driverName,
                            'first_ignition_time' => $localTime->format('H:i:s'),
                            'first_ignition_location' => round($firstLocation->latitude, 4) . ', ' . round($firstLocation->longitude, 4),
                            'distance' => 0 // To make UI table sorting easier if needed
                        ];
                    }
                }
                $currentDate->addDay();
            }
        }

        // Tarihe göre ters sırala (en yeni en üstte)
        usort($rows, function($a, $b) {
            return strtotime(\Carbon\Carbon::createFromFormat('d.m.Y', $b['date'])->format('Y-m-d')) - strtotime(\Carbon\Carbon::createFromFormat('d.m.Y', $a['date'])->format('Y-m-d'));
        });

        return response()->json(['rows' => $rows]);
    }

    /**
     * Hız Alarmı Raporu
     * vehicle_locations tablosundaki speed verisini vehicle_alarms tablosundaki hız eşik değeriyle kıyaslar.
     * Ardışık ihlalleri tek bir olay olarak gruplar ve süresini hesaplar.
     */
    public function speedAlarmReportData(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('vehicle_tracking.view'), 403);
        $companyId = auth()->user()->company_id;

        $startDate = $request->input('start_date', Carbon::today()->toDateString());
        $endDate = $request->input('end_date', Carbon::today()->toDateString());
        $vehicleId = $request->input('vehicle_id', 'all');

        $start = Carbon::parse($startDate, 'Europe/Istanbul');
        $end = Carbon::parse($endDate, 'Europe/Istanbul');

        if ($end->isFuture()) {
            $end = Carbon::today('Europe/Istanbul');
        }
        if ($start->isAfter($end)) {
            $start = $end->copy();
        }
        // Maksimum 30 gün
        if ($start->diffInDays($end) > 30) {
            $start = $end->copy()->subDays(30);
        }

        // Araçları getir
        $vehicleQuery = \App\Models\Fleet\Vehicle::whereNotNull('device_imei')
            ->where('device_imei', '!=', '')
            ->where('company_id', $companyId);

        if ($vehicleId !== 'all') {
            $vehicleQuery->where('id', $vehicleId);
        }
        $vehicles = $vehicleQuery->get();

        $rows = [];
        $totalViolations = 0;
        $maxSpeedRecorded = 0;

        $startUtc = $start->copy()->startOfDay()->setTimezone('UTC');
        $endUtc = $end->copy()->endOfDay()->setTimezone('UTC');

        foreach ($vehicles as $vehicle) {
            // Araca tanımlı hız alarm eşiğini bul
            $speedAlarm = \App\Models\VehicleAlarm::where('vehicle_id', $vehicle->id)
                ->where('alarm_type', 'speed')
                ->where('is_active', true)
                ->first();

            // Eğer araca hız alarmı tanımlanmamışsa varsayılan 120 km/h kullan
            $speedLimit = $speedAlarm ? (float) $speedAlarm->threshold_value : 120;

            // Seçilen tarih aralığındaki tüm konum kayıtlarını getir (hız > limit olanları)
            $locations = \App\Models\Fleet\VehicleLocation::where('vehicle_id', $vehicle->id)
                ->whereBetween('recorded_at', [$startUtc, $endUtc])
                ->where('speed', '>', $speedLimit)
                ->orderBy('recorded_at', 'asc')
                ->get(['id', 'latitude', 'longitude', 'speed', 'recorded_at', 'status']);

            if ($locations->isEmpty()) continue;

            // Ardışık ihlalleri grupla (2 dakika içindeki kayıtlar aynı ihlal olarak sayılır)
            $violationGroup = [];
            $prevTime = null;

            foreach ($locations as $loc) {
                $rawDate = $loc->getRawOriginal('recorded_at');
                $locTime = Carbon::parse($rawDate, 'UTC');

                if ($prevTime && $locTime->diffInMinutes($prevTime) <= 2) {
                    // Aynı ihlal grubuna ekle
                    $violationGroup[] = $loc;
                } else {
                    // Önceki grubu işle
                    if (!empty($violationGroup)) {
                        $row = $this->processViolationGroup($violationGroup, $vehicle, $speedLimit);
                        if ($row) {
                            $rows[] = $row;
                            $totalViolations++;
                            if ($row['speed'] > $maxSpeedRecorded) $maxSpeedRecorded = $row['speed'];
                        }
                    }
                    // Yeni grup başlat
                    $violationGroup = [$loc];
                }
                $prevTime = $locTime;
            }

            // Son grubu işle
            if (!empty($violationGroup)) {
                $row = $this->processViolationGroup($violationGroup, $vehicle, $speedLimit);
                if ($row) {
                    $rows[] = $row;
                    $totalViolations++;
                    if ($row['speed'] > $maxSpeedRecorded) $maxSpeedRecorded = $row['speed'];
                }
            }
        }

        // Tarihe göre ters sırala (en yeni en üstte)
        usort($rows, function ($a, $b) {
            return strtotime($b['sort_date']) - strtotime($a['sort_date']);
        });

        return response()->json([
            'rows' => $rows,
            'summary' => [
                'total_violations' => $totalViolations,
                'max_speed' => $maxSpeedRecorded,
            ]
        ]);
    }

    /**
     * Bir ihlal grubunu tek satıra çevirir
     */
    private function processViolationGroup(array $group, $vehicle, float $speedLimit): ?array
    {
        if (empty($group)) return null;

        $first = $group[0];
        $last = end($group);

        $firstRaw = $first->getRawOriginal('recorded_at');
        $lastRaw = $last->getRawOriginal('recorded_at');

        $startTime = Carbon::parse($firstRaw, 'UTC')->setTimezone('Europe/Istanbul');
        $endTime = Carbon::parse($lastRaw, 'UTC')->setTimezone('Europe/Istanbul');

        // İhlal süresi (en az 1 dk göster, tek nokta ise)
        $durationMinutes = max(1, $startTime->diffInMinutes($endTime));

        // Gruptaki maksimum hız
        $maxSpeed = collect($group)->max('speed');

        // Alarm türünü hıza göre belirle
        $alarmType = 'Şehir İçi'; // Varsayılan
        if ($maxSpeed > 120) {
            $alarmType = 'Otoyol';
        } elseif ($maxSpeed > 90) {
            $alarmType = 'Şehir Dışı';
        }

        // Harita hız limiti (genel yasal sınır tahmini)
        $mapSpeedLimit = 50; // Şehir içi varsayılan
        if ($maxSpeed > 120) {
            $mapSpeedLimit = 120; // Otoyol
        } elseif ($maxSpeed > 90) {
            $mapSpeedLimit = 90;  // Şehir dışı
        }

        // Süreyi okunaklı formata çevir
        if ($durationMinutes >= 60) {
            $hours = floor($durationMinutes / 60);
            $mins = $durationMinutes % 60;
            $durationStr = $hours . ' sa ' . $mins . ' dk';
        } else {
            $durationStr = $durationMinutes . ' dk';
        }

        return [
            'id' => $vehicle->id . '_' . $startTime->format('YmdHis'),
            'date' => $startTime->format('d.m.Y'),
            'time' => $startTime->format('H:i:s'),
            'sort_date' => $startTime->format('Y-m-d H:i:s'),
            'plate' => $vehicle->plate,
            'speed' => round($maxSpeed, 1),
            'speed_limit' => $speedLimit,
            'map_speed_limit' => $mapSpeedLimit,
            'duration' => $durationStr,
            'duration_minutes' => $durationMinutes,
            'alarm_type' => $alarmType,
            'latitude' => round($first->latitude, 5),
            'longitude' => round($first->longitude, 5),
        ];
    }

    /**
     * Rölanti Süresi Raporu
     * ACC=true ve speed<3 olan kayıtları tespit ederek rölanti olaylarını gruplar.
     * Özet mod: Günlük toplam rölanti süresi
     * Detay mod: Her rölanti olayı ayrı satır olarak konum bilgisiyle
     */
    public function idleTimeReportData(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('vehicle_tracking.view'), 403);
        $companyId = auth()->user()->company_id;

        $startDate = $request->input('start_date', Carbon::today()->toDateString());
        $endDate = $request->input('end_date', Carbon::today()->toDateString());
        $vehicleId = $request->input('vehicle_id', 'all');
        $mode = $request->input('mode', 'summary'); // 'summary' veya 'detail'
        $minIdleMinutes = (int) $request->input('min_idle', 2); // Minimum rölanti süresi filtresi (dk)

        $start = Carbon::parse($startDate, 'Europe/Istanbul');
        $end = Carbon::parse($endDate, 'Europe/Istanbul');

        if ($end->isFuture()) $end = Carbon::today('Europe/Istanbul');
        if ($start->isAfter($end)) $start = $end->copy();
        if ($start->diffInDays($end) > 30) $start = $end->copy()->subDays(30);

        $vehicleQuery = \App\Models\Fleet\Vehicle::whereNotNull('device_imei')
            ->where('device_imei', '!=', '')
            ->where('company_id', $companyId);

        if ($vehicleId !== 'all') {
            $vehicleQuery->where('id', $vehicleId);
        }
        $vehicles = $vehicleQuery->get();

        $rows = [];
        $totalIdleMinutes = 0;
        $totalIdleEvents = 0;
        $totalDistanceKm = 0;

        foreach ($vehicles as $vehicle) {
            $currentDate = $start->copy();

            while ($currentDate->lte($end)) {
                $dayStartUtc = $currentDate->copy()->startOfDay()->setTimezone('UTC');
                $dayEndUtc = $currentDate->copy()->endOfDay()->setTimezone('UTC');

                // O günün tüm konum kayıtlarını getir (sıralı)
                $locations = \App\Models\Fleet\VehicleLocation::where('vehicle_id', $vehicle->id)
                    ->whereBetween('recorded_at', [$dayStartUtc, $dayEndUtc])
                    ->orderBy('recorded_at', 'asc')
                    ->get(['id', 'latitude', 'longitude', 'speed', 'recorded_at', 'status']);

                if ($locations->isEmpty()) {
                    $currentDate->addDay();
                    continue;
                }

                // Rölanti olaylarını tespit et
                $idleEvents = [];
                $currentIdleGroup = [];
                $dayDistance = 0;
                $prevLat = null;
                $prevLng = null;

                foreach ($locations as $loc) {
                    $acc = isset($loc->status['acc']) ? (bool) $loc->status['acc'] : false;
                    $isIdle = ($acc && $loc->speed < 3); // Motor çalışıyor ama hareket yok

                    if ($isIdle) {
                        $currentIdleGroup[] = $loc;
                    } else {
                        // Önceki rölanti grubunu işle
                        if (count($currentIdleGroup) >= 2) {
                            $event = $this->processIdleGroup($currentIdleGroup, $vehicle, $currentDate);
                            if ($event && $event['duration_minutes'] >= $minIdleMinutes) {
                                $idleEvents[] = $event;
                            }
                        }
                        $currentIdleGroup = [];
                    }

                    // Mesafe hesapla
                    if ($prevLat !== null && $prevLng !== null && $loc->speed >= 3) {
                        $dayDistance += $this->haversine($prevLat, $prevLng, $loc->latitude, $loc->longitude);
                    }
                    $prevLat = $loc->latitude;
                    $prevLng = $loc->longitude;
                }

                // Son grubu işle
                if (count($currentIdleGroup) >= 2) {
                    $event = $this->processIdleGroup($currentIdleGroup, $vehicle, $currentDate);
                    if ($event && $event['duration_minutes'] >= $minIdleMinutes) {
                        $idleEvents[] = $event;
                    }
                }

                $dayDistance = round($dayDistance, 1);

                if ($mode === 'detail') {
                    // Detay mod: Her rölanti olayı ayrı satır
                    foreach ($idleEvents as $event) {
                        $event['distance'] = $dayDistance;
                        $rows[] = $event;
                        $totalIdleMinutes += $event['duration_minutes'];
                        $totalIdleEvents++;
                    }
                } else {
                    // Özet mod: Günlük toplam rölanti
                    $dayIdleMinutes = array_sum(array_column($idleEvents, 'duration_minutes'));
                    $dayIdleCount = count($idleEvents);

                    if ($dayIdleMinutes > 0) {
                        // Süreyi okunaklı formata çevir
                        $durationStr = $this->formatDuration($dayIdleMinutes);

                        $rows[] = [
                            'id' => $vehicle->id . '_' . $currentDate->format('Ymd'),
                            'date' => $currentDate->format('d.m.Y'),
                            'plate' => $vehicle->plate,
                            'idle_count' => $dayIdleCount,
                            'idle_duration' => $durationStr,
                            'duration_minutes' => $dayIdleMinutes,
                            'distance' => $dayDistance,
                            'idle_ratio' => $dayDistance > 0 ? round(($dayIdleMinutes / max(1, $dayIdleMinutes + ($dayDistance / 0.5))) * 100, 1) : 0,
                        ];
                        $totalIdleMinutes += $dayIdleMinutes;
                        $totalIdleEvents += $dayIdleCount;
                    }
                }

                $totalDistanceKm += $dayDistance;
                $currentDate->addDay();
            }
        }

        // Tarihe göre ters sırala
        usort($rows, function ($a, $b) {
            $dateA = isset($a['sort_date']) ? $a['sort_date'] : $a['date'];
            $dateB = isset($b['sort_date']) ? $b['sort_date'] : $b['date'];
            // d.m.Y formatını Y-m-d'ye çevirelim
            if (strpos($dateA, '.') !== false) {
                $dateA = Carbon::createFromFormat('d.m.Y', $dateA)->format('Y-m-d');
            }
            if (strpos($dateB, '.') !== false) {
                $dateB = Carbon::createFromFormat('d.m.Y', $dateB)->format('Y-m-d');
            }
            return strcmp($dateB, $dateA);
        });

        return response()->json([
            'rows' => $rows,
            'summary' => [
                'total_idle_minutes' => $totalIdleMinutes,
                'total_idle_str' => $this->formatDuration($totalIdleMinutes),
                'total_events' => $totalIdleEvents,
                'total_distance' => round($totalDistanceKm, 1),
            ]
        ]);
    }

    /**
     * Bir rölanti grubunu tek satıra çevirir (detay mod)
     */
    private function processIdleGroup(array $group, $vehicle, $date): ?array
    {
        if (count($group) < 2) return null;

        $first = $group[0];
        $last = end($group);

        $firstRaw = $first->getRawOriginal('recorded_at');
        $lastRaw = $last->getRawOriginal('recorded_at');

        $startTime = Carbon::parse($firstRaw, 'UTC')->setTimezone('Europe/Istanbul');
        $endTime = Carbon::parse($lastRaw, 'UTC')->setTimezone('Europe/Istanbul');

        $durationMinutes = max(1, $startTime->diffInMinutes($endTime));

        return [
            'id' => $vehicle->id . '_' . $startTime->format('YmdHis'),
            'date' => $date->format('d.m.Y'),
            'sort_date' => $startTime->format('Y-m-d H:i:s'),
            'plate' => $vehicle->plate,
            'start_time' => $startTime->format('H:i'),
            'end_time' => $endTime->format('H:i'),
            'idle_duration' => $this->formatDuration($durationMinutes),
            'duration_minutes' => $durationMinutes,
            'latitude' => round($first->latitude, 5),
            'longitude' => round($first->longitude, 5),
            'location' => round($first->latitude, 4) . ', ' . round($first->longitude, 4),
            'distance' => 0, // Günlük mesafe sonradan eklenir
        ];
    }

    /**
     * Dakikayı okunaklı formata çevirir
     */
    private function formatDuration(int $minutes): string
    {
        if ($minutes >= 60) {
            $hours = floor($minutes / 60);
            $mins = $minutes % 60;
            return $hours . ' sa ' . $mins . ' dk';
        }
        return $minutes . ' dk';
    }

    /**
     * İki koordinat arasındaki mesafe (km) - Haversine formülü
     */
    private function haversine($lat1, $lon1, $lat2, $lon2): float
    {
        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;
        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return $angle * 6371;
    }
}
