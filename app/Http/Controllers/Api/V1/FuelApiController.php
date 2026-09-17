<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Fuel;
use App\Models\FuelStation;
use Illuminate\Http\Request;

class FuelApiController extends BaseApiController
{
    /**
     * Yakıt kayıtlarını listeler
     */
    public function index(Request $request)
    {
        if (!$this->userHasPermission($request->user(), 'fuels.view')) {
            return $this->errorResponse('Bu işlem için yetkiniz bulunmamaktadır.', 403);
        }

        $query = Fuel::with(['vehicle:id,plate', 'station:id,name']);

        if ($request->user()->is_super_admin && $request->headers->has('x-tenant-id')) {
            $query->where('company_id', $request->header('x-tenant-id'));
            $companyIdForStation = $request->header('x-tenant-id');
        } else if (!$request->user()->is_super_admin) {
            $companyIdForStation = $request->user()->company_id;
        } else {
            // Super admin without tenant id sees all, but station summaries might be tricky.
            // For now, let's just use their company_id or skip station summaries if we want to show all.
            $companyIdForStation = null;
        }

        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        $fuels = $query->orderByDesc('date')->orderByDesc('id')->get();

        // Calculate is_paid
        $stationSummariesQuery = \App\Models\FuelStation::with('payments');
        if ($companyIdForStation) {
            $stationSummariesQuery->where('company_id', $companyIdForStation);
        }
        $stationSummaries = $stationSummariesQuery->get()
            ->mapWithKeys(function ($station) {
                return [mb_strtolower(trim($station->name ?? '')) => (float) $station->payments->sum('amount')];
            });

        $paymentStatusMap = [];
        foreach ($fuels->filter(fn ($row) => filled($row->station?->name ?? $row->station_name))
                ->groupBy(function ($row) {
                    return mb_strtolower(trim($row->station?->name ?? $row->station_name ?? ''));
                }) as $stationKey => $stationRows) {

            $remainingPayment = (float) ($stationSummaries[$stationKey] ?? 0);
            
            $sortedStationRows = $stationRows->sortBy(function ($row) {
                return sprintf('%s-%010d', optional($row->date)->format('Ymd') ?? '00000000', (int) $row->id);
            })->values();

            foreach ($sortedStationRows as $row) {
                $rowTotal = (float) ($row->total_cost ?? 0);
                if ($remainingPayment >= $rowTotal && $rowTotal > 0) {
                    $paymentStatusMap[$row->id] = true;
                    $remainingPayment -= $rowTotal;
                } else {
                    $paymentStatusMap[$row->id] = false;
                }
            }
        }

        $fuels->transform(function ($fuel) use ($paymentStatusMap) {
            $fuel->is_paid = $paymentStatusMap[$fuel->id] ?? false;
            return $fuel;
        });

        // Calculate total station debt for KPI
        $totalDebtQuery = \App\Models\FuelStation::query();
        if ($companyIdForStation) {
            $totalDebtQuery->where('company_id', $companyIdForStation);
        }
        $totalDebt = $totalDebtQuery->get()->sum(function($station) {
            $totalAmount = (float) $station->fuels()->sum('total_cost');
            $totalPaid = (float) $station->payments()->sum('amount');
            return $totalAmount - $totalPaid;
        });

        return $this->successResponse([
            'fuels' => $fuels,
            'total_debt' => $totalDebt
        ], 'Yakıt kayıtları başarıyla getirildi.');
    }

    /**
     * Tek bir yakıt detayını getirir
     */
    public function show(Request $request, $id)
    {
        if (!$this->userHasPermission($request->user(), 'fuels.view')) {
            return $this->errorResponse('Bu işlem için yetkiniz bulunmamaktadır.', 403);
        }

        $companyId = $this->getCompanyId();
        
        $fuel = Fuel::where('company_id', $companyId)
            ->with(['vehicle:id,plate', 'station:id,name'])
            ->find($id);

        if (!$fuel) {
            return $this->errorResponse('Kayıt bulunamadı.', 404);
        }

        return $this->successResponse($fuel, 'Yakıt detayı başarıyla getirildi.');
    }

    /**
     * Yeni yakıt ekler
     */
    public function store(Request $request)
    {
        if (!$this->userHasPermission($request->user(), 'fuels.create')) {
            return $this->errorResponse('Bu işlem için yetkiniz bulunmamaktadır.', 403);
        }

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

        $validated['company_id'] = $this->getCompanyId();
        $validated['total_cost'] = $validated['liters'] * $validated['price_per_liter'];
        $validated['gross_total_cost'] = $validated['total_cost'];
        
        $vatRate = 0;
        if (!empty($validated['fuel_station_id'])) {
            $station = FuelStation::find($validated['fuel_station_id']);
            if ($station) {
                $vatRate = (float) $station->vat_rate;
            }
        }
        
        $validated['vat_rate'] = $vatRate;
        if ($vatRate > 0) {
            $validated['net_cost'] = round($validated['gross_total_cost'] / (1 + ($vatRate / 100)), 2);
            $validated['vat_amount'] = round($validated['gross_total_cost'] - $validated['net_cost'], 2);
        } else {
            $validated['net_cost'] = $validated['gross_total_cost'];
            $validated['vat_amount'] = 0;
        }

        $fuel = Fuel::create($validated);

        return $this->successResponse($fuel, 'Yakıt kaydı başarıyla eklendi.', 201);
    }

    /**
     * Yakıt günceller
     */
    public function update(Request $request, $id)
    {
        if (!$this->userHasPermission($request->user(), 'fuels.edit')) {
            return $this->errorResponse('Bu işlem için yetkiniz bulunmamaktadır.', 403);
        }

        $fuel = Fuel::where('company_id', $this->getCompanyId())->find($id);

        if (!$fuel) {
            return $this->errorResponse('Kayıt bulunamadı.', 404);
        }

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

        $validated['total_cost'] = $validated['liters'] * $validated['price_per_liter'];
        $validated['gross_total_cost'] = $validated['total_cost'];

        $vatRate = 0;
        if (!empty($validated['fuel_station_id'])) {
            $station = FuelStation::find($validated['fuel_station_id']);
            if ($station) {
                $vatRate = (float) $station->vat_rate;
            }
        }
        
        $validated['vat_rate'] = $vatRate;
        if ($vatRate > 0) {
            $validated['net_cost'] = round($validated['gross_total_cost'] / (1 + ($vatRate / 100)), 2);
            $validated['vat_amount'] = round($validated['gross_total_cost'] - $validated['net_cost'], 2);
        } else {
            $validated['net_cost'] = $validated['gross_total_cost'];
            $validated['vat_amount'] = 0;
        }

        $fuel->update($validated);

        return $this->successResponse($fuel, 'Yakıt kaydı başarıyla güncellendi.');
    }

    /**
     * Yakıt siler
     */
    public function destroy(Request $request, $id)
    {
        if (!$this->userHasPermission($request->user(), 'fuels.delete')) {
            return $this->errorResponse('Bu işlem için yetkiniz bulunmamaktadır.', 403);
        }

        $fuel = Fuel::where('company_id', $this->getCompanyId())->find($id);

        if (!$fuel) {
            return $this->errorResponse('Kayıt bulunamadı.', 404);
        }

        $fuel->delete();

        return $this->successResponse(null, 'Yakıt kaydı başarıyla silindi.');
    }

    /**
     * Form seçenekleri
     */
    public function options(Request $request)
    {
        $companyId = $this->getCompanyId();
        
        $vehicles = \App\Models\Fleet\Vehicle::where('company_id', $companyId)
            ->where('is_active', true)
            ->get(['id', 'plate']);
            
        $stations = \App\Models\FuelStation::where('company_id', $companyId)
            ->get(['id', 'name']);

        return $this->successResponse([
            'vehicles' => $vehicles,
            'stations' => $stations
        ], 'Form seçenekleri başarıyla getirildi.');
    }
}
