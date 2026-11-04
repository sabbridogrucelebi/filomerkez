<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Models\DriverVehicleAssignment;
use App\Models\Fleet\Driver;
use App\Models\Fleet\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Çelebi Maaş Sistemi entegrasyonu.
 * Kimlik doğrulama: X-Entegrasyon-Anahtari başlığı (config/maas.php).
 *
 *  GET  /api/entegrasyon/maas/araclar     → araç listesi (+ aktif şoför ve telefonu)
 *  GET  /api/entegrasyon/maas/soforler    → aktif şoför listesi
 *  POST /api/entegrasyon/maas/arac-sofor  → araca şoför ata / plakalar arası aktarma
 *                                           (FiloMerkez'in zimmet geçmişi kurallarıyla birebir aynı)
 */
class MaasVehicleController extends Controller
{
    /** Anahtarı doğrular; hata varsa JSON yanıt döndürür */
    private function guard(Request $request)
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
        return null;
    }

    private function companyId(): int
    {
        return (int) config('maas.firma_id');
    }

    /** Araçta şu an çalışan (aktif) şoför sorgusu */
    private function activeDriverQuery()
    {
        return Driver::withoutGlobalScope('company')
            ->whereColumn('vehicle_id', 'vehicles.id')
            ->where('is_active', true)
            ->latest('id')
            ->limit(1);
    }

    public function index(Request $request)
    {
        if ($r = $this->guard($request)) {
            return $r;
        }

        $vehicles = Vehicle::withoutGlobalScope('company')
            ->where('vehicles.company_id', $this->companyId())
            ->select('vehicles.*')
            ->addSelect([
                'active_driver_id'    => $this->activeDriverQuery()->select('id'),
                'active_driver_name'  => $this->activeDriverQuery()->select('full_name'),
                'active_driver_phone' => $this->activeDriverQuery()->select('phone'),
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
                'driver_id'    => $v->active_driver_id,
                'driver'       => $v->active_driver_name,
                'driver_phone' => $v->active_driver_phone,
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

    public function drivers(Request $request)
    {
        if ($r = $this->guard($request)) {
            return $r;
        }

        $drivers = Driver::withoutGlobalScope('company')
            ->leftJoin('vehicles', function ($j) {
                $j->on('drivers.vehicle_id', '=', 'vehicles.id')->whereNull('vehicles.deleted_at');
            })
            ->where('drivers.company_id', $this->companyId())
            ->where('drivers.is_active', true)
            ->where(function ($q) {
                $q->whereNull('drivers.approval_status')->orWhere('drivers.approval_status', '!=', 'pending');
            })
            ->orderBy('drivers.full_name')
            ->get(['drivers.id', 'drivers.full_name', 'drivers.phone', 'drivers.vehicle_id', 'vehicles.plate'])
            ->map(fn ($d) => [
                'id'         => $d->id,
                'full_name'  => $d->full_name,
                'phone'      => $d->phone,
                'vehicle_id' => $d->plate ? $d->vehicle_id : null,
                'plate'      => $d->plate,
            ]);

        return response()->json(['success' => true, 'data' => ['drivers' => $drivers]]);
    }

    /**
     * Araca şoför atar.
     * Girdi: vehicle_id, driver_id, date (Y-m-d, ops.), shift (morning|evening|full_day, ops.),
     *        previous_action: 'unassign' (eski şoför araçsız kalır) | 'swap' (eski şoför, yeni şoförün eski aracına geçer)
     *        changed_by (ops., log için)
     */
    public function assignDriver(Request $request)
    {
        if ($r = $this->guard($request)) {
            return $r;
        }

        $data = $request->validate([
            'vehicle_id'      => 'required|integer',
            'driver_id'       => 'required|integer',
            'date'            => 'nullable|date',
            'shift'           => 'nullable|string|in:morning,evening,full_day',
            'previous_action' => 'nullable|string|in:unassign,swap',
            'changed_by'      => 'nullable|string|max:100',
        ]);

        $companyId = $this->companyId();
        $date      = $data['date'] ?? now()->toDateString();
        $shift     = $data['shift'] ?? 'morning';
        $action    = $data['previous_action'] ?? 'unassign';
        $note      = 'Çelebi Maaş Sistemi' . (!empty($data['changed_by']) ? ' (' . $data['changed_by'] . ')' : '');

        $vehicle = Vehicle::withoutGlobalScope('company')->where('company_id', $companyId)->find($data['vehicle_id']);
        $driver  = Driver::withoutGlobalScope('company')->where('company_id', $companyId)->where('is_active', true)->find($data['driver_id']);

        if (!$vehicle) {
            return response()->json(['success' => false, 'message' => 'Araç bulunamadı.'], 404);
        }
        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'Şoför bulunamadı veya aktif değil.'], 404);
        }

        $previousDrivers = Driver::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('vehicle_id', $vehicle->id)
            ->where('is_active', true)
            ->where('id', '!=', $driver->id)
            ->get();

        $newDriverOldVehicleId = $driver->vehicle_id && (int) $driver->vehicle_id !== (int) $vehicle->id
            ? (int) $driver->vehicle_id : null;

        if ((int) $driver->vehicle_id === (int) $vehicle->id && $previousDrivers->isEmpty()) {
            return response()->json(['success' => true, 'message' => 'Şoför zaten bu araçta.', 'data' => ['changed' => false]]);
        }

        DB::transaction(function () use ($vehicle, $driver, $previousDrivers, $newDriverOldVehicleId, $action, $date, $shift, $note, $companyId) {
            // 1) Yeni şoförü araca ata
            if ((int) $driver->vehicle_id !== (int) $vehicle->id) {
                $this->moveDriver($driver, $vehicle->id, $date, $shift, $note, $companyId);
            }

            // 2) Araçtaki eski şoför(ler)
            foreach ($previousDrivers as $old) {
                if ($action === 'swap' && $newDriverOldVehicleId) {
                    $this->moveDriver($old, $newDriverOldVehicleId, $date, $shift, $note, $companyId);
                } else {
                    $this->moveDriver($old, null, $date, $shift, $note, $companyId);
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Şoför ataması FiloMerkez\'de güncellendi.',
            'data'    => [
                'changed'          => true,
                'vehicle_id'       => $vehicle->id,
                'driver_id'        => $driver->id,
                'previous_drivers' => $previousDrivers->pluck('full_name'),
                'swapped_to'       => $action === 'swap' ? $newDriverOldVehicleId : null,
            ],
        ]);
    }

    /**
     * Şoförün aktif zimmetini kapatır ve (varsa) yeni araca zimmet açar.
     * DriverController::changeVehicle ile aynı kurallar: eski zimmet yeni tarihten bir gün önce kapanır.
     */
    private function moveDriver(Driver $driver, ?int $newVehicleId, string $date, string $shift, string $note, int $companyId): void
    {
        $active = DriverVehicleAssignment::withoutGlobalScope('company')
            ->where('driver_id', $driver->id)
            ->whereNull('unassigned_at')
            ->first();

        if ($active) {
            $closeDate = Carbon::parse($date)->subDay()->toDateString();
            if ($closeDate < $active->assigned_at->toDateString()) {
                $closeDate = $active->assigned_at->toDateString();
            }
            $active->close($closeDate, 'full_day');
        }

        if ($newVehicleId) {
            DriverVehicleAssignment::create([
                'company_id'     => $companyId,
                'driver_id'      => $driver->id,
                'vehicle_id'     => $newVehicleId,
                'assigned_at'    => $date,
                'assigned_shift' => $shift,
                'notes'          => $note,
            ]);
        }

        $driver->update(['vehicle_id' => $newVehicleId]);
    }
}
