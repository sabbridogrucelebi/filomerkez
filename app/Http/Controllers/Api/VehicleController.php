<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fleet\Vehicle;
use App\Models\Fleet\Driver;
use App\Models\Trip;
use App\Models\Fuel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $query = Vehicle::where('company_id', $companyId)->with(['drivers', 'documents' => fn($q) => $q->whereNull('archived_at')]);

        if ($request->filter === 'upcoming_inspection') {
            $query->where(function ($q) {
                $q->whereNotNull('inspection_date')
                  ->where('inspection_date', '<=', now()->addDays(10))
                  ->orWhereHas('documents', function ($doc) {
                      $doc->whereIn('document_type', ['Muayene', 'Muayene Raporu'])
                          ->whereNotNull('end_date')
                          ->where('end_date', '<=', now()->addDays(10))
                          ->whereNull('archived_at');
                  });
            });
        } elseif ($request->filter === 'upcoming_insurance') {
            $query->where(function ($q) {
                $q->whereNotNull('insurance_end_date')
                  ->where('insurance_end_date', '<=', now()->addDays(10))
                  ->orWhereHas('documents', function ($doc) {
                      $doc->whereIn('document_type', ['Sigorta', 'Sigorta Poliçesi'])
                          ->whereNotNull('end_date')
                          ->where('end_date', '<=', now()->addDays(10))
                          ->whereNull('archived_at');
                  });
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'passive') {
                $query->where('is_active', false);
            }
        }

        $vehicles = $query->get();

        $formatted = $vehicles->map(function ($vehicle) {
            $lastFuel = $vehicle->fuels()->latest('date')->latest('id')->first();
            $currentKm = $lastFuel ? $lastFuel->km : ($vehicle->current_km ?? 0);

            $driver = $vehicle->drivers()->latest()->first();
            
            $insuranceDoc = $vehicle->documents->whereIn('document_type', ['Sigorta', 'Sigorta Poliçesi'])->sortByDesc('end_date')->first();
            $docDate = $insuranceDoc && $insuranceDoc->end_date ? \Carbon\Carbon::parse($insuranceDoc->end_date) : null;
            $vehDate = $vehicle->insurance_end_date ? \Carbon\Carbon::parse($vehicle->insurance_end_date) : null;
            $insuranceDate = $docDate && $vehDate ? ($docDate->gt($vehDate) ? $docDate : $vehDate) : ($docDate ?: $vehDate);

            return [
                'id' => $vehicle->id,
                'plate' => $vehicle->plate,
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'brand_model' => trim($vehicle->brand . ' ' . $vehicle->model),
                'vehicle_type' => $vehicle->vehicle_type,
                'model_year' => $vehicle->model_year,
                'status' => $vehicle->is_active ? 'active' : 'passive',
                'current_km' => (int) $currentKm,
                'inspection_date' => $vehicle->inspection_date,
                'insurance_end_date' => $insuranceDate ? $insuranceDate->toDateString() : null,
                'engine_no' => $vehicle->engine_no,
                'chassis_no' => $vehicle->chassis_no,
                'fuel_type' => $vehicle->fuel_type,
                'license_serial_no' => $vehicle->license_serial_no,
                'license_owner' => $vehicle->license_owner,
                'owner_tax_or_tc_no' => $vehicle->owner_tax_or_tc_no,
                'color' => $vehicle->color,
                'seat_count' => $vehicle->seat_count,
                'driver' => $driver ? $driver->full_name : null,
            ];
        });

        $kpi = [
            'total' => Vehicle::where('company_id', $companyId)->count(),
            'upcoming_inspection' => Vehicle::where('company_id', $companyId)
                ->where(function ($q) {
                    $q->whereNotNull('inspection_date')
                      ->where('inspection_date', '<=', now()->addDays(10))
                      ->orWhereHas('documents', function ($doc) {
                          $doc->whereIn('document_type', ['Muayene', 'Muayene Raporu'])
                              ->whereNotNull('end_date')
                              ->where('end_date', '<=', now()->addDays(10))
                              ->whereNull('archived_at');
                      });
                })->count(),
            'upcoming_insurance' => Vehicle::where('company_id', $companyId)
                ->where(function ($q) {
                    $q->whereNotNull('insurance_end_date')
                      ->where('insurance_end_date', '<=', now()->addDays(10))
                      ->orWhereHas('documents', function ($doc) {
                          $doc->whereIn('document_type', ['Sigorta', 'Sigorta Poliçesi'])
                              ->whereNotNull('end_date')
                              ->where('end_date', '<=', now()->addDays(10))
                              ->whereNull('archived_at');
                      });
                })
                ->count(),
        ];

        return response()->json([
            'vehicles' => $formatted,
            'kpi' => $kpi,
        ]);
    }

    public function store(Request $request)
    {
        $company = $request->user()->company;
        if (!is_null($company->max_vehicles) && $company->vehicles()->count() >= $company->max_vehicles) {
            return response()->json([
                'message' => 'Maksimum araç kotanıza (' . $company->max_vehicles . ') ulaştınız. Lütfen paketinizi yükseltin.'
            ], 403);
        }

        $validated = $request->validate([
            'plate' => ['required', 'string', 'max:20', Rule::unique('vehicles', 'plate')->whereNull('deleted_at')],
            'device_imei' => 'nullable|string|max:20',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'vehicle_type' => 'nullable|string|max:100',
            'model_year' => 'nullable|integer',
            'current_km' => 'nullable|integer',
            'engine_no' => 'nullable|string|max:100',
            'chassis_no' => 'nullable|string|max:100',
            'fuel_type' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
            'seat_count' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $validated['company_id'] = $request->user()->company_id;
        $validated['status'] = 'active';

        $vehicle = Vehicle::create($validated);

        return response()->json(['message' => 'Araç başarıyla eklendi.', 'vehicle' => $vehicle], 201);
    }


    public function show(Request $request, Vehicle $vehicle)
    {
        if ($vehicle->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Yetkisiz erişim.'], 403);
        }

        // KM Hesabı: En son yakıt kaydındaki KM, yoksa aracın kendi KM'si
        $lastFuel = $vehicle->fuels()->latest('date')->latest('id')->first();
        $currentKm = $lastFuel ? $lastFuel->km : ($vehicle->current_km ?? 0);

        // Finansal İstatistikler
        $income = \App\Models\Trip::where('vehicle_id', $vehicle->id)->sum('trip_price');
        $fuel = \App\Models\Fuel::where('vehicle_id', $vehicle->id)->sum('total_cost');
        $salary = \App\Models\Payroll::whereHas('driver', function ($q) use ($vehicle) {
            $q->where('vehicle_id', $vehicle->id);
        })->sum('net_salary');
        $profit = $income - ($fuel + $salary);

        // Personel / Şoför
        $driver = $vehicle->drivers()->latest()->first();

        // Görsel: Öne çıkarılan yoksa ilk görsel
        $image = $vehicle->images()->where('is_featured', true)->first() ?: $vehicle->images()->orderBy('sort_order')->first();
        $imageUrl = null;
        if ($image) {
            $imageUrl = url(Storage::url($image->file_path));
        }

        $stats = [
            'revenue' => (float) $income,
            'fuel' => (float) $fuel,
            'salary' => (float) $salary,
            'net' => (float) $profit,
        ];

        return response()->json([
            'vehicle' => array_merge($vehicle->toArray(), [
                'current_km' => (int) $currentKm,
                'driver' => $driver ? $driver->full_name : null,
                'image_url' => $imageUrl,
            ]),
            'stats' => $stats,
        ]);
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        if ($vehicle->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Yetkisiz erişim.'], 403);
        }

        $validated = $request->validate([
            'plate' => ['required', 'string', 'max:20', Rule::unique('vehicles', 'plate')->ignore($vehicle->id)->whereNull('deleted_at')],
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'vehicle_type' => 'nullable|string|max:100',
            'model_year' => 'nullable|integer',
            'current_km' => 'nullable|integer',
            'engine_no' => 'nullable|string|max:100',
            'chassis_no' => 'nullable|string|max:100',
            'fuel_type' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
            'seat_count' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $vehicle->update($validated);

        return response()->json(['message' => 'Araç başarıyla güncellendi.', 'vehicle' => $vehicle]);
    }

    public function destroy(Request $request, Vehicle $vehicle)
    {
        if ($vehicle->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Yetkisiz erişim.'], 403);
        }

        DB::transaction(function () use ($vehicle) {
            $vehicle->fuels()->delete();
            $vehicle->documents()->delete();
            $vehicle->trafficPenalties()->delete();
            $vehicle->maintenances()->delete();
            \App\Models\Trip::where('vehicle_id', $vehicle->id)->update(['vehicle_id' => null]);
            Driver::where('vehicle_id', $vehicle->id)->update(['vehicle_id' => null]);
            $vehicle->delete();
        });

        return response()->json(['message' => 'Araç başarıyla silindi.']);
    }

    public function documents(Request $request, Vehicle $vehicle)
    {
        if ($vehicle->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Yetkisiz erişim.'], 403);
        }

        $documents = $vehicle->documents()
            ->latest()
            ->get()
            ->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'title' => $doc->document_name,
                    'type' => $doc->document_type,
                    'file_url' => url(Storage::url($doc->file_path)),
                    'start_date' => $doc->start_date ? $doc->start_date->toDateString() : null,
                    'end_date' => $doc->end_date ? $doc->end_date->toDateString() : null,
                    'is_expired' => $doc->end_date ? $doc->end_date->isPast() : false,
                ];
            });

        return response()->json(['documents' => $documents]);
    }

    public function fuels(Request $request, Vehicle $vehicle)
    {
        if ($vehicle->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Yetkisiz erişim.'], 403);
        }

        $query = $vehicle->fuels()->with('station');

        // Filtering
        if ($request->filled('start_date')) {
            $query->where('date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('date', '<=', $request->end_date);
        }
        if ($request->filled('station')) {
            $query->where(function($q) use ($request) {
                $q->where('station_name', 'like', '%' . $request->station . '%')
                  ->orWhereHas('station', function($sq) use ($request) {
                      $sq->where('name', 'like', '%' . $request->station . '%');
                  });
            });
        }
        if ($request->filled('fuel_type')) {
            $query->where('fuel_type', $request->fuel_type);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('station_name', 'like', "%$search%")
                  ->orWhere('notes', 'like', "%$search%")
                  ->orWhere('km', 'like', "%$search%")
                  ->orWhereHas('station', function($sq) use ($search) {
                      $sq->where('name', 'like', "%$search%");
                  });
            });
        }

        $fuels = $query->latest('date')->latest('id')->get();

        // KM Difference and KM/L calculations (need sorted ascending for this)
        $sortedAsc = $fuels->sortBy('date')->sortBy('id')->values();
        $previousRow = null;
        $paymentStatusMap = [];

        // Payment status logic similar to web
        $stationSummaries = \App\Models\FuelStation::with(['fuels', 'payments'])->get()->map(function($s) {
            return [
                'name' => mb_strtolower(trim($s->name)),
                'total_paid' => (float)$s->payments->sum('amount')
            ];
        })->pluck('total_paid', 'name');

        $stationRowsGrouped = $fuels->groupBy(function($row) {
            return mb_strtolower(trim($row->station?->name ?? $row->station_name ?? ''));
        });

        foreach ($stationRowsGrouped as $stationKey => $stationRows) {
            $remainingPayment = (float)($stationSummaries[$stationKey] ?? 0);
            $sortedStationRows = $stationRows->sortBy('date')->sortBy('id')->values();
            foreach ($sortedStationRows as $row) {
                $rowTotal = (float)($row->total_cost ?? 0);
                if ($rowTotal > 0 && $remainingPayment >= $rowTotal) {
                    $paymentStatusMap[$row->id] = true;
                    $remainingPayment -= $rowTotal;
                } else {
                    $paymentStatusMap[$row->id] = false;
                }
            }
        }

        $processed = [];
        $lastRow = null;
        foreach ($sortedAsc as $row) {
            $kmDiff = ($lastRow && $row->km > $lastRow->km) ? ($row->km - $lastRow->km) : 0;
            $kmPerLiter = ($kmDiff > 0 && $row->liters > 0) ? ($kmDiff / $row->liters) : 0;
            
            $processed[] = [
                'id' => $row->id,
                'date' => $row->date->toDateString(),
                'station_name' => $row->station?->name ?? $row->station_name,
                'total_cost' => (float)$row->total_cost,
                'km' => (int)$row->km,
                'km_diff' => (int)$kmDiff,
                'km_per_liter' => (float)number_format($kmPerLiter, 2, '.', ''),
                'liters' => (float)$row->liters,
                'price_per_liter' => (float)$row->price_per_liter,
                'fuel_type' => $row->fuel_type,
                'is_paid' => $paymentStatusMap[$row->id] ?? false,
                'notes' => $row->notes
            ];
            $lastRow = $row;
        }

        // Final output should be latest first
        $processed = array_reverse($processed);

        // Summary Statistics (Current month)
        $now = now();
        $monthTotal = $vehicle->fuels()->whereMonth('date', $now->month)->whereYear('date', $now->year)->sum('total_cost');
        $monthLiters = $vehicle->fuels()->whereMonth('date', $now->month)->whereYear('date', $now->year)->sum('liters');
        $monthCount = $vehicle->fuels()->whereMonth('date', $now->month)->whereYear('date', $now->year)->count();
        
        $monthFuels = $vehicle->fuels()->whereMonth('date', $now->month)->whereYear('date', $now->year)->orderBy('km')->get();
        $monthKm = 0;
        if ($monthFuels->count() >= 2) {
            $monthKm = $monthFuels->last()->km - $monthFuels->first()->km;
        }

        $summary = [
            'month_total' => (float)$monthTotal,
            'all_time_total' => (float)$vehicle->fuels()->sum('total_cost'),
            'month_km' => (int)$monthKm,
            'month_first_km' => $monthFuels->first()?->km ?? 0,
            'month_last_km' => $monthFuels->last()?->km ?? 0,
            'month_liters' => (float)$monthLiters,
            'month_count' => (int)$monthCount,
            'last_km' => (int)($vehicle->fuels()->max('km') ?? 0)
        ];

        return response()->json([
            'fuels' => $processed,
            'summary' => $summary,
            'options' => [
                'stations' => \App\Models\FuelStation::pluck('name')->toArray(),
                'fuel_types' => ['Dizel', 'Benzin', 'LPG', 'AdBlue']
            ]
        ]);
    }

    public function storeFuel(Request $request, Vehicle $vehicle)
    {
        if ($vehicle->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Yetkisiz erişim.'], 403);
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'km' => 'required|integer',
            'liters' => 'required|numeric',
            'price_per_liter' => 'nullable|numeric',
            'total_cost' => 'required|numeric',
            'fuel_type' => 'nullable|string|max:50',
            'station_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $fuel = $vehicle->fuels()->create(array_merge($validated, [
            'company_id' => $request->user()->company_id,
            'price_per_liter' => $validated['price_per_liter'] ?? ($validated['total_cost'] / $validated['liters']),
        ]));

        return response()->json(['message' => 'Yakıt kaydı başarıyla eklendi.', 'fuel' => $fuel], 201);
    }
    public function maintenances(Request $request, Vehicle $vehicle)
    {
        if ($vehicle->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Yetkisiz erişim.'], 403);
        }

        $query = $vehicle->maintenances();

        // Filtering
        if ($request->filled('start_date')) {
            $query->where('service_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('service_date', '<=', $request->end_date);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                  ->orWhere('maintenance_type', 'like', "%$search%")
                  ->orWhere('service_name', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%");
            });
        }

        $maintenances = $query->latest('service_date')->latest('id')->get()->map(function($m) {
            return [
                'id' => $m->id,
                'title' => $m->title,
                'type' => $m->maintenance_type,
                'date' => $m->service_date ? $m->service_date->toDateString() : null,
                'km' => (int)$m->km,
                'next_km' => $m->next_service_km ? (int)$m->next_service_km : null,
                'next_date' => $m->next_service_date ? $m->next_service_date->toDateString() : null,
                'service_name' => $m->service_name,
                'amount' => (float)$m->amount,
                'description' => $m->description
            ];
        });

        return response()->json(['maintenances' => $maintenances]);
    }

    public function exportMaintenancesPdf(Request $request, Vehicle $vehicle)
    {
        // Web tarayıcıdan (window.open) gelindiğinde token query string'de olabilir
        if (!$request->user() && $request->has('token')) {
            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($request->token);
            if ($accessToken && $accessToken->tokenable) {
                auth()->login($accessToken->tokenable);
            }
        }

        if (!$request->user() || $vehicle->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Yetkisiz erişim.'], 403);
        }

        $query = $vehicle->maintenances();

        if ($request->filled('start_date')) {
            $query->where('service_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('service_date', '<=', $request->end_date);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                  ->orWhere('service_name', 'like', "%$search%");
            });
        }

        $maintenances = $query->latest('service_date')->latest('id')->get();
        $totalAmount = (float) $maintenances->sum('amount');

        $filters = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('maintenances.pdf', [
            'maintenances' => $maintenances,
            'filters' => $filters,
            'generatedAt' => now(),
            'totalAmount' => $totalAmount,
        ])->setPaper('a4', 'landscape');
        
        $filename = Str::slug($vehicle->plate) . '_bakimlari.pdf';
        
        return $pdf->download($filename);
    }
    public function penalties(Request $request, Vehicle $vehicle)
    {
        if ($vehicle->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Yetkisiz erişim.'], 403);
        }

        $query = $vehicle->trafficPenalties();

        if ($request->filled('start_date')) {
            $query->where('penalty_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('penalty_date', '<=', $request->end_date);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('penalty_no', 'like', "%$search%")
                  ->orWhere('penalty_article', 'like', "%$search%")
                  ->orWhere('penalty_location', 'like', "%$search%")
                  ->orWhere('driver_name', 'like', "%$search%");
            });
        }

        $penalties = $query->latest('penalty_date')->latest('id')->get()->map(function($p) {
            return [
                'id' => $p->id,
                'penalty_no' => $p->penalty_no,
                'plate' => $p->vehicle?->plate,
                'driver_name' => $p->driver_name,
                'date' => $p->penalty_date ? $p->penalty_date->toDateString() : null,
                'time' => $p->penalty_time ? \Carbon\Carbon::parse($p->penalty_time)->format('H:i') : null,
                'discount_deadline' => $p->discount_deadline ? $p->discount_deadline->toDateString() : null,
                'article' => $p->penalty_article,
                'location' => $p->penalty_location,
                'status' => $p->payment_status,
                'payment_date' => $p->payment_date ? $p->payment_date->toDateString() : null,
                'amount' => (float)$p->penalty_amount,
                'discounted_amount' => (float)$p->discounted_amount,
                'paid_amount' => (float)$p->paid_amount,
                'is_discount_eligible' => $p->is_discount_eligible,
                'traffic_penalty_document' => $p->traffic_penalty_document ? asset('storage/' . $p->traffic_penalty_document) : null,
                'payment_receipt' => $p->payment_receipt ? asset('storage/' . $p->payment_receipt) : null,
                'notes' => $p->notes
            ];
        });

    }

    public function gallery(Request $request, Vehicle $vehicle)
    {
        if ($vehicle->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Yetkisiz erişim.'], 403);
        }

        $images = $vehicle->images()->get()->map(function($img) {
            return [
                'id' => $img->id,
                'title' => $img->title ?: $img->image_type_label,
                'type_label' => $img->image_type_label,
                'source_label' => $img->upload_source_label,
                'is_featured' => $img->is_featured,
                'url' => asset('storage/' . $img->file_path),
            ];
        });

        $upload_link = route('vehicles.public-images.form', [
            'vehicle' => $vehicle->id,
            'token' => $vehicle->public_image_upload_token
        ]);

        return response()->json([
            'images' => $images,
            'upload_link' => $upload_link
        ]);
    }

    public function uploadImage(Request $request, Vehicle $vehicle)
    {
        if ($vehicle->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Yetkisiz erişim.'], 403);
        }

        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:20480',
            'title' => 'nullable|string|max:255',
            'image_type' => 'required|string',
            'is_featured' => 'nullable|boolean',
        ]);

        $path = $request->file('image')->store('vehicle-images', 'public');
        
        $isFeatured = $request->boolean('is_featured');
        if ($isFeatured || $vehicle->images()->count() === 0) {
            $vehicle->images()->update(['is_featured' => false]);
            $isFeatured = true;
        }

        $image = $vehicle->images()->create([
            'title' => $request->title,
            'file_path' => $path,
            'is_featured' => $isFeatured,
            'sort_order' => 0,
            'image_type' => $request->image_type,
            'upload_source' => 'panel',
        ]);

        return response()->json(['message' => 'Resim başarıyla yüklendi.']);
    }

    public function setFeaturedImage(Request $request, Vehicle $vehicle, \App\Models\Fleet\VehicleImage $image)
    {
        if ($vehicle->company_id !== $request->user()->company_id || $image->vehicle_id !== $vehicle->id) {
            return response()->json(['message' => 'Yetkisiz erişim.'], 403);
        }

        $vehicle->images()->update(['is_featured' => false]);
        $image->update(['is_featured' => true]);

        return response()->json(['message' => 'Vitrin resmi güncellendi.']);
    }

    public function deleteImage(Request $request, Vehicle $vehicle, \App\Models\Fleet\VehicleImage $image)
    {
        if ($vehicle->company_id !== $request->user()->company_id || $image->vehicle_id !== $vehicle->id) {
            return response()->json(['message' => 'Yetkisiz erişim.'], 403);
        }

        \Illuminate\Support\Facades\Storage::disk('public')->delete($image->file_path);
        $image->delete();

        return response()->json(['message' => 'Resim silindi.']);
    }

    public function reports(Request $request, Vehicle $vehicle)
    {
        if ($vehicle->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Yetkisiz erişim.'], 403);
        }

        $reportsMonth = $request->input('reports_month', now()->format('Y-m'));
        $reportsStartDate = \Carbon\Carbon::parse($reportsMonth . '-01')->startOfMonth();
        $reportsEndDate = $reportsStartDate->copy()->endOfMonth();

        $vehicleTrips = \App\Models\Trip::with(['serviceRoute.customer'])
            ->where(function ($q) use ($vehicle) {
                $q->where('vehicle_id', $vehicle->id)
                  ->orWhere('morning_vehicle_id', $vehicle->id)
                  ->orWhere('evening_vehicle_id', $vehicle->id);
            })
            ->whereBetween('trip_date', [$reportsStartDate, $reportsEndDate])
            ->get();

        $monthlyReports = [];
        $reportTotals = ['morning' => 0, 'evening' => 0, 'income' => 0];

        foreach ($vehicleTrips as $trip) {
            $route = $trip->serviceRoute;
            if (!$route) continue;
            $customer = $route->customer;
            $customerId = $customer ? $customer->id : 0;
            $customerName = $customer ? $customer->company_name : 'Diğer Seferler';

            if (!isset($monthlyReports[$customerId])) {
                $monthlyReports[$customerId] = [
                    'id' => $customerId,
                    'customer_name' => $customerName,
                    'morning_count' => 0,
                    'evening_count' => 0,
                    'total_price' => 0,
                ];
            }

            $didMorning = false;
            if ($trip->morning_vehicle_id == $vehicle->id) {
                $didMorning = true;
            } elseif ($trip->vehicle_id == $vehicle->id && !$trip->morning_vehicle_id && ($route->service_type == 'morning' || $route->service_type == 'both')) {
                $didMorning = true;
            }

            $didEvening = false;
            if ($trip->evening_vehicle_id == $vehicle->id) {
                $didEvening = true;
            } elseif ($trip->vehicle_id == $vehicle->id && !$trip->evening_vehicle_id && ($route->service_type == 'evening' || $route->service_type == 'both')) {
                $didEvening = true;
            }

            if ($didMorning) {
                $monthlyReports[$customerId]['morning_count']++;
                $reportTotals['morning']++;
            }
            if ($didEvening) {
                $monthlyReports[$customerId]['evening_count']++;
                $reportTotals['evening']++;
            }

            if ($trip->vehicle_id == $vehicle->id) {
                $price = $trip->trip_price ?? 0;
                $monthlyReports[$customerId]['total_price'] += $price;
                $reportTotals['income'] += $price;
            }
        }

        return response()->json([
            'reports_month' => $reportsMonth,
            'totals' => $reportTotals,
            'details' => array_values($monthlyReports)
        ]);
    }
}
