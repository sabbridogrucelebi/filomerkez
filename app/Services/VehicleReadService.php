<?php

namespace App\Services;

use App\Models\Fleet\Vehicle;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class VehicleReadService
{
    public function getVehicles(int $companyId, array $filters, int $perPage = 20): array
    {
        $query = Vehicle::with(['drivers', 'documents' => fn ($q) => $q->whereNull('archived_at')->latest()])
            ->select('vehicles.*')
            ->where('company_id', $companyId)
            ->addSelect([
                'max_fuel_km' => \App\Models\Fuel::selectRaw('MAX(km)')
                    ->whereColumn('vehicle_id', 'vehicles.id'),
                'max_maintenance_km' => \App\Models\VehicleMaintenance::selectRaw('MAX(km)')
                    ->whereColumn('vehicle_id', 'vehicles.id'),
                'last_driver_name' => \App\Models\Fleet\Driver::select('full_name')
                    ->whereColumn('vehicle_id', 'vehicles.id')
                    ->latest('id')
                    ->limit(1)
            ]);

        if (isset($filters['filter'])) {
            if ($filters['filter'] === 'upcoming_inspection') {
                $query->where(function ($q) {
                    $q->whereNotNull('inspection_date')->where('inspection_date', '<=', now()->addDays(15))
                      ->orWhereHas('documents', function ($doc) {
                          $doc->whereIn('document_type', ['Muayene', 'Muayene Raporu'])
                              ->whereNotNull('end_date')
                              ->where('end_date', '<=', now()->addDays(15))
                              ->whereNull('archived_at');
                      });
                });
            } elseif ($filters['filter'] === 'upcoming_insurance') {
                $query->where(function ($q) {
                    $q->whereNotNull('insurance_end_date')->where('insurance_end_date', '<=', now()->addDays(10))
                      ->orWhereHas('documents', function ($doc) {
                          $doc->whereIn('document_type', ['Sigorta', 'Sigorta Poliçesi'])
                              ->whereNotNull('end_date')
                              ->where('end_date', '<=', now()->addDays(10))
                              ->whereNull('archived_at');
                      });
                });
            }
        }

        if (isset($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'active') {
                $query->where('is_active', true);
            } elseif ($filters['status'] === 'passive') {
                $query->where('is_active', false);
            }
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('plate', 'like', "%$search%")
                  ->orWhere('brand', 'like', "%$search%")
                  ->orWhere('model', 'like', "%$search%")
                  ->orWhereHas('drivers', function($sq) use ($search) {
                      $sq->where('full_name', 'like', "%$search%");
                  });
            });
        }

        $paginator = $query->latest()->paginate($perPage);

        $formattedVehicles = collect($paginator->items())->map(function ($vehicle) {
            $currentKm = max((int)$vehicle->current_km, (int)$vehicle->max_fuel_km, (int)$vehicle->max_maintenance_km);
            $driver = $vehicle->last_driver_name;
            
            $docTypes = ['Muayene', 'Egzoz', 'Sigorta', 'Kasko', 'İMM Poliçesi', 'İMM POLİÇESİ'];
            $latestDocs = $vehicle->documents
                ->whereIn('document_type', $docTypes)
                ->whereNotNull('end_date')
                ->groupBy('document_type')
                ->map(fn($g) => $g->sortByDesc('end_date')->first());

            $inspectionDoc = $latestDocs->get('Muayene');
            $insuranceDoc  = $latestDocs->get('Sigorta');

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
                'inspection_date' => $inspectionDoc?->end_date?->toDateString() ?? ($vehicle->inspection_date ? $vehicle->inspection_date->toDateString() : null),
                'insurance_end_date' => $insuranceDoc?->end_date?->toDateString() ?? ($vehicle->insurance_end_date ? $vehicle->insurance_end_date->toDateString() : null),
                'engine_no' => $vehicle->engine_no,
                'chassis_no' => $vehicle->chassis_no,
                'fuel_type' => $vehicle->fuel_type,
                'license_serial_no' => $vehicle->license_serial_no,
                'license_owner' => $vehicle->license_owner,
                'owner_tax_or_tc_no' => $vehicle->owner_tax_or_tc_no,
                'color' => $vehicle->color,
                'seat_count' => $vehicle->seat_count,
                'driver' => $driver,
            ];
        });

        $kpi = [
            'total' => Vehicle::where('company_id', $companyId)->count(),
            'upcoming_inspection' => Vehicle::where('company_id', $companyId)
                ->where(function ($q) {
                    $q->whereNotNull('inspection_date')->where('inspection_date', '<=', now()->addDays(15))
                      ->orWhereHas('documents', function ($doc) {
                          $doc->whereIn('document_type', ['Muayene', 'Muayene Raporu'])
                              ->whereNotNull('end_date')
                              ->where('end_date', '<=', now()->addDays(15))
                              ->whereNull('archived_at');
                      });
                })->count(),
            'upcoming_insurance' => Vehicle::where('company_id', $companyId)
                ->where(function ($q) {
                    $q->whereNotNull('insurance_end_date')->where('insurance_end_date', '<=', now()->addDays(10))
                      ->orWhereHas('documents', function ($doc) {
                          $doc->whereIn('document_type', ['Sigorta', 'Sigorta Poliçesi'])
                              ->whereNotNull('end_date')
                              ->where('end_date', '<=', now()->addDays(10))
                              ->whereNull('archived_at');
                      });
                })->count(),
        ];

        return [
            'vehicles' => $formattedVehicles->toArray(),
            'kpi' => $kpi,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        ];
    }

    public function getVehicleDetail(int $companyId, int $vehicleId): ?array
    {
        $vehicle = Vehicle::query()
            ->select('vehicles.*')
            ->where('company_id', $companyId)
            ->addSelect([
                'max_fuel_km' => \App\Models\Fuel::selectRaw('MAX(km)')
                    ->whereColumn('vehicle_id', 'vehicles.id'),
                'max_maintenance_km' => \App\Models\VehicleMaintenance::selectRaw('MAX(km)')
                    ->whereColumn('vehicle_id', 'vehicles.id'),
                'last_driver_name' => \App\Models\Fleet\Driver::select('full_name')
                    ->whereColumn('vehicle_id', 'vehicles.id')
                    ->latest('id')
                    ->limit(1)
            ])
            ->with(['images' => fn($q) => $q->orderBy('sort_order')])
            ->find($vehicleId);

        if (!$vehicle) {
            return null;
        }

        $currentKm = max((int)$vehicle->current_km, (int)$vehicle->max_fuel_km, (int)$vehicle->max_maintenance_km);

        $income = \App\Models\Trip::where('vehicle_id', $vehicle->id)->sum('trip_price');
        $fuel = \App\Models\Fuel::where('vehicle_id', $vehicle->id)->sum('total_cost');
        $salary = \App\Models\Payroll::whereHas('driver', function ($q) use ($vehicle) {
            $q->where('vehicle_id', $vehicle->id);
        })->sum('net_salary');
        $profit = $income - ($fuel + $salary);

        $driver = $vehicle->last_driver_name;
        $image = $vehicle->images->where('is_featured', true)->first() ?: $vehicle->images->first();
        $imageUrl = $image ? url(Storage::url($image->file_path)) : null;

        $stats = [
            'revenue' => (float) $income,
            'fuel' => (float) $fuel,
            'salary' => (float) $salary,
            'net' => (float) $profit,
        ];

        // Maintenance Health Calculation (Canlı Veri / Live Data Calculation)
        // Web paneldeki logic ile birebir aynı olması için model üzerindeki attribute'u kullanıyoruz
        $mStatus = $vehicle->maintenance_status;
        
        $maintenanceHealth = [
            'has_setting' => current($mStatus) !== false ? $mStatus['has_setting'] : false,
            'oil_change_remaining_km' => $mStatus['oil_remaining'] ?? null,
            'oil_change_percent' => $mStatus['oil_percent'] ?? 0,
            'bottom_lube_remaining_km' => $mStatus['lube_remaining'] ?? null,
            'bottom_lube_percent' => $mStatus['lube_percent'] ?? 0,
        ];

        // Tüm kritik belge tarihlerini documents tablosundan oku
        $docTypes = ['Muayene', 'Egzoz', 'Sigorta', 'Kasko', 'İMM Poliçesi', 'İMM POLİÇESİ'];
        $latestDocs = $vehicle->documents()
            ->whereIn('document_type', $docTypes)
            ->whereNotNull('end_date')
            ->get()
            ->groupBy('document_type')
            ->map(fn($g) => $g->sortByDesc('end_date')->first());

        $inspectionDoc = $latestDocs->get('Muayene');
        $exhaustDoc    = $latestDocs->get('Egzoz');
        $insuranceDoc  = $latestDocs->get('Sigorta');
        $kaskoDoc      = $latestDocs->get('Kasko');
        $immDoc        = $latestDocs->get('İMM Poliçesi') ?? $latestDocs->get('İMM POLİÇESİ');

        return [
            'vehicle' => array_merge($vehicle->toArray(), [
                'current_km' => (int) ($mStatus['current_km'] ?? $currentKm),
                'driver' => $driver,
                'image_url' => $imageUrl,
                // Belge tarihlerini documents tablosundan öncelikli oku, fallback olarak vehicle alanları
                'inspection_date'   => $inspectionDoc?->end_date?->toDateString() ?? ($vehicle->inspection_date ? $vehicle->inspection_date->toDateString() : null),
                'exhaust_date'      => $exhaustDoc?->end_date?->toDateString() ?? ($vehicle->exhaust_date ? $vehicle->exhaust_date->toDateString() : null),
                'insurance_end_date'=> $insuranceDoc?->end_date?->toDateString() ?? ($vehicle->insurance_end_date ? $vehicle->insurance_end_date->toDateString() : null),
                'kasko_end_date'    => $kaskoDoc?->end_date?->toDateString() ?? ($vehicle->kasko_end_date ? $vehicle->kasko_end_date->toDateString() : null),
                'imm_end_date'      => $immDoc?->end_date?->toDateString() ?? null,
            ]),
            'stats' => $stats,
            'maintenance_health' => $maintenanceHealth,
        ];
    }

    public function getDocuments(int $companyId, int $vehicleId): ?array
    {
        $vehicle = Vehicle::where('company_id', $companyId)->find($vehicleId);
        if (!$vehicle) return null;

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
                    'is_expired' => $doc->isExpired(),
                ];
            });

        return ['documents' => $documents];
    }

    public function getFuels(int $companyId, int $vehicleId, array $filters, int $perPage = 20): ?array
    {
        $vehicle = Vehicle::where('company_id', $companyId)->find($vehicleId);
        if (!$vehicle) return null;

        $query = $vehicle->fuels()->with('station');

        if (!empty($filters['start_date'])) $query->where('date', '>=', $filters['start_date']);
        if (!empty($filters['end_date'])) $query->where('date', '<=', $filters['end_date']);
        
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('station_name', 'like', "%$search%")
                  ->orWhere('notes', 'like', "%$search%")
                  ->orWhereHas('station', function($sq) use ($search) {
                      $sq->where('name', 'like', "%$search%");
                  });
            });
        }

        $paginator = $query->latest('date')->latest('id')->paginate($perPage);

        $fuels = collect($paginator->items())->map(function($row) use ($vehicle) {
            $prev = $vehicle->fuels()
                ->where('km', '<', $row->km)
                ->where('km', '>', 0)
                ->orderBy('km', 'desc')
                ->first();
                
            $km_diff = $prev ? (int)$row->km - (int)$prev->km : null;
            $km_per_liter = ($km_diff && $row->liters > 0) ? round($km_diff / $row->liters, 2) : null;

            return [
                'id' => $row->id,
                'date' => $row->date ? $row->date->toDateString() : null,
                'station_name' => $row->station?->name ?? $row->station_name,
                'total_cost' => (float)$row->total_cost,
                'km' => (int)$row->km,
                'km_diff' => $km_diff,
                'km_per_liter' => $km_per_liter,
                'liters' => (float)$row->liters,
                'price_per_liter' => (float)$row->price_per_liter,
                'fuel_type' => $row->fuel_type,
                'is_paid' => true, // Simplified for mobile V1
                'notes' => $row->notes
            ];
        });

        // Simplified summary for V1
        $now = now();
        $monthTotal = $vehicle->fuels()->whereMonth('date', $now->month)->whereYear('date', $now->year)->sum('total_cost');
        
        $summary = [
            'month_total' => (float)$monthTotal,
            'all_time_total' => (float)$vehicle->fuels()->sum('total_cost')
        ];

        return [
            'fuels' => $fuels,
            'summary' => $summary,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        ];
    }

    public function getMaintenances(int $companyId, int $vehicleId, array $filters, int $perPage = 20): ?array
    {
        $vehicle = Vehicle::where('company_id', $companyId)->find($vehicleId);
        if (!$vehicle) return null;

        $query = $vehicle->maintenances();

        if (!empty($filters['start_date'])) $query->where('service_date', '>=', $filters['start_date']);
        if (!empty($filters['end_date'])) $query->where('service_date', '<=', $filters['end_date']);
        
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                  ->orWhere('maintenance_type', 'like', "%$search%")
                  ->orWhere('service_name', 'like', "%$search%");
            });
        }

        $paginator = $query->latest('service_date')->latest('id')->paginate($perPage);

        $maintenances = collect($paginator->items())->map(function($m) {
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

        return [
            'maintenances' => $maintenances,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        ];
    }

    public function getPenalties(int $companyId, int $vehicleId, array $filters, int $perPage = 20): ?array
    {
        $vehicle = Vehicle::where('company_id', $companyId)->find($vehicleId);
        if (!$vehicle) return null;

        $query = $vehicle->trafficPenalties();

        if (!empty($filters['start_date'])) $query->where('penalty_date', '>=', $filters['start_date']);
        if (!empty($filters['end_date'])) $query->where('penalty_date', '<=', $filters['end_date']);
        
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('penalty_no', 'like', "%$search%")
                  ->orWhere('driver_name', 'like', "%$search%");
            });
        }

        $paginator = $query->latest('penalty_date')->latest('id')->paginate($perPage);

        $penalties = collect($paginator->items())->map(function($p) {
            return [
                'id' => $p->id,
                'penalty_no' => $p->penalty_no,
                'driver_name' => $p->driver_name,
                'date' => $p->penalty_date ? $p->penalty_date->toDateString() : null,
                'time' => $p->penalty_time ? \Carbon\Carbon::parse($p->penalty_time)->format('H:i') : null,
                'status' => $p->payment_status,
                'amount' => (float)$p->penalty_amount,
                'paid_amount' => (float)$p->paid_amount,
                'discounted_amount' => (float)$p->discounted_amount,
                'article' => $p->penalty_article,
                'location' => $p->penalty_location,
                'payment_date' => $p->payment_date ? $p->payment_date->toDateString() : null,
                'discount_deadline' => $p->discount_deadline ? $p->discount_deadline->toDateString() : null,
                'traffic_penalty_document' => $p->traffic_penalty_document ? url(\Illuminate\Support\Facades\Storage::url($p->traffic_penalty_document)) : null,
                'payment_receipt' => $p->payment_receipt ? url(\Illuminate\Support\Facades\Storage::url($p->payment_receipt)) : null,
                'notes' => $p->notes,
            ];
        });

        return [
            'penalties' => $penalties,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        ];
    }

    public function getGallery(int $companyId, int $vehicleId): ?array
    {
        $vehicle = Vehicle::where('company_id', $companyId)->find($vehicleId);
        if (!$vehicle) return null;

        $images = $vehicle->images()->latest()->get()->map(function($img) {
            return [
                'id' => $img->id,
                'title' => $img->title ?: $img->image_type_label,
                'type_label' => $img->image_type_label,
                'type' => $img->image_type_label,
                'is_featured' => $img->is_featured,
                'source' => $img->source ?? 'manual',
                'created_at' => $img->created_at ? $img->created_at->toISOString() : null,
                'url' => asset('storage/' . $img->file_path),
            ];
        });

        $uploadLink = route('vehicles.public-images.form', [
            'vehicle' => $vehicle->id, 
            'token' => $vehicle->public_image_upload_token
        ]);

        return [
            'images' => $images,
            'driver_upload_link' => $uploadLink
        ];
    }

    public function getReports(int $companyId, int $vehicleId, string $reportsMonth): ?array
    {
        $vehicle = Vehicle::where('company_id', $companyId)->find($vehicleId);
        if (!$vehicle) return null;

        $reportsStartDate = Carbon::parse($reportsMonth . '-01')->startOfMonth();
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

        return [
            'reports_month' => $reportsMonth,
            'totals' => $reportTotals,
            'details' => array_values($monthlyReports)
        ];
    }
}
