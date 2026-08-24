<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Fleet\Vehicle;
use App\Models\Fleet\VehicleLocation;

class LiveTrackingController extends Controller
{
    public function index()
    {
        // Kullanıcının yetkisi var mı? (navigation config'deki permission'a göre)
        abort_unless(auth()->user()->hasPermission('vehicle_tracking.view'), 403);

        $companyId = auth()->user()->company_id;
        
        // Şirkete ait tüm araçları al, cihaz numarası (IMEI) olanları ve son konumlarını getir.
        $vehicles = Vehicle::where('company_id', $companyId)->get();

        return view('live-tracking.index', compact('vehicles'));
    }

    public function liveData()
    {
        abort_unless(auth()->user()->hasPermission('vehicle_tracking.view'), 403);

        $companyId = auth()->user()->company_id;
        
        // Sadece IMEI numarası olan araçları al
        $vehicles = Vehicle::where('company_id', $companyId)
            ->whereNotNull('device_imei')
            ->where('device_imei', '!=', '')
            ->get();

        $liveData = [];
        foreach ($vehicles as $vehicle) {
            // Aracın en son kaydedilen konumunu bul
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
}
