<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\TrafficPenalty;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PenaltyApiController extends BaseApiController
{
    /**
     * Ceza kayıtlarını listeler
     */
    public function index(Request $request)
    {
        if (!$this->userHasPermission($request->user(), 'penalties.view')) {
            return $this->errorResponse('Bu işlem için yetkiniz bulunmamaktadır.', 403);
        }

        $companyId = $this->getCompanyId();
        
        $query = TrafficPenalty::where('company_id', $companyId)
            ->with(['vehicle:id,plate']);

        if ($request->has('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('penalty_no', 'like', '%' . $search . '%')
                    ->orWhere('driver_name', 'like', '%' . $search . '%')
                    ->orWhere('penalty_article', 'like', '%' . $search . '%')
                    ->orWhere('penalty_location', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('penalty_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('penalty_date', '<=', $request->date_to);
        }

        $penalties = $query->orderByDesc('penalty_date')->get();

        return $this->successResponse($penalties, 'Ceza kayıtları başarıyla getirildi.');
    }

    /**
     * İstatistikleri getirir
     */
    public function statistics(Request $request)
    {
        if (!$this->userHasPermission($request->user(), 'penalties.view')) {
            return $this->errorResponse('Bu işlem için yetkiniz bulunmamaktadır.', 403);
        }

        $companyId = $this->getCompanyId();

        $allPenalties = TrafficPenalty::query()
            ->where('company_id', $companyId)
            ->get();

        $totalCount = $allPenalties->count();

        $unpaidCount = $allPenalties
            ->where('payment_status', 'unpaid')
            ->count();

        $totalAmount = (float) $allPenalties->sum('penalty_amount');

        $collectableAmount = (float) $allPenalties
            ->where('payment_status', 'unpaid')
            ->sum(function ($penalty) {
                return $penalty->calculated_payable_amount ?? $penalty->penalty_amount;
            });

        $thisMonthCount = $allPenalties
            ->filter(function ($penalty) {
                return Carbon::parse($penalty->penalty_date)->month === now()->month
                    && Carbon::parse($penalty->penalty_date)->year === now()->year;
            })
            ->count();

        return $this->successResponse([
            'totalCount' => $totalCount,
            'unpaidCount' => $unpaidCount,
            'totalAmount' => $totalAmount,
            'collectableAmount' => $collectableAmount,
            'thisMonthCount' => $thisMonthCount,
        ], 'İstatistikler başarıyla getirildi.');
    }

    /**
     * Tek bir ceza detayını getirir
     */
    public function show(Request $request, $id)
    {
        if (!$this->userHasPermission($request->user(), 'penalties.view')) {
            return $this->errorResponse('Bu işlem için yetkiniz bulunmamaktadır.', 403);
        }

        $companyId = $this->getCompanyId();
        
        $penalty = TrafficPenalty::where('company_id', $companyId)
            ->with(['vehicle:id,plate'])
            ->find($id);

        if (!$penalty) {
            return $this->errorResponse('Kayıt bulunamadı.', 404);
        }

        return $this->successResponse($penalty, 'Ceza detayı başarıyla getirildi.');
    }

    /**
     * Yeni ceza ekler
     */
    public function store(Request $request)
    {
        if (!$this->userHasPermission($request->user(), 'penalties.create')) {
            return $this->errorResponse('Bu işlem için yetkiniz bulunmamaktadır.', 403);
        }

        $validated = $request->validate([
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'penalty_no' => 'required|string|max:255',
            'penalty_date' => 'required|date',
            'penalty_time' => 'nullable|date_format:H:i',
            'penalty_article' => 'required|string|max:255',
            'penalty_location' => 'required|string|max:255',
            'penalty_amount' => 'required|numeric|min:0',
            'driver_name' => 'required|string|max:255',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'traffic_penalty_document' => 'nullable|file|max:10240',
            'payment_receipt' => 'nullable|file|max:10240',
        ]);

        $validated['company_id'] = $this->getCompanyId();
        
        // discount logic
        $validated['discounted_amount'] = round($validated['penalty_amount'] * 0.75, 2);
        
        if (!empty($validated['payment_date'])) {
            $paymentDate = Carbon::parse($validated['payment_date']);
            $discountDeadline = Carbon::parse($validated['penalty_date'])->addMonth();
            $validated['is_paid'] = true;
            $validated['payment_status'] = 'paid';
            $validated['paid_amount'] = $paymentDate->lte($discountDeadline) ? $validated['discounted_amount'] : $validated['penalty_amount'];
        } else {
            $validated['is_paid'] = false;
            $validated['payment_status'] = 'unpaid';
        }

        if ($request->hasFile('traffic_penalty_document')) {
            $validated['traffic_penalty_document'] = $request->file('traffic_penalty_document')
                ->store('traffic-penalties/documents', 'public');
        }

        if ($request->hasFile('payment_receipt')) {
            $validated['payment_receipt'] = $request->file('payment_receipt')
                ->store('traffic-penalties/receipts', 'public');
        }

        $penalty = TrafficPenalty::create($validated);

        return $this->successResponse($penalty, 'Ceza kaydı başarıyla eklendi.', 201);
    }

    /**
     * Ceza günceller
     */
    public function update(Request $request, $id)
    {
        if (!$this->userHasPermission($request->user(), 'penalties.edit')) {
            return $this->errorResponse('Bu işlem için yetkiniz bulunmamaktadır.', 403);
        }

        $penalty = TrafficPenalty::where('company_id', $this->getCompanyId())->find($id);

        if (!$penalty) {
            return $this->errorResponse('Kayıt bulunamadı.', 404);
        }

        $validated = $request->validate([
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'penalty_no' => 'required|string|max:255',
            'penalty_date' => 'required|date',
            'penalty_time' => 'nullable|date_format:H:i',
            'penalty_article' => 'required|string|max:255',
            'penalty_location' => 'required|string|max:255',
            'penalty_amount' => 'required|numeric|min:0',
            'driver_name' => 'required|string|max:255',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'traffic_penalty_document' => 'nullable|file|max:10240',
            'payment_receipt' => 'nullable|file|max:10240',
        ]);

        $validated['discounted_amount'] = round($validated['penalty_amount'] * 0.75, 2);

        if (!empty($validated['payment_date'])) {
            $paymentDate = Carbon::parse($validated['payment_date']);
            $discountDeadline = Carbon::parse($validated['penalty_date'])->addMonth();
            $validated['is_paid'] = true;
            $validated['payment_status'] = 'paid';
            $validated['paid_amount'] = $paymentDate->lte($discountDeadline) ? $validated['discounted_amount'] : $validated['penalty_amount'];
        } else {
            $validated['is_paid'] = false;
            $validated['payment_status'] = 'unpaid';
            $validated['paid_amount'] = null;
        }

        if ($request->hasFile('traffic_penalty_document')) {
            if ($penalty->traffic_penalty_document) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($penalty->traffic_penalty_document);
            }
            $validated['traffic_penalty_document'] = $request->file('traffic_penalty_document')
                ->store('traffic-penalties/documents', 'public');
        }

        if ($request->hasFile('payment_receipt')) {
            if ($penalty->payment_receipt) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($penalty->payment_receipt);
            }
            $validated['payment_receipt'] = $request->file('payment_receipt')
                ->store('traffic-penalties/receipts', 'public');
        }

        $penalty->update($validated);

        return $this->successResponse($penalty, 'Ceza kaydı başarıyla güncellendi.');
    }

    /**
     * Ceza siler
     */
    public function destroy(Request $request, $id)
    {
        if (!$this->userHasPermission($request->user(), 'penalties.delete')) {
            return $this->errorResponse('Bu işlem için yetkiniz bulunmamaktadır.', 403);
        }

        $penalty = TrafficPenalty::where('company_id', $this->getCompanyId())->find($id);

        if (!$penalty) {
            return $this->errorResponse('Kayıt bulunamadı.', 404);
        }

        $penalty->delete();

        return $this->successResponse(null, 'Ceza kaydı başarıyla silindi.');
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

        return $this->successResponse([
            'vehicles' => $vehicles,
        ], 'Form seçenekleri başarıyla getirildi.');
    }
}
