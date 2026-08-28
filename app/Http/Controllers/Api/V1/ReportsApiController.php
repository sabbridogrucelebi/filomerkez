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

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        if ($end->isFuture()) {
            $end = Carbon::today();
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
                    // İlk hareket veya kontak
                    $firstLocation = \App\Models\Fleet\VehicleLocation::where('vehicle_id', $vehicle->id)
                        ->whereBetween('recorded_at', [$dayStartUtc, $dayEndUtc])
                        ->where('speed', '>', 0)
                        ->orderBy('recorded_at', 'asc')
                        ->first();
                        
                    if ($firstLocation) {
                        $localTime = \Carbon\Carbon::parse($firstLocation->recorded_at)->setTimezone('Europe/Istanbul');
                        
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
}
