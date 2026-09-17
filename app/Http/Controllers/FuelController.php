<?php

namespace App\Http\Controllers;

use App\Models\Fuel;
use App\Models\FuelStation;
use App\Models\Fleet\Vehicle;
use Illuminate\Http\Request;

class FuelController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->hasPermission('fuels.view'), 403);

        $fuels = Fuel::with(['vehicle', 'station'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $stationSummaries = FuelStation::with(['fuels', 'payments'])
            ->orderBy('name')
            ->get()
            ->map(function ($station) {
                $totalLiters = (float) $station->fuels->sum('liters');
                $totalAmount = (float) $station->fuels->sum('total_cost');
                $totalPaid = (float) $station->payments->sum('amount');

                return (object) [
                    'id' => $station->id,
                    'name' => $station->name,
                    'total_liters' => $totalLiters,
                    'total_amount' => $totalAmount,
                    'total_paid' => $totalPaid,
                    'current_debt' => $totalAmount - $totalPaid,
                ];
            });

        return view('fuels.index', compact('fuels', 'stationSummaries'));
    }

    public function create()
    {
        abort_unless(auth()->user()->hasPermission('fuels.create'), 403);

        $vehicles = Vehicle::orderBy('plate')->get();
        $stations = FuelStation::where('is_active', true)->orderBy('name')->get();

        return view('fuels.create', compact('vehicles', 'stations'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('fuels.create'), 403);

        $validated = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'fuel_station_id' => 'nullable|exists:fuel_stations,id',
            'station_name' => 'nullable|string|max:255',
            'fuel_type' => 'required|string|max:50',
            'date' => 'required|date',
            'liters' => 'required|numeric|min:0',
            'price_per_liter' => 'required|numeric|min:0',
            'km' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        if (!empty($validated['fuel_station_id'])) {
            $station = FuelStation::find($validated['fuel_station_id']);
            $validated['station_name'] = $station?->name;
        }

        $pricing = $this->calculatePricing(
            (float) $validated['liters'],
            (float) $validated['price_per_liter'],
            $validated['fuel_station_id'] ?? null
        );

        $validated['gross_total_cost'] = $pricing['gross_total_cost'];
        $validated['vat_rate'] = $pricing['vat_rate'];
        $validated['vat_amount'] = $pricing['vat_amount'];
        $validated['net_cost'] = $pricing['net_cost'];
        $validated['discount_amount'] = $pricing['discount_amount'];
        $validated['total_cost'] = $pricing['total_cost'];
        
        $vehicle = \App\Models\Fleet\Vehicle::find($validated['vehicle_id']);
        if ($vehicle) {
            $validated['company_id'] = $vehicle->company_id;
        }

        Fuel::create($validated);

        return redirect()->route('fuels.index')->with('success', 'Yakıt kaydı başarıyla eklendi.');
    }

    public function show(Fuel $fuel)
    {
        abort_unless(auth()->user()->hasPermission('fuels.view'), 403);

        return view('fuels.show', compact('fuel'));
    }

    public function edit(Fuel $fuel)
    {
        abort_unless(auth()->user()->hasPermission('fuels.edit'), 403);

        $vehicles = Vehicle::orderBy('plate')->get();
        $stations = FuelStation::where('is_active', true)->orderBy('name')->get();

        return view('fuels.edit', compact('fuel', 'vehicles', 'stations'));
    }

    public function update(Request $request, Fuel $fuel)
    {
        abort_unless(auth()->user()->hasPermission('fuels.edit'), 403);

        $validated = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'fuel_station_id' => 'nullable|exists:fuel_stations,id',
            'station_name' => 'nullable|string|max:255',
            'fuel_type' => 'required|string|max:50',
            'date' => 'required|date',
            'liters' => 'required|numeric|min:0',
            'price_per_liter' => 'required|numeric|min:0',
            'km' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        if (!empty($validated['fuel_station_id'])) {
            $station = FuelStation::find($validated['fuel_station_id']);
            $validated['station_name'] = $station?->name;
        }

        $pricing = $this->calculatePricing(
            (float) $validated['liters'],
            (float) $validated['price_per_liter'],
            $validated['fuel_station_id'] ?? null
        );

        $validated['gross_total_cost'] = $pricing['gross_total_cost'];
        $validated['vat_rate'] = $pricing['vat_rate'];
        $validated['vat_amount'] = $pricing['vat_amount'];
        $validated['net_cost'] = $pricing['net_cost'];
        $validated['discount_amount'] = $pricing['discount_amount'];
        $validated['total_cost'] = $pricing['total_cost'];

        $vehicle = \App\Models\Fleet\Vehicle::find($validated['vehicle_id']);
        if ($vehicle) {
            $validated['company_id'] = $vehicle->company_id;
        }

        $fuel->update($validated);

        return redirect()->route('fuels.index')->with('success', 'Yakıt kaydı güncellendi.');
    }

    public function destroy(Fuel $fuel)
    {
        abort_unless(auth()->user()->hasPermission('fuels.delete'), 403);

        $fuel->delete();

        return redirect()->route('fuels.index')->with('success', 'Yakıt kaydı silindi.');
    }

    public function downloadTemplate()
    {
        abort_unless(auth()->user()->hasPermission('fuels.create'), 403);

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\FuelTemplateExport, 'yakit_ekleme_sablonu.xlsx');
    }

    public function import(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('fuels.create'), 403);

        $request->validate([
            'excel_file' => ['required', 'file', 'max:10240'],
        ]);

        try {
            \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\FuelsImport, $request->file('excel_file'));
            return back()->with('success', 'Toplu yakıt ekleme işlemi başarıyla tamamlandı.');
        } catch (\Exception $e) {
            return back()->with('error', 'Dosya yüklenirken bir hata oluştu: ' . $e->getMessage());
        }
    }

    public function consumptionSettings()
    {
        abort_unless(auth()->user()->hasPermission('fuels.edit'), 403);

        $vehicles = Vehicle::orderBy('plate')->get();

        return view('fuels.consumption_settings', compact('vehicles'));
    }

    public function saveConsumptionSettings(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('fuels.edit'), 403);

        $data = $request->validate([
            'vehicles' => 'array',
            'vehicles.*.min_km_per_liter' => 'nullable|numeric|min:0',
            'vehicles.*.max_km_per_liter' => 'nullable|numeric|min:0|gte:vehicles.*.min_km_per_liter',
        ]);

        if (isset($data['vehicles'])) {
            foreach ($data['vehicles'] as $vehicleId => $bounds) {
                $vehicle = Vehicle::find($vehicleId);
                if ($vehicle) {
                    $vehicle->min_km_per_liter = $bounds['min_km_per_liter'];
                    $vehicle->max_km_per_liter = $bounds['max_km_per_liter'];
                    $vehicle->save();
                }
            }
        }

        return redirect()->route('fuels.index')->with('success', 'Yakıt tüketim limitleri başarıyla kaydedildi.');
    }

    protected function calculatePricing(float $liters, float $pricePerLiter, ?int $stationId = null): array
    {
        $grossTotal = round($liters * $pricePerLiter, 2);
        $discountAmount = 0;
        $vatRate = 0;

        if ($stationId) {
            $station = FuelStation::find($stationId);

            if ($station) {
                $vatRate = (float) $station->vat_rate;
                
                if ((float) $station->discount_value > 0) {
                    if ($station->discount_type === 'percentage') {
                        $discountAmount = round($grossTotal * ((float) $station->discount_value / 100), 2);
                    }

                    if ($station->discount_type === 'fixed') {
                        $discountAmount = round((float) $station->discount_value, 2);
                    }
                }
            }
        }

        if ($discountAmount > $grossTotal) {
            $discountAmount = $grossTotal;
        }

        $netCost = $grossTotal; // KDV Hariç Ana Tutar
        $vatAmount = 0;

        if ($vatRate > 0) {
            $vatAmount = round($grossTotal * ($vatRate / 100), 2);
        }

        // Toplam Borç = (Ana Tutar - İskonto) + KDV
        $totalCost = round($grossTotal - $discountAmount + $vatAmount, 2);

        return [
            'gross_total_cost' => $grossTotal,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'net_cost' => $netCost,
            'discount_amount' => $discountAmount,
            'total_cost' => $totalCost,
        ];
    }
}