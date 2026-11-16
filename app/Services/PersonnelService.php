<?php

namespace App\Services;

use App\Models\Fleet\Driver;

class PersonnelService
{
    /**
     * Şirkete ait tüm personelleri (sürücüleri) listeler.
     */
    public function getPersonnelList($companyId)
    {
        return Driver::where('drivers.company_id', $companyId)
            ->leftJoin('vehicles', function ($join) {
                $join->on('drivers.vehicle_id', '=', 'vehicles.id')
                    ->whereNull('vehicles.deleted_at');
            })
            ->select('drivers.*')
            ->with('vehicle:id,plate')
            ->orderByRaw('CASE WHEN vehicles.plate IS NULL OR TRIM(vehicles.plate) = "" THEN 1 ELSE 0 END ASC')
            ->orderByRaw("REPLACE(vehicles.plate, ' ', '') ASC")
            ->orderBy('drivers.full_name', 'asc')
            ->get();
    }

    /**
     * Belirli bir personelin tüm detaylarını, aracını, belgelerini ve son bordrolarını getirir.
     */
    public function getPersonnelDetail($companyId, $driverId)
    {
        return Driver::where('company_id', $companyId)
            ->where('id', $driverId)
            ->with([
                'vehicle', 
                'documents', 
                'payrolls' => function($q) {
                    $q->latest('period_month')->take(5); // Sadece son 5 bordroyu getir
                }
            ])
            ->first();
    }
}
