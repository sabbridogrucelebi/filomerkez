<?php

namespace App\Http\Controllers;

use App\Exports\TrafficPenaltyExport;
use App\Models\TrafficPenalty;
use App\Models\Fleet\Vehicle;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class TrafficPenaltyController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('penalties.view'), 403);

        $companyId = auth()->user()->company_id;

        $query = TrafficPenalty::query()
            ->with('vehicle')
            ->where('company_id', $companyId)
            ->latest('penalty_date')
            ->latest('id');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('penalty_no', 'like', '%' . $search . '%')
                    ->orWhere('driver_name', 'like', '%' . $search . '%')
                    ->orWhere('penalty_article', 'like', '%' . $search . '%')
                    ->orWhere('penalty_location', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('penalty_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('penalty_date', '<=', $request->date_to);
        }

        if ($request->filled('discount_status')) {
            if ($request->discount_status === 'active') {
                $query->where('payment_status', 'unpaid')
                    ->whereDate('penalty_date', '>=', now()->copy()->subMonth()->toDateString());
            }

            if ($request->discount_status === 'expired') {
                $query->where('payment_status', 'unpaid')
                    ->whereDate('penalty_date', '<', now()->copy()->subMonth()->toDateString());
            }

            if ($request->discount_status === 'critical') {
                $query->where('payment_status', 'unpaid')
                    ->whereDate('penalty_date', '>=', now()->copy()->subDays(27)->toDateString())
                    ->whereDate('penalty_date', '<=', now()->toDateString());
            }
        }

        $trafficPenalties = $query->paginate(10)->withQueryString();

        $vehicles = Vehicle::query()
            ->where('company_id', $companyId)
            ->orderBy('plate')
            ->get();

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
            ->sum(fn ($penalty) => $penalty->calculated_payable_amount);

        $thisMonthCount = $allPenalties
            ->filter(function ($penalty) {
                return optional($penalty->penalty_date)?->month === now()->month
                    && optional($penalty->penalty_date)?->year === now()->year;
            })
            ->count();

        $criticalCount = $allPenalties
            ->where('payment_status', 'unpaid')
            ->filter(function ($penalty) {
                return $penalty->remaining_days_for_discount > 0
                    && $penalty->remaining_days_for_discount <= 3;
            })
            ->count();

        return view('traffic-penalties.index', compact(
            'trafficPenalties',
            'vehicles',
            'totalCount',
            'unpaidCount',
            'totalAmount',
            'collectableAmount',
            'thisMonthCount',
            'criticalCount'
        ));
    }

    public function create()
    {
        abort_unless(auth()->user()->hasPermission('penalties.create'), 403);

        $vehicles = Vehicle::query()
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('plate')
            ->get();

        return view('traffic-penalties.create', compact('vehicles'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('penalties.create'), 403);

        $data = $request->validate([
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'penalty_no' => ['required', 'string', 'max:255', 'unique:traffic_penalties,penalty_no'],
            'penalty_date' => ['required', 'date'],
            'penalty_time' => ['nullable', 'date_format:H:i'],
            'penalty_article' => ['required', 'string', 'max:255'],
            'penalty_location' => ['required', 'string', 'max:255'],
            'penalty_amount' => ['required', 'numeric', 'min:0'],
            'driver_name' => ['required', 'string', 'max:255'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'traffic_penalty_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,heic,heif,doc,docx', 'max:10240'],
            'payment_receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,heic,heif,doc,docx', 'max:10240'],
        ]);

        $companyId = auth()->user()->company_id;
        $penaltyAmount = (float) $data['penalty_amount'];
        $discountedAmount = round($penaltyAmount * 0.75, 2);

        $paymentDate = !empty($data['payment_date']) ? Carbon::parse($data['payment_date']) : null;
        $penaltyDate = Carbon::parse($data['penalty_date']);
        $discountDeadline = $penaltyDate->copy()->addMonth();

        $paidAmount = null;
        $paymentStatus = 'unpaid';

        if ($paymentDate) {
            $paymentStatus = 'paid';
            $paidAmount = $paymentDate->lte($discountDeadline)
                ? $discountedAmount
                : $penaltyAmount;
        }

        $trafficPenaltyDocumentPath = null;
        $paymentReceiptPath = null;

        if ($request->hasFile('traffic_penalty_document')) {
            $trafficPenaltyDocumentPath = $request->file('traffic_penalty_document')
                ->store('traffic-penalties/documents', 'public');
        }

        if ($request->hasFile('payment_receipt')) {
            $paymentReceiptPath = $request->file('payment_receipt')
                ->store('traffic-penalties/receipts', 'public');
        }

        TrafficPenalty::create([
            'company_id' => $companyId,
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'penalty_no' => $data['penalty_no'],
            'penalty_date' => $data['penalty_date'],
            'penalty_time' => $data['penalty_time'] ?? null,
            'penalty_article' => $data['penalty_article'],
            'penalty_location' => $data['penalty_location'],
            'penalty_amount' => $penaltyAmount,
            'discounted_amount' => $discountedAmount,
            'driver_name' => $data['driver_name'],
            'payment_date' => $paymentDate ? $paymentDate->format('Y-m-d') : null,
            'paid_amount' => $paidAmount,
            'payment_status' => $paymentStatus,
            'traffic_penalty_document' => $trafficPenaltyDocumentPath,
            'payment_receipt' => $paymentReceiptPath,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('traffic-penalties.index')
            ->with('success', 'Trafik cezası kaydı başarıyla oluşturuldu.');
    }

    public function edit(TrafficPenalty $trafficPenalty)
    {
        abort_unless(auth()->user()->hasPermission('penalties.edit'), 403);
        abort_unless($trafficPenalty->company_id === auth()->user()->company_id, 403);

        $vehicles = Vehicle::query()
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('plate')
            ->get();

        return view('traffic-penalties.edit', compact('trafficPenalty', 'vehicles'));
    }

    public function update(Request $request, TrafficPenalty $trafficPenalty)
    {
        abort_unless(auth()->user()->hasPermission('penalties.edit'), 403);
        abort_unless($trafficPenalty->company_id === auth()->user()->company_id, 403);

        $data = $request->validate([
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'penalty_no' => ['required', 'string', 'max:255', 'unique:traffic_penalties,penalty_no,' . $trafficPenalty->id],
            'penalty_date' => ['required', 'date'],
            'penalty_time' => ['nullable', 'date_format:H:i'],
            'penalty_article' => ['required', 'string', 'max:255'],
            'penalty_location' => ['required', 'string', 'max:255'],
            'penalty_amount' => ['required', 'numeric', 'min:0'],
            'driver_name' => ['required', 'string', 'max:255'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'traffic_penalty_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,heic,heif,doc,docx', 'max:10240'],
            'payment_receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,heic,heif,doc,docx', 'max:10240'],
        ]);

        $penaltyAmount = (float) $data['penalty_amount'];
        $discountedAmount = round($penaltyAmount * 0.75, 2);

        $paymentDate = !empty($data['payment_date']) ? Carbon::parse($data['payment_date']) : null;
        $penaltyDate = Carbon::parse($data['penalty_date']);
        $discountDeadline = $penaltyDate->copy()->addMonth();

        $paidAmount = null;
        $paymentStatus = 'unpaid';

        if ($paymentDate) {
            $paymentStatus = 'paid';
            $paidAmount = $paymentDate->lte($discountDeadline)
                ? $discountedAmount
                : $penaltyAmount;
        }

        $trafficPenaltyDocumentPath = $trafficPenalty->traffic_penalty_document;
        $paymentReceiptPath = $trafficPenalty->payment_receipt;

        if ($request->hasFile('traffic_penalty_document')) {
            if ($trafficPenalty->traffic_penalty_document) {
                Storage::disk('public')->delete($trafficPenalty->traffic_penalty_document);
            }

            $trafficPenaltyDocumentPath = $request->file('traffic_penalty_document')
                ->store('traffic-penalties/documents', 'public');
        }

        if ($request->hasFile('payment_receipt')) {
            if ($trafficPenalty->payment_receipt) {
                Storage::disk('public')->delete($trafficPenalty->payment_receipt);
            }

            $paymentReceiptPath = $request->file('payment_receipt')
                ->store('traffic-penalties/receipts', 'public');
        }

        $trafficPenalty->update([
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'penalty_no' => $data['penalty_no'],
            'penalty_date' => $data['penalty_date'],
            'penalty_time' => $data['penalty_time'] ?? null,
            'penalty_article' => $data['penalty_article'],
            'penalty_location' => $data['penalty_location'],
            'penalty_amount' => $penaltyAmount,
            'discounted_amount' => $discountedAmount,
            'driver_name' => $data['driver_name'],
            'payment_date' => $paymentDate ? $paymentDate->format('Y-m-d') : null,
            'paid_amount' => $paidAmount,
            'payment_status' => $paymentStatus,
            'traffic_penalty_document' => $trafficPenaltyDocumentPath,
            'payment_receipt' => $paymentReceiptPath,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('traffic-penalties.index')
            ->with('success', 'Trafik cezası kaydı güncellendi.');
    }

    public function destroy(TrafficPenalty $trafficPenalty)
    {
        abort_unless(auth()->user()->hasPermission('penalties.delete'), 403);
        abort_unless($trafficPenalty->company_id === auth()->user()->company_id, 403);

        if ($trafficPenalty->traffic_penalty_document) {
            Storage::disk('public')->delete($trafficPenalty->traffic_penalty_document);
        }

        if ($trafficPenalty->payment_receipt) {
            Storage::disk('public')->delete($trafficPenalty->payment_receipt);
        }

        $trafficPenalty->delete();

        return redirect()
            ->route('traffic-penalties.index')
            ->with('success', 'Trafik cezası kaydı silindi.');
    }

    public function quickPay(Request $request, TrafficPenalty $trafficPenalty)
    {
        abort_unless(auth()->user()->hasPermission('penalties.edit'), 403);
        abort_unless($trafficPenalty->company_id === auth()->user()->company_id, 403);

        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'payment_receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,heic,heif,doc,docx', 'max:10240'],
        ]);

        $paymentDate = Carbon::parse($data['payment_date']);
        $discountDeadline = Carbon::parse($trafficPenalty->penalty_date)->addMonth();

        $paidAmount = $paymentDate->lte($discountDeadline)
            ? (float) $trafficPenalty->discounted_amount
            : (float) $trafficPenalty->penalty_amount;

        $paymentReceiptPath = $trafficPenalty->payment_receipt;

        if ($request->hasFile('payment_receipt')) {
            if ($trafficPenalty->payment_receipt) {
                Storage::disk('public')->delete($trafficPenalty->payment_receipt);
            }

            $paymentReceiptPath = $request->file('payment_receipt')
                ->store('traffic-penalties/receipts', 'public');
        }

        $trafficPenalty->update([
            'payment_date' => $paymentDate->format('Y-m-d'),
            'paid_amount' => $paidAmount,
            'payment_status' => 'paid',
            'payment_receipt' => $paymentReceiptPath,
        ]);

        return redirect()
            ->route('traffic-penalties.index')
            ->with('success', 'Ceza hızlı ödeme ile kapatıldı.');
    }

    public function exportExcel(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('penalties.view'), 403);

        return Excel::download(
            new TrafficPenaltyExport($request->all(), auth()->user()->company_id),
            'trafik-cezalari.xlsx'
        );
    }

    public function exportPdf(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('penalties.view'), 403);

        $query = TrafficPenalty::query()
            ->with('vehicle')
            ->where('company_id', auth()->user()->company_id)
            ->latest('penalty_date')
            ->latest('id');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('penalty_no', 'like', '%' . $search . '%')
                    ->orWhere('driver_name', 'like', '%' . $search . '%')
                    ->orWhere('penalty_article', 'like', '%' . $search . '%')
                    ->orWhere('penalty_location', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('penalty_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('penalty_date', '<=', $request->date_to);
        }

        if ($request->filled('discount_status')) {
            if ($request->discount_status === 'active') {
                $query->where('payment_status', 'unpaid')
                    ->whereDate('penalty_date', '>=', now()->copy()->subMonth()->toDateString());
            }

            if ($request->discount_status === 'expired') {
                $query->where('payment_status', 'unpaid')
                    ->whereDate('penalty_date', '<', now()->copy()->subMonth()->toDateString());
            }

            if ($request->discount_status === 'critical') {
                $query->where('payment_status', 'unpaid')
                    ->whereDate('penalty_date', '>=', now()->copy()->subDays(27)->toDateString())
                    ->whereDate('penalty_date', '<=', now()->toDateString());
            }
        }

        $penalties = $query->get();

        $pdf = Pdf::loadView('exports.traffic-penalties-pdf', [
            'penalties' => $penalties,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('trafik-cezalari.pdf');
    }
}