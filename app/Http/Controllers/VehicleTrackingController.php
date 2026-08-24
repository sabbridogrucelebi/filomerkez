<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Fleet\Vehicle;
use App\Models\Fleet\VehicleLocation;

class VehicleTrackingController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->hasPermission('vehicles.view'), 403);

        $companyId = auth()->user()->company_id;
        
        // Şirkete ait tüm araçları al, cihaz numarası (IMEI) olanları ve son konumlarını getir.
        $vehicles = Vehicle::where('company_id', $companyId)->get();

        return view('vehicle-tracking.index', compact('vehicles'));
    }

    public function live()
    {
        abort_unless(auth()->user()->hasPermission('vehicles.view'), 403);

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
                    'LicensePlate' => $vehicle->license_plate,
                    'Latitude' => $lastLocation->latitude,
                    'Longitude' => $lastLocation->longitude,
                    'Speed' => $lastLocation->speed,
                    'Course' => $lastLocation->course,
                    'Datetime' => $lastLocation->recorded_at ? $lastLocation->recorded_at->format('d.m.Y H:i:s') : null,
                    'Address' => 'Konum: ' . $lastLocation->latitude . ', ' . $lastLocation->longitude, // Geocoding API eklenebilir
                ];
            } else {
                // Konumu yoksa ama cihaz takılıysa varsayılan/bekliyor datası
                $liveData[] = [
                    'Node' => $vehicle->id,
                    'LicensePlate' => $vehicle->license_plate,
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

    // Modal üzerinden araca IMEI atamak için yeni method
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

    public function reports()
    {
        abort_unless(auth()->user()->hasPermission('vehicle-tracking.view'), 403);
        
        return redirect()->route('vehicle-tracking.reports.daily-work');
    }

    public function dailyWorkReport(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('vehicle-tracking.view'), 403);

        $companyId = auth()->user()->company_id;
        $date = $request->input('date', date('Y-m-d'));
        
        // Bu kısım yerli cihaza göre ileride özelleştirilecek. Şimdilik boş rapor dönüyor.
        $reports = [];

        return view('vehicle-tracking.reports', compact('reports', 'date'));
    }
}
