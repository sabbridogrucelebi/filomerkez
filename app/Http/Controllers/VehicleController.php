<?php

namespace App\Http\Controllers;

use ZipArchive;
use App\Models\Fuel;
use App\Models\Trip;
use App\Models\Document;
use App\Models\Payroll;
use App\Models\FuelStation;
use App\Models\TrafficPenalty;
use Illuminate\Http\Request;
use App\Exports\VehiclesExport;
use App\Models\Fleet\Vehicle;
use App\Models\VehicleMaintenance;
use App\Models\Fleet\VehicleImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\ArventoService;
use App\Models\VehicleTrackingSetting;
use App\Models\Fleet\Driver;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        // Güvenlik: Route middleware'de permission:vehicles.view kontrolü var
        // ama burada ikinci bir güvenlik halkası ekliyoruz (defense-in-depth)
        if (!auth()->user()->hasPermission('vehicles.view')) {
            abort(403, 'Bu sayfayı görüntüleme yetkiniz yok.');
        }

        // Filtre girdilerini whitelist ile temizle (XSS / SQLi karşıtı)
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type'   => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:active,passive,all'],
            'filter' => ['nullable', 'in:upcoming_inspection,upcoming_insurance,no_driver'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $type   = trim((string) ($filters['type'] ?? ''));
        $status = $filters['status'] ?? 'all';
        $specialFilter = $filters['filter'] ?? null;

        $vehicles = Vehicle::query()
            ->with([
                'fuels' => fn ($q) => $q->latest('date')->latest('id')->limit(3),
                'drivers' => fn ($q) => $q->latest()->limit(3),
                'images' => fn ($q) => $q->orderByDesc('is_featured')->orderBy('sort_order')->limit(1),
                'maintenanceSetting',
                'documents' => fn ($q) => $q->whereNull('archived_at')->latest(),
            ])
            ->withCount(['maintenances', 'fuels', 'trafficPenalties'])
            ->when($search !== '', function ($q) use ($search) {
                // like için % ve _ kaçırma
                $needle = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
                $q->where(function ($qq) use ($needle) {
                    $qq->where('plate', 'like', $needle)
                       ->orWhere('brand', 'like', $needle)
                       ->orWhere('model', 'like', $needle)
                       ->orWhere('chassis_no', 'like', $needle)
                       ->orWhere('engine_no', 'like', $needle);
                });
            })
            ->when($type !== '', fn ($q) => $q->where('vehicle_type', $type))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'passive', fn ($q) => $q->where('is_active', false))
            ->when($specialFilter === 'upcoming_inspection', function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNotNull('inspection_date')->where('inspection_date', '<=', now()->addDays(15))
                        ->orWhereHas('documents', function ($doc) {
                            $doc->whereIn('document_type', ['Muayene', 'Muayene Raporu'])
                                ->whereNotNull('end_date')
                                ->where('end_date', '<=', now()->addDays(15))
                                ->whereNull('archived_at');
                        });
                });
            })
            ->when($specialFilter === 'upcoming_insurance', function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNotNull('insurance_end_date')->where('insurance_end_date', '<=', now()->addDays(10))
                        ->orWhereHas('documents', function ($doc) {
                            $doc->whereIn('document_type', ['Sigorta', 'Sigorta Poliçesi'])
                                ->whereNotNull('end_date')
                                ->where('end_date', '<=', now()->addDays(10))
                                ->whereNull('archived_at');
                        });
                });
            })
            ->when($specialFilter === 'no_driver', fn ($q) => $q->whereDoesntHave('drivers', fn($d) => $d->where('is_active', true)))
            ->orderByRaw("CASE WHEN plate REGEXP '^[0-9]{2} *C *[0-9]+.*$' THEN 0 ELSE 1 END")
            ->orderBy('plate', 'asc')
            ->paginate(100)
            ->withQueryString();

        // KPI İstatistikleri
        $kpi = [
            'total' => Vehicle::count(),
            'upcoming_inspection' => Vehicle::where(function ($q) {
                $q->whereNotNull('inspection_date')->where('inspection_date', '<=', now()->addDays(15))
                  ->orWhereHas('documents', function ($doc) {
                      $doc->whereIn('document_type', ['Muayene', 'Muayene Raporu'])
                          ->whereNotNull('end_date')
                          ->where('end_date', '<=', now()->addDays(15))
                          ->whereNull('archived_at');
                  });
            })->count(),
            'upcoming_insurance' => Vehicle::where(function ($q) {
                $q->whereNotNull('insurance_end_date')->where('insurance_end_date', '<=', now()->addDays(10))
                  ->orWhereHas('documents', function ($doc) {
                      $doc->whereIn('document_type', ['Sigorta', 'Sigorta Poliçesi'])
                          ->whereNotNull('end_date')
                          ->where('end_date', '<=', now()->addDays(10))
                          ->whereNull('archived_at');
                  });
            })->count(),
            'no_driver' => Vehicle::whereDoesntHave('drivers', fn($d) => $d->where('is_active', true))->count(),
            'types' => Vehicle::selectRaw('vehicle_type, count(*) as count')->groupBy('vehicle_type')->pluck('count', 'vehicle_type')->toArray(),
        ];

        // Şoför ataması yapılmamış araçlar için "hızlı atama" modalında gösterilecek
        // aktif personel listesi. BelongsToCompany global scope sayesinde sadece
        // mevcut firmanın aktif personelleri listelenir.
        $availableDrivers = Driver::query()
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'phone', 'vehicle_id']);

        return view('vehicles.index', compact('vehicles', 'filters', 'availableDrivers', 'kpi'));
    }

    /**
     * Toplu araç ekleme için Excel şablonu indir.
     */
    public function downloadTemplate()
    {
        if (!auth()->user()->hasPermission('vehicles.create')) {
            abort(403, 'Bu işlemi yapma yetkiniz yok.');
        }

        return Excel::download(new \App\Exports\VehicleTemplateExport, 'arac_ekleme_sablonu.xlsx');
    }

    /**
     * Toplu araç ekleme Excel içe aktarımı.
     */
    public function import(Request $request)
    {
        if (!auth()->user()->hasPermission('vehicles.create')) {
            abort(403, 'Bu işlemi yapma yetkiniz yok.');
        }

        $request->validate([
            'excel_file' => ['required', 'file', 'max:10240'],
        ]);

        try {
            Excel::import(new \App\Imports\VehiclesImport, $request->file('excel_file'));
            return redirect()->route('vehicles.index')->with('success', 'Toplu araç ekleme işlemi başarıyla tamamlandı.');
        } catch (\Exception $e) {
            return redirect()->route('vehicles.index')->with('error', 'Dosya yüklenirken bir hata oluştu: ' . $e->getMessage());
        }
    }

    /**
     * Bir araca tek tıkla şoför atar. Driver modelindeki vehicle_id güncellenir.
     *
     * Güvenlik:
     *  - Route middleware: permission:vehicles.edit + throttle
     *  - Driver aynı firmada olmalı (BelongsToCompany global scope zaten
     *    garanti eder, yine de validation "exists" kuralı kullanılır).
     *  - Araç ve şoför company_id eşleşmesi ekstra kontrolle doğrulanır.
     */
    public function assignDriver(Request $request, Vehicle $vehicle)
    {
        if (!auth()->user()->hasPermission('vehicles.edit')) {
            abort(403, 'Bu işlemi yapma yetkiniz yok.');
        }

        $validated = $request->validate([
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
        ]);

        $driver = Driver::findOrFail($validated['driver_id']);

        // Çifte kilit: Şoförün ve aracın aynı firmaya ait olduğunu doğrula.
        if ($driver->company_id !== $vehicle->company_id
            || $driver->company_id !== auth()->user()->company_id) {
            abort(403, 'Bu şoförü bu araca atama yetkiniz yok.');
        }

        $driver->update([
            'vehicle_id' => $vehicle->id,
            'is_active'  => true,
        ]);

        return redirect()
            ->route('vehicles.index')
            ->with('success', $driver->full_name . ' isimli personel ' . $vehicle->plate . ' plakalı araca başarıyla atandı.');
    }

    public function exportExcel()
    {
        if (!auth()->user()->hasPermission('vehicles.view')) {
            abort(403, 'Bu işlemi yapma yetkiniz yok.');
        }
        $fileName = 'Araclar_' . now()->format('d-m-Y_H-i') . '.xlsx';

        // Aktivite Kaydı
        \App\Models\ActivityLog::create([
            'company_id'   => auth()->user()->company_id,
            'user_id'      => auth()->id(),
            'module'       => 'vehicles',
            'action'       => 'exported',
            'subject_type' => Vehicle::class,
            'title'        => 'Araç Listesi Dışa Aktarıldı',
            'description'  => 'Tüm araç listesi Excel formatında indirildi.',
            'ip_address'   => request()->ip(),
            'user_agent'   => request()->userAgent(),
        ]);

        return Excel::download(new VehiclesExport(), $fileName);
    }

    public function show(Vehicle $vehicle)
    {
        if (!$vehicle->public_image_upload_token) {
            $vehicle->update([
                'public_image_upload_token' => Str::random(40),
            ]);
            $vehicle->refresh();
        }

        $income = Trip::where('vehicle_id', $vehicle->id)->sum('trip_price');

        $fuel = Fuel::where('vehicle_id', $vehicle->id)->sum('total_cost');

        $salary = Payroll::whereHas('driver', function ($q) use ($vehicle) {
            $q->where('vehicle_id', $vehicle->id);
        })->sum('net_salary');

        $profit = $income - ($fuel + $salary);

        $recentFuels = Fuel::with(['station', 'vehicle'])
            ->where('vehicle_id', $vehicle->id)
            ->latest('date')
            ->latest('id')
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

        // Belge türü sıralama önceliği
        $docTypeOrder = [
            'Ruhsat' => 1,
            'Muayene' => 2,
            'Sigorta' => 3,
            'Kasko' => 4,
            'Egzoz' => 5,
            'Koltuk Sigortası' => 6,
            'İMM Poliçesi' => 7,
            'Vergi / Harç' => 8,
            'Diğer' => 9,
        ];

        $allVehicleDocuments = $vehicle->documents()->get();

        $activeVehicleDocuments = $allVehicleDocuments->filter(function ($doc) {
            return is_null($doc->archived_at)
                && (is_null($doc->end_date) || $doc->end_date->startOfDay()->gte(now()->startOfDay()));
        })->sortBy(function ($doc) use ($docTypeOrder) {
            return $docTypeOrder[$doc->document_type] ?? 99;
        })->values();

        $archivedVehicleDocuments = $allVehicleDocuments->filter(function ($doc) {
            return !is_null($doc->archived_at)
                || (!is_null($doc->end_date) && $doc->end_date->startOfDay()->lt(now()->startOfDay()));
        })->sortBy(function ($doc) use ($docTypeOrder) {
            return $docTypeOrder[$doc->document_type] ?? 99;
        })->values();

        $assignedDrivers = $vehicle->drivers()
            ->latest()
            ->take(5)
            ->get();

        $primaryDriver = $vehicle->drivers()->latest()->first();

        $driverAge = null;
        if ($primaryDriver && !empty($primaryDriver->birth_date)) {
            try {
                $driverAge = \Carbon\Carbon::parse($primaryDriver->birth_date)->age;
            } catch (\Throwable $e) {
                $driverAge = null;
            }
        }

        $tripCount = Trip::where('vehicle_id', $vehicle->id)->count();

        $recentTrips = Trip::where('vehicle_id', $vehicle->id)
            ->latest('trip_date')
            ->take(10)
            ->get();

        $vehicleMaintenances = VehicleMaintenance::with(['vehicle', 'creator'])
            ->where('vehicle_id', $vehicle->id)
            ->latest('service_date')
            ->latest('id')
            ->get();

        $vehiclePenalties = TrafficPenalty::query()
            ->where('vehicle_id', $vehicle->id)
            ->latest('penalty_date')
            ->latest('id')
            ->get();

        $vehicleImages = $vehicle->images()
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        $featuredImage = $vehicleImages->where('is_featured', true)->first() ?? $vehicleImages->first();

        // Raporlar Sekmesi Verisi: Ay Ay Müşteri Bazlı Puantaj (Çalışma)
        $reportsMonth = request('reports_month', now()->format('Y-m'));
        $reportsStartDate = \Carbon\Carbon::parse($reportsMonth . '-01')->startOfMonth();
        $reportsEndDate = $reportsStartDate->copy()->endOfMonth();

        $vehicleTrips = Trip::with(['serviceRoute.customer'])
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

            // Aracın asıl trip'i ise trip_price'ı da ekleyelim (ya da sabah/akşam fiyatını)
            if ($trip->vehicle_id == $vehicle->id) {
                $price = $trip->trip_price ?? 0;
                $monthlyReports[$customerId]['total_price'] += $price;
                $reportTotals['income'] += $price;
            }
        }

        $monthlyReports = collect($monthlyReports)->values();

        $allVehicleTrips = Trip::with(['morningDriver', 'eveningDriver', 'driver'])
            ->where('company_id', $vehicle->company_id)
            ->where(function ($q) use ($vehicle) {
                $q->where('vehicle_id', $vehicle->id)
                  ->orWhere('morning_vehicle_id', $vehicle->id)
                  ->orWhere('evening_vehicle_id', $vehicle->id);
            })->get();

        $historyMap = [];
        $defaultDriver = $vehicle->drivers->where('is_active', true)->first() ?? $vehicle->drivers->first();

        foreach ($allVehicleTrips as $trip) {
            $driversDrove = [];
            
            $mVehicleId = $trip->morning_vehicle_id ?? $trip->vehicle_id;
            $eVehicleId = $trip->evening_vehicle_id ?? $trip->vehicle_id;
            
            // Morning
            if ($mVehicleId == $vehicle->id) {
                $dId = $trip->morning_driver_id;
                $dModel = $trip->morningDriver;
                
                if (!$dId && $trip->driver_id) {
                    $isAmbiguous = $mVehicleId && $eVehicleId && ($mVehicleId != $eVehicleId);
                    if ($isAmbiguous) {
                        if ($trip->driver?->vehicle_id == $vehicle->id) {
                            $dId = $trip->driver_id;
                            $dModel = $trip->driver;
                        }
                    } else {
                        $dId = $trip->driver_id;
                        $dModel = $trip->driver;
                    }
                }
                
                if (!$dId) {
                    $dId = $defaultDriver?->id;
                    $dModel = $defaultDriver;
                }
                
                if ($dId) {
                    $driversDrove[] = ['id' => $dId, 'model' => $dModel];
                }
            }
            
            // Evening
            if ($eVehicleId == $vehicle->id) {
                $dId = $trip->evening_driver_id;
                $eModel = $trip->eveningDriver;
                
                if (!$dId && $trip->driver_id) {
                    $isAmbiguous = $mVehicleId && $eVehicleId && ($mVehicleId != $eVehicleId);
                    if ($isAmbiguous) {
                        if ($trip->driver?->vehicle_id == $vehicle->id) {
                            $dId = $trip->driver_id;
                            $eModel = $trip->driver;
                        }
                    } else {
                        $dId = $trip->driver_id;
                        $eModel = $trip->driver;
                    }
                }
                
                if (!$dId) {
                    $dId = $defaultDriver?->id;
                    $eModel = $defaultDriver;
                }
                
                if ($dId) {
                    $driversDrove[] = ['id' => $dId, 'model' => $eModel];
                }
            }
            
            $driversDrove = collect($driversDrove)->unique('id')->all();
            
            foreach ($driversDrove as $d) {
                $dId = $d['id'];
                if (!$dId) continue;
                if (!isset($historyMap[$dId])) {
                    $historyMap[$dId] = ['driver' => $d['model'], 'start' => $trip->trip_date, 'end' => $trip->trip_date, 'count' => 0];
                }
                $historyMap[$dId]['count']++;
                if ($trip->trip_date < $historyMap[$dId]['start']) $historyMap[$dId]['start'] = $trip->trip_date;
                if ($trip->trip_date > $historyMap[$dId]['end']) $historyMap[$dId]['end'] = $trip->trip_date;
            }
        }
        $driverHistory = collect($historyMap)->sortByDesc('end')->values();

        $latestFuelRecord = $recentFuels
            ->sortByDesc(function ($item) {
                return sprintf(
                    '%s-%010d',
                    optional($item->date)->format('Ymd') ?? '00000000',
                    (int) $item->id
                );
            })
            ->first();

        $currentKm = data_get($latestFuelRecord, 'current_km')
            ?? data_get($latestFuelRecord, 'km')
            ?? data_get($latestFuelRecord, 'vehicle_km')
            ?? data_get($latestFuelRecord, 'odometer')
            ?? data_get($latestFuelRecord, 'kilometer')
            ?? data_get($vehicle, 'current_km')
            ?? data_get($vehicle, 'km')
            ?? data_get($vehicle, 'kilometer')
            ?? 0;

        $publicImageUploadUrl = route('vehicles.public-images.form', [
            'vehicle' => $vehicle->id,
            'token' => $vehicle->public_image_upload_token,
        ]);

        // Arvento Canlı Verileri
        $arventoStats = null;
        $arventoSetting = VehicleTrackingSetting::where('provider', 'arvento')->first();
        if ($arventoSetting) {
            try {
                $arventoService = new ArventoService($arventoSetting);
                $arventoStats = $arventoService->getVehicleDailyStats($vehicle->plate);
            } catch (\Exception $e) {
                \Log::error("Arvento Stats Fetch Error: " . $e->getMessage());
            }
        }

        return view('vehicles.show', compact(
            'vehicle',
            'income',
            'fuel',
            'salary',
            'profit',
            'recentFuels',
            'stationSummaries',
            'activeVehicleDocuments',
            'archivedVehicleDocuments',
            'assignedDrivers',
            'primaryDriver',
            'driverAge',
            'currentKm',
            'tripCount',
            'recentTrips',
            'vehicleMaintenances',
            'vehiclePenalties',
            'vehicleImages',
            'publicImageUploadUrl',
            'arventoStats',
            'reportsMonth',
            'monthlyReports',
            'reportTotals',
            'driverHistory'
        ));
    }

    public function create()
    {
        if (!auth()->user()->hasPermission('vehicles.create')) {
            abort(403, 'Araç ekleme yetkiniz bulunmuyor.');
        }

        $company = auth()->user()->company;
        if (!is_null($company->max_vehicles) && $company->vehicles()->count() >= $company->max_vehicles) {
            return redirect()->route('vehicles.index')->with('error', 'Lisans paketinize ait maksimum araç kotasına (' . $company->max_vehicles . ' Araç) ulaştınız. Yeni araç ekleyebilmek için lütfen sistem yöneticisi ile iletişime geçerek lisansınızı yükseltin.');
        }

        return view('vehicles.create');
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('vehicles.create'), 403);

        $company = auth()->user()->company;
        if (!is_null($company->max_vehicles) && $company->vehicles()->count() >= $company->max_vehicles) {
            return redirect()->back()->with('error', 'Lisans paketinize ait maksimum araç kotasına (' . $company->max_vehicles . ' Araç) ulaştınız. Yeni araç ekleyebilmek için lütfen sistem yöneticisi ile iletişime geçerek lisansınızı yükseltin.')->withInput();
        }

        $data = $request->validate([
            'plate' => ['required', 'string', 'max:20', Rule::unique('vehicles', 'plate')->whereNull('deleted_at')],
            'device_imei' => 'nullable|string|max:20',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'vehicle_type' => 'nullable|string|max:100',
            'vehicle_package' => 'nullable|string|max:100',
            'model_year' => 'nullable|integer|min:1900|max:2100',
            'registration_date' => 'nullable|date',
            'seat_count' => 'nullable|integer|min:1',
            'gear_type' => 'nullable|string|max:50',
            'fuel_type' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
            'other_color' => 'nullable|string|max:100',
            'engine_no' => 'nullable|string|max:100',
            'chassis_no' => 'nullable|string|max:100',
            'license_serial_no' => 'nullable|string|max:100',
            'license_owner' => 'nullable|string|max:255',
            'owner_tax_or_tc_no' => 'nullable|string|max:50',
            'inspection_date' => 'nullable|date',
            'exhaust_date' => 'nullable|date',
            'insurance_end_date' => 'nullable|date',
            'kasko_end_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'current_km' => 'nullable|integer|min:0',
            'status' => 'nullable|string|max:50',
        ]);

        if (($data['color'] ?? null) !== 'Diğer') {
            $data['other_color'] = null;
        }

        $data['company_id'] = auth()->user()->company_id;
        $data['is_active'] = $request->boolean('is_active');
        $data['public_image_upload_token'] = Str::random(40);

        $vehicle = Vehicle::create($data);

        // Aktivite Kaydı
        \App\Models\ActivityLog::create([
            'company_id'   => auth()->user()->company_id,
            'user_id'      => auth()->id(),
            'module'       => 'vehicles',
            'action'       => 'created',
            'subject_type' => Vehicle::class,
            'subject_id'   => $vehicle->id,
            'title'        => 'Yeni Araç Eklendi',
            'description'  => "{$vehicle->plate} plakalı yeni araç sisteme tanımlandı.",
            'new_values'   => $data,
            'ip_address'   => request()->ip(),
            'user_agent'   => request()->userAgent(),
        ]);

        return redirect()
            ->route('vehicles.index')
            ->with('success', 'Araç başarıyla eklendi.');
    }

    public function edit(Vehicle $vehicle)
    {
        abort_unless(auth()->user()->hasPermission('vehicles.edit'), 403);
        return view('vehicles.edit', compact('vehicle'));
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        abort_unless(auth()->user()->hasPermission('vehicles.edit'), 403);

        $data = $request->validate([
            'plate' => ['required', 'string', 'max:20', Rule::unique('vehicles', 'plate')->ignore($vehicle->id)->whereNull('deleted_at')],
            'device_imei' => 'nullable|string|max:20',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'vehicle_type' => 'nullable|string|max:100',
            'vehicle_package' => 'nullable|string|max:100',
            'model_year' => 'nullable|integer|min:1900|max:2100',
            'registration_date' => 'nullable|date',
            'seat_count' => 'nullable|integer|min:1',
            'gear_type' => 'nullable|string|max:50',
            'fuel_type' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
            'other_color' => 'nullable|string|max:100',
            'engine_no' => 'nullable|string|max:100',
            'chassis_no' => 'nullable|string|max:100',
            'license_serial_no' => 'nullable|string|max:100',
            'license_owner' => 'nullable|string|max:255',
            'owner_tax_or_tc_no' => 'nullable|string|max:50',
            'inspection_date' => 'nullable|date',
            'exhaust_date' => 'nullable|date',
            'insurance_end_date' => 'nullable|date',
            'kasko_end_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'current_km' => 'nullable|integer|min:0',
            'status' => 'nullable|string|max:50',
        ]);

        if (($data['color'] ?? null) !== 'Diğer') {
            $data['other_color'] = null;
        }

        $data['is_active'] = $request->boolean('is_active');

        if (!$vehicle->public_image_upload_token) {
            $data['public_image_upload_token'] = Str::random(40);
        }

        $oldData = $vehicle->toArray();
        $vehicle->update($data);

        // Aktivite Kaydı
        \App\Models\ActivityLog::create([
            'company_id'   => auth()->user()->company_id,
            'user_id'      => auth()->id(),
            'module'       => 'vehicles',
            'action'       => 'updated',
            'subject_type' => Vehicle::class,
            'subject_id'   => $vehicle->id,
            'title'        => 'Araç Güncellendi',
            'description'  => "{$vehicle->plate} plakalı araç bilgileri güncellendi.",
            'old_values'   => $oldData,
            'new_values'   => $data,
            'ip_address'   => request()->ip(),
            'user_agent'   => request()->userAgent(),
        ]);

        return redirect()
            ->route('vehicles.index')
            ->with('success', 'Araç başarıyla güncellendi.');
    }

    public function uploadImage(Request $request, Vehicle $vehicle)
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
            'is_featured' => 'nullable|boolean',
            'image_type' => 'nullable|string|max:50',
        ]);

        $path = $request->file('image')->store('vehicle-images', 'public');

        $isFeatured = $request->boolean('is_featured');

        if ($isFeatured || $vehicle->images()->count() === 0) {
            $vehicle->images()->update(['is_featured' => false]);
            $isFeatured = true;
        }

        $imageType = $data['image_type'] ?? 'other';

        $image = $vehicle->images()->create([
            'title' => $data['title'] ?? $this->resolveImageTypeLabel($imageType),
            'file_path' => $path,
            'is_featured' => $isFeatured,
            'sort_order' => 0,
            'image_type' => $imageType,
            'upload_source' => 'panel',
        ]);

        \App\Models\ActivityLog::create([
            'company_id'   => auth()->user()->company_id,
            'user_id'      => auth()->id(),
            'module'       => 'vehicles',
            'action'       => 'image_uploaded',
            'subject_type' => Vehicle::class,
            'subject_id'   => $vehicle->id,
            'title'        => 'Araç Resmi Yüklendi',
            'description'  => "{$vehicle->plate} plakalı araca yeni bir resim eklendi.",
            'ip_address'   => request()->ip(),
            'user_agent'   => request()->userAgent(),
        ]);

        return redirect()
            ->route('vehicles.show', ['vehicle' => $vehicle->id, 'tab' => 'images'])
            ->with('success', 'Araç resmi başarıyla yüklendi.');
    }

    public function publicImageUploadForm(Vehicle $vehicle, string $token)
    {
        abort_unless($vehicle->public_image_upload_token && hash_equals($vehicle->public_image_upload_token, $token), 404);

        $imageTypeOptions = $this->imageTypeOptions();

        return view('vehicles.public-upload', compact('vehicle', 'token', 'imageTypeOptions'));
    }

    public function publicImageUploadStore(Request $request, Vehicle $vehicle, string $token)
    {
        abort_unless($vehicle->public_image_upload_token && hash_equals($vehicle->public_image_upload_token, $token), 404);

        $data = $request->validate([
            'image_type' => 'required|string|in:front,right_side,left_side,rear,interior_1,interior_2,dashboard,other',
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:20480',
        ]);

        $path = $request->file('image')->store('vehicle-images', 'public');

        $imageType = $data['image_type'];
        $typeLabel = $this->resolveImageTypeLabel($imageType);

        $existingCount = $vehicle->images()
            ->where('image_type', $imageType)
            ->count();

        $title = $typeLabel;
        if ($existingCount > 0 && $imageType === 'other') {
            $title .= ' #' . ($existingCount + 1);
        }

        $isFeatured = $vehicle->images()->count() === 0;

        if ($isFeatured) {
            $vehicle->images()->update(['is_featured' => false]);
        }

        $vehicle->images()->create([
            'company_id' => $vehicle->company_id,
            'title' => $title,
            'file_path' => $path,
            'is_featured' => $isFeatured,
            'sort_order' => 0,
            'image_type' => $imageType,
            'upload_source' => 'driver_link',
        ]);

        return redirect()
            ->route('vehicles.public-images.form', ['vehicle' => $vehicle->id, 'token' => $token])
            ->with('success', $typeLabel . ' başarıyla yüklendi.');
    }

    public function adminFastPhotoUpload()
    {
        if (!auth()->user()->hasPermission('vehicles.view')) {
            abort(403);
        }

        $vehicles = Vehicle::orderBy('plate')->get();

        return view('vehicles.admin-fast-upload-select', compact('vehicles'));
    }

    public function setFeaturedImage(Vehicle $vehicle, VehicleImage $image)
    {
        if ($image->vehicle_id !== $vehicle->id) {
            abort(404);
        }

        $vehicle->images()->update(['is_featured' => false]);
        $image->update(['is_featured' => true]);

        return redirect()
            ->route('vehicles.show', ['vehicle' => $vehicle->id, 'tab' => 'images'])
            ->with('success', 'Vitrin resmi güncellendi.');
    }

    public function deleteImage(Vehicle $vehicle, VehicleImage $image)
    {
        if ((int) $image->vehicle_id !== (int) $vehicle->id) {
            abort(404);
        }

        $wasFeatured = $image->is_featured;

        if ($image->file_path && Storage::disk('public')->exists($image->file_path)) {
            Storage::disk('public')->delete($image->file_path);
        }

        $image->delete();

        if ($wasFeatured) {
            $nextImage = $vehicle->images()->first();
            if ($nextImage) {
                $nextImage->update(['is_featured' => true]);
            }
        }

        \App\Models\ActivityLog::create([
            'company_id'   => auth()->user()->company_id,
            'user_id'      => auth()->id(),
            'module'       => 'vehicles',
            'action'       => 'image_deleted',
            'subject_type' => Vehicle::class,
            'subject_id'   => $vehicle->id,
            'title'        => 'Araç Resmi Silindi',
            'description'  => "{$vehicle->plate} plakalı aracın bir resmi silindi.",
            'ip_address'   => request()->ip(),
            'user_agent'   => request()->userAgent(),
        ]);

        return redirect()
            ->route('vehicles.show', ['vehicle' => $vehicle->id, 'tab' => 'images'])
            ->with('success', 'Araç resmi silindi.');
    }

    public function uploadDocument(Request $request, Vehicle $vehicle)
    {
        $data = $request->validate([
            'document_name' => 'required|string|max:255',
            'document_type' => 'required|string|max:255',
            'issuer_name' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx|max:20480',
            'notes' => 'nullable|string',
        ]);

        $filePath = null;

        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('vehicle-documents', 'public');
        }

        $vehicle->documents()
            ->whereNull('archived_at')
            ->where('document_type', $data['document_type'])
            ->update(['archived_at' => now()]);

        $vehicle->documents()->create([
            'company_id' => auth()->user()->company_id,
            'document_name' => $data['document_name'],
            'document_type' => $data['document_type'],
            'issuer_name' => $data['issuer_name'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'archived_at' => null,
            'file_path' => $filePath,
            'notes' => $data['notes'] ?? null,
        ]);

        \App\Models\ActivityLog::create([
            'company_id'   => auth()->user()->company_id,
            'user_id'      => auth()->id(),
            'module'       => 'vehicles',
            'action'       => 'document_uploaded',
            'subject_type' => Vehicle::class,
            'subject_id'   => $vehicle->id,
            'title'        => 'Araç Belgesi Eklendi',
            'description'  => "{$vehicle->plate} plakalı araca '{$data['document_name']}' belgesi eklendi.",
            'ip_address'   => request()->ip(),
            'user_agent'   => request()->userAgent(),
        ]);

        return redirect()
            ->route('vehicles.show', ['vehicle' => $vehicle->id, 'tab' => 'documents'])
            ->with('success', 'Belge başarıyla kaydedildi.');
    }

    public function deleteDocument(Vehicle $vehicle, Document $document)
    {
        if ((int) $document->documentable_id !== (int) $vehicle->id) {
            abort(404);
        }

        if (!empty($document->file_path) && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        \App\Models\ActivityLog::create([
            'company_id'   => auth()->user()->company_id,
            'user_id'      => auth()->id(),
            'module'       => 'vehicles',
            'action'       => 'document_deleted',
            'subject_type' => Vehicle::class,
            'subject_id'   => $vehicle->id,
            'title'        => 'Araç Belgesi Silindi',
            'description'  => "{$vehicle->plate} plakalı aracın bir belgesi silindi.",
            'ip_address'   => request()->ip(),
            'user_agent'   => request()->userAgent(),
        ]);

        return redirect()
            ->route('vehicles.show', ['vehicle' => $vehicle->id, 'tab' => 'documents'])
            ->with('success', 'Araç belgesi silindi.');
    }

    public function downloadDocumentsZip(Vehicle $vehicle)
    {
        $documents = $vehicle->documents()->whereNotNull('file_path')->get();

        $validDocs = $documents->filter(function ($doc) {
            return !empty($doc->file_path) && Storage::disk('public')->exists($doc->file_path);
        });

        if ($validDocs->isEmpty()) {
            return redirect()
                ->route('vehicles.show', ['vehicle' => $vehicle->id, 'tab' => 'documents'])
                ->with('error', 'İndirilecek belge dosyası bulunamadı. Lütfen önce belgelere dosya yükleyin.');
        }

        $safePlate = \Illuminate\Support\Str::slug($vehicle->plate, '');
        $todayDate = now()->format('d-m-Y');
        $zipFileName = $safePlate . '_' . $todayDate . '_Arac_Evraklari.zip';
        $zipPath = storage_path('app/temp_' . time() . '_' . $zipFileName);

        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return redirect()
                ->route('vehicles.show', ['vehicle' => $vehicle->id, 'tab' => 'documents'])
                ->with('error', 'ZIP dosyası oluşturulamadı. Sunucu izinlerini kontrol edin.');
        }

        $filesAdded = 0;
        foreach ($validDocs as $document) {
            $absolutePath = Storage::disk('public')->path($document->file_path);
            
            if (file_exists($absolutePath)) {
                $extension = pathinfo($absolutePath, PATHINFO_EXTENSION);
                if (empty($extension)) {
                    $extension = 'pdf'; // Fallback extension
                }

                $folder = (!is_null($document->archived_at) || (!is_null($document->end_date) && $document->end_date->isPast()))
                    ? 'Arsiv_Belgeler'
                    : 'Aktif_Belgeler';

                $safeName = \Illuminate\Support\Str::slug($document->document_name ?: 'Belge');
                $safeType = \Illuminate\Support\Str::slug($document->document_type ?: 'Dosya');

                $fileNameInZip = $folder . '/' . $safeName . '_' . $safeType . '.' . $extension;

                $zip->addFile($absolutePath, $fileNameInZip);
                $filesAdded++;
            }
        }

        if ($filesAdded === 0) {
            $zip->close();
            @unlink($zipPath);
            return redirect()
                ->route('vehicles.show', ['vehicle' => $vehicle->id, 'tab' => 'documents'])
                ->with('error', 'Belgelerin fiziksel dosyaları sunucuda bulunamadı.');
        }

        $zip->close();

        return response()->download($zipPath, $zipFileName, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $zipFileName . '"'
        ])->deleteFileAfterSend(true);
    }

    public function destroy(Vehicle $vehicle)
    {
        abort_unless(auth()->user()->hasPermission('vehicles.delete'), 403);

        $plate = $vehicle->plate;

        foreach ($vehicle->images as $image) {
            if ($image->file_path && Storage::disk('public')->exists($image->file_path)) {
                Storage::disk('public')->delete($image->file_path);
            }
        }

        foreach ($vehicle->documents as $document) {
            if (!empty($document->file_path) && Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }
        }

        $vehicle->delete();

        // Aktivite Kaydı
        \App\Models\ActivityLog::create([
            'company_id'   => auth()->user()->company_id,
            'user_id'      => auth()->id(),
            'module'       => 'vehicles',
            'action'       => 'deleted',
            'subject_type' => Vehicle::class,
            'title'        => 'Araç Silindi',
            'description'  => "{$plate} plakalı araç ve bağlı tüm verileri sistemden silindi.",
            'ip_address'   => request()->ip(),
            'user_agent'   => request()->userAgent(),
        ]);

        return back()->with('success', 'Araç silindi.');
    }

    protected function imageTypeOptions(): array
    {
        return [
            'front' => 'Araç Ön Resmi',
            'right_side' => 'Sağ Yan',
            'left_side' => 'Sol Yan',
            'rear' => 'Arka',
            'interior_1' => 'İç Resim 1',
            'interior_2' => 'İç Resim 2',
            'dashboard' => 'Göğüs',
            'other' => 'Diğer Resimler',
        ];
    }

    protected function resolveImageTypeLabel(?string $type): string
    {
        return $this->imageTypeOptions()[$type] ?? 'Araç Görseli';
    }

    public function aiAssistant(Request $request)
    {
        $question = mb_strtolower($request->get('question', ''), 'UTF-8');
        $companyId = auth()->user()->company_id;
        
        $vehicles = Vehicle::where('company_id', $companyId)->get();
        $totalCount = $vehicles->count();
        
        $answer = "Sistemi taradım. İşte bulduğum sonuçlar:\n\n";

        if (str_contains($question, 'bakım') || str_contains($question, 'servis')) {
            $nearMaintenance = [];
            foreach ($vehicles as $v) {
                $status = $v->maintenance_status;
                if ($status['has_setting'] && ($status['oil_remaining'] < 2000 || $status['lube_remaining'] < 1000)) {
                    $nearMaintenance[] = $v->plate . " (" . number_format($status['oil_remaining'], 0, ',', '.') . " KM kaldı)";
                }
            }
            
            if (count($nearMaintenance) > 0) {
                $answer .= "Şu an bakımı yaklaşan " . count($nearMaintenance) . " aracınız var: " . implode(', ', $nearMaintenance) . ". En kısa sürede randevu almanızı öneririm.";
            } else {
                $answer .= "Tüm araçlarınızın bakım durumu şu an iyi görünüyor. Kritik bir seviyeye ulaşan aracınız yok.";
            }
        } elseif (str_contains($question, 'kaç') || str_contains($question, 'sayı') || str_contains($question, 'toplam')) {
            $types = $vehicles->groupBy('vehicle_type')->map->count();
            $answer .= "Toplam {$totalCount} aracınız bulunuyor.\n";
            foreach ($types as $type => $count) {
                $answer .= "- {$count} adet {$type}\n";
            }
        } elseif (str_contains($question, 'aktif') || str_contains($question, 'pasif')) {
            $active = $vehicles->where('is_active', true)->count();
            $passive = $vehicles->where('is_active', false)->count();
            $answer .= "Filonuzda şu an {$active} aktif, {$passive} pasif araç bulunuyor.";
        } elseif (str_contains($question, 'en çok') && (str_contains($question, 'sefer') || str_contains($question, 'yol'))) {
            $topVehicle = $vehicles->sortByDesc('current_km')->first();
            if ($topVehicle) {
                $answer .= "En çok yol kat eden aracınız {$topVehicle->plate} plakalı araçtır (Toplam: " . number_format($topVehicle->current_km, 0, ',', '.') . " KM).";
            }
        } elseif (preg_match('/[0-9]{2}\s?[a-z]{1,3}\s?[0-9]{2,4}/i', $question, $matches)) {
            $plate = strtoupper(str_replace(' ', '', $matches[0]));
            $v = Vehicle::where('company_id', $companyId)->where('plate', 'like', "%$plate%")->first();
            if ($v) {
                $status = $v->is_active ? 'Aktif' : 'Pasif';
                $answer .= "{$v->plate} plakalı aracı buldum. {$v->brand} {$v->model} model, {$v->vehicle_type} tipinde bir araç. Şu an durumu: {$status}. Güncel kilometresi: " . number_format($v->current_km, 0, ',', '.') . " KM.";
            } else {
                $answer .= "Maalesef {$plate} plakalı bir araç sistemde kayıtlı görünmüyor.";
            }
        } else {
            $answer = "Filonuzla ilgili verileri analiz ettim ancak bu soruya özel bir veri bulamadım. Bakım durumları, araç sayıları veya aktiflik durumları hakkında sorular sorabilirsiniz.";
        }

        return response()->json(['answer' => $answer]);
    }

    public function bulkDelete(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('vehicles.delete'), 403);

        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:vehicles,id',
        ]);

        $deleted = Vehicle::whereIn('id', $request->ids)
            ->where('company_id', auth()->user()->company_id)
            ->delete();

        return redirect()
            ->route('vehicles.index')
            ->with('success', $deleted . ' adet araç başarıyla silindi.');
    }
}