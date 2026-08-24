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
                $liveData[] = [
                    'Node' => $vehicle->id,
                    'LicensePlate' => $vehicle->plate,
                    'Latitude' => $lastLocation->latitude,
                    'Longitude' => $lastLocation->longitude,
                    'Speed' => $lastLocation->speed,
                    'Course' => $lastLocation->course,
                    'Datetime' => $lastLocation->recorded_at ? $lastLocation->recorded_at->format('d.m.Y H:i:s') : null,
                    'Address' => 'Konum: ' . $lastLocation->latitude . ', ' . $lastLocation->longitude,
                ];
            } else {
                $liveData[] = [
                    'Node' => $vehicle->id,
                    'LicensePlate' => $vehicle->plate,
                    'Latitude' => null,
                    'Longitude' => null,
                    'Speed' => 0,
                    'Course' => 0,
                    'Datetime' => null,
                    'Address' => 'Cihazdan veri bekleniyor...',
                ];
            }
        }

        return response()->json(['vehicles' => $liveData]);
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

        $vehicles = Vehicle::where('company_id', $companyId)->orderBy('plate')->get();
        $alarms = VehicleAlarm::where('company_id', $companyId)->with('vehicle')->get();
        $geofences = Geofence::where('company_id', $companyId)->get();
        $schedules = WorkSchedule::where('company_id', $companyId)->get();

        // Her aracın son sinyal zamanını hesapla
        foreach ($vehicles as $vehicle) {
            $lastLoc = VehicleLocation::where('vehicle_id', $vehicle->id)
                ->orderBy('recorded_at', 'desc')
                ->first();
            $vehicle->last_signal = $lastLoc ? $lastLoc->recorded_at : null;
            $vehicle->device_status = $this->getDeviceStatus($vehicle, $lastLoc);
        }

        return view('live-tracking.definitions', compact('vehicles', 'alarms', 'geofences', 'schedules'));
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
