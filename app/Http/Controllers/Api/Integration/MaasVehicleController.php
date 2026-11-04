<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Models\Fleet\Driver;
use App\Models\Fleet\Vehicle;
use Illuminate\Http\Request;

/**
 * Çelebi Maaş Sistemi için salt-okunur araç listesi.
 * Kimlik doğrulama: X-Entegrasyon-Anahtari başlığı (config/maas.php).
 * Silinen (soft delete) araçlar listede yer almaz; maaş sistemi bunları kendi tarafında arşive alır.
 */
class MaasVehicleController extends Controller
{
    public function index(Request $request)
    {
        $key = (string) config('maas.anahtar');
        $companyId = (int) config('maas.firma_id');
        $given = (string) $request->header('X-Entegrasyon-Anahtari', '');

        if ($key === '' || $companyId <= 0) {
            return response()->json(['success' => false, 'message' => 'Maaş entegrasyonu kapalı.'], 503);
        }
        if ($given === '' || !hash_equals($key, $given)) {
            return response()->json(['success' => false, 'message' => 'Geçersiz entegrasyon anahtarı.'], 401);
        }

        $vehicles = Vehicle::withoutGlobalScope('company')
            ->where('vehicles.company_id', $companyId)
            ->select('vehicles.*')
            ->addSelect([
                'last_driver_name' => Driver::withoutGlobalScopes()
                    ->select('full_name')
                    ->whereColumn('vehicle_id', 'vehicles.id')
                    ->latest('id')
                    ->limit(1),
            ])
            ->orderBy('plate')
            ->get()
            ->map(fn ($v) => [
                'id'           => $v->id,
                'plate'        => $v->plate,
                'brand'        => $v->brand,
                'model'        => $v->model,
                'vehicle_type' => $v->vehicle_type,
                'model_year'   => $v->model_year,
                'seat_count'   => $v->seat_count,
                'fuel_type'    => $v->fuel_type,
                'color'        => $v->color,
                'current_km'   => $v->current_km !== null ? (int) $v->current_km : null,
                'status'       => $v->is_active ? 'active' : 'passive',
                'driver'       => $v->last_driver_name,
                'updated_at'   => optional($v->updated_at)->toDateTimeString(),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Araç listesi getirildi.',
            'data'    => ['vehicles' => $vehicles],
            'meta'    => ['current_page' => 1, 'last_page' => 1, 'total' => $vehicles->count()],
        ]);
    }
}

