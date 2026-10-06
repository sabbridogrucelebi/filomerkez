<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\Fuel;
use App\Models\Document;
use App\Models\Payroll;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\TrafficPenalty;
use App\Models\VehicleMaintenance;
use App\Models\Fleet\Driver;
use App\Models\Fleet\Vehicle;

class DashboardService
{
    /**
     * Web ve mobil için ortak dashboard istatistiklerini hesaplar ve döndürür.
     */
    public function getStatistics($companyId)
    {
        $vehicleCount = Vehicle::where('company_id', $companyId)->count();
        $driverCount = Driver::where('company_id', $companyId)->count();
        $customerCount = Customer::where('company_id', $companyId)->count();

        $todayTrips = Trip::where('company_id', $companyId)
            ->whereDate('trip_date', now()->toDateString())->count();

        $monthlyIncome = Trip::where('company_id', $companyId)
            ->whereMonth('trip_date', now()->month)
            ->whereYear('trip_date', now()->year)
            ->sum('trip_price');

        $monthlyFuel = Fuel::where('company_id', $companyId)
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('total_cost');

        $monthlyPenalty = TrafficPenalty::where('company_id', $companyId)
            ->whereMonth('penalty_date', now()->month)
            ->whereYear('penalty_date', now()->year)
            ->sum('penalty_amount');

        $activeMaintenances = VehicleMaintenance::where('company_id', $companyId)
            ->where('status', 'in_progress')->count();
        $waitingMaintenances = VehicleMaintenance::where('company_id', $companyId)
            ->where('status', 'waiting')->count();

        $today = now()->startOfDay();
        $sevenDaysLater = now()->copy()->addDays(7)->startOfDay();
        $thirtyDaysLater = now()->copy()->addDays(30)->startOfDay();

        $expiredDocumentsCount = Document::where('company_id', $companyId)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', $today)
            ->count();

        $documentsExpiringIn7DaysCount = Document::where('company_id', $companyId)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', $today)
            ->whereDate('end_date', '<=', $sevenDaysLater)
            ->count();

        $documentsExpiringIn30DaysCount = Document::where('company_id', $companyId)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>', $sevenDaysLater)
            ->whereDate('end_date', '<=', $thirtyDaysLater)
            ->count();

        $upcomingDocuments = Document::where('company_id', $companyId)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', $today)
            ->whereDate('end_date', '<=', $thirtyDaysLater)
            ->orderBy('end_date')
            ->take(10)
            ->get();

        $totalFuel = Fuel::where('company_id', $companyId)->sum('total_cost');
        $totalSalary = Payroll::where('company_id', $companyId)->sum('net_salary');
        $netProfit = $monthlyIncome - ($totalFuel + $totalSalary);

        $recentTrips = Trip::with(['vehicle', 'driver'])
            ->where('company_id', $companyId)
            ->orderBy('trip_date', 'desc')
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();

        $recentActivity = ActivityLog::with('user:id,name')
            ->where('company_id', $companyId)
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get();

        // Bakım Sağlığı (Maintenance Health)
        // Son 500 KM'ye düşen veya gecikmiş (<= 500) yağ ve alt yağlama bakımlarını bul
        $vehiclesWithSettings = Vehicle::with(['maintenanceSetting', 'maintenances' => function($q) {
            $q->whereIn('maintenance_type', ['YAĞ BAKIMI', 'ALT YAĞLAMA'])->where('status', 'completed');
        }])->where('company_id', $companyId)->get();

        $maintenanceHealth = [];
        foreach ($vehiclesWithSettings as $vehicle) {
            $status = $vehicle->maintenance_status;
            $needsAttention = false;
            $alerts = [];

            if ($status['has_oil_setting'] && $status['oil_remaining'] !== null && $status['oil_remaining'] <= 500) {
                $needsAttention = true;
                $alerts[] = [
                    'type' => 'YAĞ BAKIMI',
                    'remaining' => $status['oil_remaining'],
                    'percent' => $status['oil_percent']
                ];
            }
            if ($status['has_lube_setting'] && $status['lube_remaining'] !== null && $status['lube_remaining'] <= 500) {
                $needsAttention = true;
                $alerts[] = [
                    'type' => 'ALT YAĞLAMA',
                    'remaining' => $status['lube_remaining'],
                    'percent' => $status['lube_percent']
                ];
            }

            if ($needsAttention) {
                $maintenanceHealth[] = [
                    'vehicle_id' => $vehicle->id,
                    'plate' => $vehicle->plate,
                    'current_km' => $status['current_km'],
                    'alerts' => $alerts
                ];
            }
        }

        // Kalan km'ye göre küçükten büyüğe sırala (en aciller üstte)
        usort($maintenanceHealth, function($a, $b) {
            $minA = min(array_column($a['alerts'], 'remaining'));
            $minB = min(array_column($b['alerts'], 'remaining'));
            return $minA <=> $minB;
        });

        return [
            'vehicle_count' => $vehicleCount,
            'driver_count' => $driverCount,
            'customer_count' => $customerCount,
            'today_trips' => $todayTrips,
            'monthly_income' => $monthlyIncome,
            'monthly_fuel' => $monthlyFuel,
            'monthly_penalty' => $monthlyPenalty,
            'active_maintenances' => $activeMaintenances,
            'waiting_maintenances' => $waitingMaintenances,
            'expired_documents_count' => $expiredDocumentsCount,
            'documents_expiring_in_7_days_count' => $documentsExpiringIn7DaysCount,
            'documents_expiring_in_30_days_count' => $documentsExpiringIn30DaysCount,
            'upcoming_documents' => $upcomingDocuments,
            'total_fuel' => $totalFuel,
            'total_salary' => $totalSalary,
            'net_profit' => $netProfit,
            'recent_trips' => $recentTrips,
            'recent_activity' => $recentActivity,
            'maintenance_health' => $maintenanceHealth
        ];
    }
}
