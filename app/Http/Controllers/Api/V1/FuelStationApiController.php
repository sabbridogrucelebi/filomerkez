<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\FuelStation;
use App\Services\FuelPricingService;
use Illuminate\Http\Request;

class FuelStationApiController extends BaseApiController
{
    /**
     * Akaryakıt istasyonu kayıtlarını listeler
     */
    public function index(Request $request)
    {
        if (!$this->userHasPermission($request->user(), 'finance.view')) {
            return $this->errorResponse('Bu işlem için yetkiniz bulunmamaktadır.', 403);
        }

        $companyId = $this->getCompanyId();
        
        $stations = FuelStation::with(['fuels', 'payments'])
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get()
            ->map(function ($station) {
                $priced = app(FuelPricingService::class)->summarizeFuels($station->fuels, $station);
                $station->total_liters = $priced['total_liters'];
                $station->gross_total = $priced['gross_total'];
                $station->discount_total = $priced['discount_total'];
                $station->net_debt = $priced['net_total'];
                $station->total_paid = (float) $station->payments->sum('amount');
                $station->current_debt = $station->net_debt - $station->total_paid;
                
                // Hide relations to save payload size
                unset($station->fuels);
                unset($station->payments);
                
                return $station;
            });

        return $this->successResponse($stations, 'İstasyon kayıtları başarıyla getirildi.');
    }

    /**
     * Yeni istasyon ekler
     */
    public function store(Request $request)
    {
        if (!$this->userHasPermission($request->user(), 'finance.edit')) {
            return $this->errorResponse('Bu işlem için yetkiniz bulunmamaktadır.', 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
        ]);

        if (empty($validated['discount_type'])) {
            $validated['discount_value'] = 0;
        }

        $validated['company_id'] = $this->getCompanyId();

        $station = FuelStation::create($validated);

        return $this->successResponse($station, 'İstasyon başarıyla eklendi.', 201);
    }

    /**
     * İstasyon detayını getirir
     */
    public function show(Request $request, $id)
    {
        if (!$this->userHasPermission($request->user(), 'finance.view')) {
            return $this->errorResponse('Bu işlem için yetkiniz bulunmamaktadır.', 403);
        }

        $station = FuelStation::where('company_id', $this->getCompanyId())->findOrFail($id);

        return $this->successResponse($station, 'İstasyon detayları getirildi.');
    }

    /**
     * İstasyon günceller
     */
    public function update(Request $request, $id)
    {
        if (!$this->userHasPermission($request->user(), 'finance.edit')) {
            return $this->errorResponse('Bu işlem için yetkiniz bulunmamaktadır.', 403);
        }

        $station = FuelStation::where('company_id', $this->getCompanyId())->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
        ]);

        if (empty($validated['discount_type'])) {
            $validated['discount_value'] = 0;
        }

        $station->update($validated);

        return $this->successResponse($station, 'İstasyon başarıyla güncellendi.');
    }

    /**
     * İstasyon siler
     */
    public function destroy(Request $request, $id)
    {
        if (!$this->userHasPermission($request->user(), 'finance.delete')) {
            return $this->errorResponse('Bu işlem için yetkiniz bulunmamaktadır.', 403);
        }

        $station = FuelStation::where('company_id', $this->getCompanyId())->findOrFail($id);
        $station->delete();

        return $this->successResponse(null, 'İstasyon başarıyla silindi.');
    }

    /**
     * İstasyon ekstresini getirir
     */
    public function statement(Request $request, $id)
    {
        if (!$this->userHasPermission($request->user(), 'finance.view')) {
            return $this->errorResponse('Bu işlem için yetkiniz bulunmamaktadır.', 403);
        }

        $station = FuelStation::where('company_id', $this->getCompanyId())->findOrFail($id);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $fuels = $station->fuels()
            ->with('vehicle:id,plate')
            ->when($startDate, fn ($q) => $q->whereDate('date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('date', '<=', $endDate))
            ->orderByDesc('date')
            ->get();

        $payments = $station->payments()
            ->when($startDate, fn ($q) => $q->whereDate('payment_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('payment_date', '<=', $endDate))
            ->orderByDesc('payment_date')
            ->get();

        $summary = [
            'total_liters' => (float) $fuels->sum('liters'),
            'total_fuel_cost' => (float) $fuels->sum('total_cost'),
            'total_paid' => (float) $payments->sum('amount'),
            'current_debt' => (float) $fuels->sum('total_cost') - (float) $payments->sum('amount'),
        ];

        return $this->successResponse([
            'station' => $station,
            'summary' => $summary,
            'fuels' => $fuels,
            'payments' => $payments
        ], 'İstasyon ekstresi başarıyla getirildi.');
    }
}
