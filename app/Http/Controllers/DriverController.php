<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DriverVehicleAssignment;
use App\Models\Fleet\Driver;
use App\Models\Fleet\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use ZipArchive;
use App\Imports\DriversImport;

class DriverController extends Controller
{
    protected array $driverDocumentTypes = [
        'Kimlik ve Ehliyet',
        'Adli Sicil Kaydı',
        'SRC 1 Belgesi',
        'SRC 2 Belgesi',
        'Psikoteknik Belgesi',
        'MYK Belgesi',
        'İkametgah Belgesi',
        'Sağlık Raporu Belgesi',
        'İş Sözleşmesi',
        'İşten Çıkış Belgesi',
    ];

    public function index(Request $request)
    {
        if (!auth()->user()->hasPermission('drivers.view')) {
            abort(403, 'Personel listesini görme yetkiniz yok.');
        }
        $query = Driver::with(['vehicle', 'documents'])
            ->where('approval_status', '!=', 'pending')
            ->latest();

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%')
                    ->orWhere('tc_no', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('license_class', 'like', '%' . $search . '%')
                    ->orWhere('src_type', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            }

            if ($request->status === 'passive') {
                $query->where('is_active', false);
            }
        }

        if ($request->filled('document_status')) {
            $query->whereHas('documents', function($q) use ($request) {
                $today = now()->startOfDay();
                $sevenDaysLater = now()->copy()->addDays(7)->endOfDay();
                $thirtyDaysLater = now()->copy()->addDays(30)->endOfDay();

                $q->where(function($sq) {
                    $sq->where('file_path', 'not like', '%.jpg')
                      ->where('file_path', 'not like', '%.jpeg')
                      ->where('file_path', 'not like', '%.png')
                      ->where('file_path', 'not like', '%.webp');
                });

                match ($request->document_status) {
                    'expired' => $q->whereNotNull('end_date')->whereDate('end_date', '<', $today),
                    'expiring' => $q->whereNotNull('end_date')->whereBetween('end_date', [$today, $thirtyDaysLater]),
                    'ok' => $q->where(function($sq) use ($thirtyDaysLater) {
                        $sq->whereNull('end_date')->orWhereDate('end_date', '>', $thirtyDaysLater);
                    }),
                    default => null
                };
            });
        }

        $drivers = $query->paginate(12)->through(function ($driver) {
            $driver->resolved_document_status = $this->resolveDriverDocumentStatus($driver);
            return $driver;
        });

        $vehicles = Vehicle::orderBy('plate')->get();
        $allDrivers = Driver::with('documents')->where('approval_status', '!=', 'pending')->get()->map(function ($driver) {
            $driver->resolved_document_status = $this->resolveDriverDocumentStatus($driver);
            return $driver;
        });

        $totalDrivers = $allDrivers->count();
        $activeDrivers = $allDrivers->where('is_active', true)->count();
        $passiveDrivers = $allDrivers->where('is_active', false)->count();

        $expiredDocumentCount = $allDrivers->filter(function ($driver) {
            return ($driver->resolved_document_status['priority'] ?? 'ok') === 'expired';
        })->count();

        $expiringSoonCount = $allDrivers->filter(function ($driver) {
            return in_array(($driver->resolved_document_status['priority'] ?? 'ok'), ['soon7', 'expiring'], true);
        })->count();

        $pendingDrivers = Driver::where('approval_status', 'pending')->latest()->get();

        return view('drivers.index', compact(
            'drivers',
            'vehicles',
            'totalDrivers',
            'activeDrivers',
            'passiveDrivers',
            'expiredDocumentCount',
            'expiringSoonCount',
            'pendingDrivers'
        ));
    }

    public function approve(Driver $driver)
    {
        if (!auth()->user()->hasPermission('drivers.edit')) {
            abort(403, 'Personel düzenleme yetkiniz yok.');
        }

        $driver->update([
            'approval_status' => 'approved',
            'is_active' => true,
            'start_date' => now()->toDateString()
        ]);

        return redirect()->back()->with('success', 'Personel kaydı onaylandı ve aktif hale getirildi.');
    }

    public function reject(Driver $driver)
    {
        if (!auth()->user()->hasPermission('drivers.delete')) {
            abort(403, 'Personel silme yetkiniz yok.');
        }

        $driver->delete();

        return redirect()->back()->with('success', 'Personel kaydı reddedildi ve sistemden silindi.');
    }

    public function toggleInviteLink()
    {
        if (!auth()->user()->isSuperAdmin() && !auth()->user()->isCompanyAdmin()) {
            abort(403, 'Bu işlem için yetkiniz yok.');
        }

        $company = auth()->user()->company;
        $company->update([
            'is_driver_invite_active' => !$company->is_driver_invite_active
        ]);

        return redirect()->back()->with('success', 'Davet linki durumu güncellendi.');
    }

    public function create()
    {
        if (!auth()->user()->hasPermission('drivers.create')) {
            abort(403, 'Personel ekleme yetkiniz yok.');
        }
        $vehicles = Vehicle::orderBy('plate')->get();

        return view('drivers.create', compact('vehicles'));
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasPermission('drivers.create')) {
            abort(403, 'Personel ekleme yetkiniz yok.');
        }
        $validated = $request->validate([
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'full_name' => 'required|string|max:255',
            'tc_no' => 'nullable|string|max:20|unique:drivers,tc_no',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'license_class' => 'nullable|string|max:50',
            'src_type' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'start_shift' => 'nullable|string|in:morning,evening',
            'base_salary' => 'nullable|numeric|min:0',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $driver = Driver::create($validated);

        // Araç zimmet geçmişi kaydı oluştur
        if (!empty($validated['vehicle_id'])) {
            DriverVehicleAssignment::create([
                'company_id' => $driver->company_id,
                'driver_id' => $driver->id,
                'vehicle_id' => $validated['vehicle_id'],
                'assigned_at' => $validated['start_date'] ?? now()->toDateString(),
                'assigned_shift' => $validated['start_shift'] ?? 'morning',
            ]);
        }

        return redirect()
            ->route('drivers.index')
            ->with('success', 'Personel başarıyla eklendi.');
    }

    public function importExcel(Request $request)
    {
        if (!auth()->user()->hasPermission('drivers.create')) {
            abort(403, 'Personel ekleme yetkiniz yok.');
        }

        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        try {
            Excel::import(new DriversImport, $request->file('excel_file'));
            return redirect()->back()->with('success', 'Personeller başarıyla içe aktarıldı.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'İçe aktarma sırasında bir hata oluştu: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        return Excel::download(new \App\Exports\DriversTemplateExport, 'personel_sablonu.xlsx');
    }

    public function show(Request $request, Driver $driver)
    {
        if (!auth()->user()->hasPermission('drivers.view')) {
            abort(403, 'Personel detaylarını görme yetkiniz yok.');
        }
        $driver->load([
            'vehicle',
            'payrolls',
            'documents',
        ]);

        $activeTab = $request->get('tab', 'general');

        // Eski linklerden work-documents gelirse otomatik documents'e yönlensin
        if ($activeTab === 'work-documents') {
            $activeTab = 'documents';
        }

        $driverAge = null;
        if ($driver->birth_date) {
            try {
                $driverAge = $driver->birth_date->age;
            } catch (\Throwable $e) {
                $driverAge = null;
            }
        }

        $serviceYears = null;
        if ($driver->start_date) {
            try {
                $serviceYears = $driver->start_date->diffInYears(now());
            } catch (\Throwable $e) {
                $serviceYears = null;
            }
        }

        $documents = $driver->documents
            ->sortByDesc(function ($document) {
                return optional($document->created_at)->timestamp ?? 0;
            })
            ->values()
            ->map(function ($document) use ($driver) {
                return $this->decorateDriverDocument($document, $driver);
            });

        $isImageDocument = function ($document) {
            $extension = strtolower(pathinfo((string) $document->file_path, PATHINFO_EXTENSION));
            return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);
        };

        $imageDocuments = $documents->filter(function ($document) use ($isImageDocument) {
            $extension = strtolower(pathinfo((string) $document->file_path, PATHINFO_EXTENSION));
            return $isImageDocument($document) || ($extension === 'pdf' && $document->document_type === 'Kimlik ve Ehliyet');
        })->values();

        $allDocumentDocuments = $documents->filter(function ($document) use ($isImageDocument) {
            return !$isImageDocument($document);
        });

        $documentDocuments = $allDocumentDocuments->filter(function ($doc) {
            return is_null($doc->archived_at)
                && (is_null($doc->end_date) || $doc->end_date->startOfDay()->gte(now()->startOfDay()));
        })->values();

        $archivedDocumentDocuments = $allDocumentDocuments->filter(function ($doc) {
            return !is_null($doc->archived_at)
                || (!is_null($doc->end_date) && $doc->end_date->startOfDay()->lt(now()->startOfDay()));
        })->values();

        $featuredImage = $imageDocuments->first();

        $lastPayroll = $driver->payrolls->sortByDesc('period_month')->first();
        $totalPayrollCount = $driver->payrolls->count();
        $documentCount = $documents->count();
        $imageCount = $imageDocuments->count();

        $totalNetSalary = (float) $driver->payrolls->sum('net_salary');
        $totalGrossSalary = (float) $driver->payrolls->sum('gross_salary');

        $documentStatus = 'ok';
        if ($documentDocuments->contains(fn ($doc) => $doc->alert_status === 'expired')) {
            $documentStatus = 'expired';
        } elseif ($documentDocuments->contains(fn ($doc) => $doc->alert_status === 'expiring')) {
            $documentStatus = 'expiring';
        }

        $driverDocumentTypes = $this->driverDocumentTypes;

        $allDriverTrips = \App\Models\Trip::with(['vehicle', 'morningVehicle', 'eveningVehicle'])
            ->where('company_id', $driver->company_id)
            ->where(function ($q) use ($driver) {
                $q->where('driver_id', $driver->id)
                  ->orWhere('morning_driver_id', $driver->id)
                  ->orWhere('evening_driver_id', $driver->id);
            })->get();

        $vehicleHistoryMap = [];
        foreach ($allDriverTrips as $trip) {
            $vehiclesDriven = [];
            
            $mVehicleId = $trip->morning_vehicle_id ?? $trip->vehicle_id;
            $mVehicleModel = $trip->morningVehicle ?? $trip->vehicle;
            
            $eVehicleId = $trip->evening_vehicle_id ?? $trip->vehicle_id;
            $eVehicleModel = $trip->eveningVehicle ?? $trip->vehicle;

            // Morning check
            if ($trip->morning_driver_id == $driver->id) {
                $vehiclesDriven[] = ['id' => $mVehicleId, 'model' => $mVehicleModel];
            } elseif (!$trip->morning_driver_id && $trip->driver_id == $driver->id) {
                $isAmbiguous = $mVehicleId && $eVehicleId && ($mVehicleId != $eVehicleId);
                if ($isAmbiguous) {
                    if ($driver->vehicle_id == $mVehicleId) {
                        $vehiclesDriven[] = ['id' => $mVehicleId, 'model' => $mVehicleModel];
                    }
                } else {
                    $vehiclesDriven[] = ['id' => $mVehicleId, 'model' => $mVehicleModel];
                }
            } elseif (!$trip->morning_driver_id && !$trip->driver_id) {
                if ($mVehicleId && $driver->vehicle_id == $mVehicleId) {
                    $vehiclesDriven[] = ['id' => $mVehicleId, 'model' => $mVehicleModel];
                }
            }

            // Evening check
            if ($trip->evening_driver_id == $driver->id) {
                $vehiclesDriven[] = ['id' => $eVehicleId, 'model' => $eVehicleModel];
            } elseif (!$trip->evening_driver_id && $trip->driver_id == $driver->id) {
                $isAmbiguous = $mVehicleId && $eVehicleId && ($mVehicleId != $eVehicleId);
                if ($isAmbiguous) {
                    if ($driver->vehicle_id == $eVehicleId) {
                        $vehiclesDriven[] = ['id' => $eVehicleId, 'model' => $eVehicleModel];
                    }
                } else {
                    $vehiclesDriven[] = ['id' => $eVehicleId, 'model' => $eVehicleModel];
                }
            } elseif (!$trip->evening_driver_id && !$trip->driver_id) {
                if ($eVehicleId && $driver->vehicle_id == $eVehicleId) {
                    $vehiclesDriven[] = ['id' => $eVehicleId, 'model' => $eVehicleModel];
                }
            }
            
            $vehiclesDriven = collect($vehiclesDriven)->unique('id')->all();
            
            foreach ($vehiclesDriven as $v) {
                $vId = $v['id'];
                if (!$vId) continue;
                if (!isset($vehicleHistoryMap[$vId])) {
                    $vehicleHistoryMap[$vId] = ['vehicle' => $v['model'], 'start' => $trip->trip_date, 'end' => $trip->trip_date, 'count' => 0];
                }
                $vehicleHistoryMap[$vId]['count']++;
                if ($trip->trip_date < $vehicleHistoryMap[$vId]['start']) $vehicleHistoryMap[$vId]['start'] = $trip->trip_date;
                if ($trip->trip_date > $vehicleHistoryMap[$vId]['end']) $vehicleHistoryMap[$vId]['end'] = $trip->trip_date;
            }
        }
        $vehicleHistory = collect($vehicleHistoryMap)->sortByDesc('end')->values();

        return view('drivers.show', compact(
            'driver',
            'activeTab',
            'documentStatus',
            'driverAge',
            'serviceYears',
            'lastPayroll',
            'totalPayrollCount',
            'documentCount',
            'imageCount',
            'documents',
            'documentDocuments',
            'archivedDocumentDocuments',
            'imageDocuments',
            'featuredImage',
            'totalNetSalary',
            'totalGrossSalary',
            'driverDocumentTypes',
            'vehicleHistory'
        ));
    }

    public function uploadDocument(Request $request, Driver $driver)
    {
        $validated = $request->validate([
            'document_type' => ['required', 'string', Rule::in($this->driverDocumentTypes)],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx|max:20480',
            'redirect_tab' => 'nullable|string|in:documents,images',
        ]);

        $startDate = !empty($validated['start_date']) ? Carbon::parse($validated['start_date']) : null;
        $endDate = !empty($validated['end_date']) ? Carbon::parse($validated['end_date']) : null;
        $documentType = $validated['document_type'];

        if ($documentType === 'Adli Sicil Kaydı' && $startDate) {
            $endDate = $startDate->copy()->addMonthsNoOverflow(6);
        }

        if (in_array($documentType, ['Psikoteknik Belgesi', 'MYK Belgesi'], true) && $startDate) {
            $endDate = $startDate->copy()->addYears(5);
        }

        if (in_array($documentType, ['SRC 1 Belgesi', 'SRC 2 Belgesi'], true) && $driver->birth_date) {
            $endDate = Carbon::parse($driver->birth_date)->copy()->addYears(69);
        }

        $filePath = $request->file('file')->store('driver-documents', 'public');

        $driver->documents()
            ->whereNull('archived_at')
            ->where('document_type', $documentType)
            ->update(['archived_at' => now()]);

        $driver->documents()->create([
            'document_type' => $documentType,
            'document_name' => $documentType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'file_path' => $filePath,
            'is_active' => true,
            'notes' => $validated['notes'] ?? null,
        ]);

        $redirectTab = $validated['redirect_tab'] ?? 'documents';

        return redirect()
            ->route('drivers.show', [
                'driver' => $driver->id,
                'tab' => $redirectTab,
            ])
            ->with('success', 'Belge başarıyla yüklendi.');
    }

    public function cropPhoto(Request $request, Driver $driver)
    {
        $request->validate([
            'image' => 'required|string',
        ]);

        $imageData = $request->image;
        if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
            $imageData = substr($imageData, strpos($imageData, ',') + 1);
            $type = strtolower($type[1]); // jpg, png, etc

            if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png', 'webp'])) {
                return response()->json(['success' => false, 'message' => 'Geçersiz resim formatı.'], 422);
            }

            $imageData = base64_decode($imageData);

            if ($imageData === false) {
                return response()->json(['success' => false, 'message' => 'Resim verisi çözülemedi.'], 422);
            }
        } else {
            return response()->json(['success' => false, 'message' => 'Geçersiz resim verisi.'], 422);
        }

        $fileName = 'cropped_' . time() . '.' . $type;
        $filePath = 'driver-documents/' . $fileName;

        Storage::disk('public')->put($filePath, $imageData);

        $driver->documents()->create([
            'document_type' => 'Kimlik ve Ehliyet',
            'document_name' => 'Profil Fotoğrafı (Kırpılmış)',
            'file_path' => $filePath,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profil fotoğrafı başarıyla oluşturuldu.',
            'redirect' => route('drivers.show', ['driver' => $driver->id, 'tab' => 'general'])
        ]);
    }

    public function deleteDocument(Driver $driver, Document $document)
    {
        if (
            $document->documentable_type !== Driver::class ||
            (int) $document->documentable_id !== (int) $driver->id
        ) {
            abort(404);
        }

        if (!empty($document->file_path) && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return redirect()
            ->back()
            ->with('success', 'Belge silindi.');
    }

    public function edit(Driver $driver)
    {
        if (!auth()->user()->hasPermission('drivers.edit')) {
            abort(403, 'Personel düzenleme yetkiniz yok.');
        }
        $vehicles = Vehicle::orderBy('plate')->get();

        return view('drivers.edit', compact('driver', 'vehicles'));
    }

    public function update(Request $request, Driver $driver)
    {
        if (!auth()->user()->hasPermission('drivers.edit')) {
            abort(403, 'Personel düzenleme yetkiniz yok.');
        }
        $validated = $request->validate([
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'full_name' => 'required|string|max:255',
            'tc_no' => 'nullable|string|max:20|unique:drivers,tc_no,' . $driver->id,
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'license_class' => 'nullable|string|max:50',
            'src_type' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'start_shift' => 'nullable|string|in:morning,evening',
            'base_salary' => 'nullable|numeric|min:0',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $validated['is_active'] = $request->has('is_active');

        // Araç değiştiyse zimmet geçmişi kaydet
        $oldVehicleId = $driver->vehicle_id;
        $newVehicleId = $validated['vehicle_id'] ?? null;

        if ($oldVehicleId != $newVehicleId) {
            // Eski zimmeti kapat
            if ($oldVehicleId) {
                $activeAssignment = DriverVehicleAssignment::where('driver_id', $driver->id)
                    ->where('vehicle_id', $oldVehicleId)
                    ->whereNull('unassigned_at')
                    ->first();

                if ($activeAssignment) {
                    $activeAssignment->close(now()->toDateString(), 'full_day');
                }
            }

            // Yeni zimmet aç
            if ($newVehicleId) {
                DriverVehicleAssignment::create([
                    'company_id' => $driver->company_id,
                    'driver_id' => $driver->id,
                    'vehicle_id' => $newVehicleId,
                    'assigned_at' => now()->toDateString(),
                    'assigned_shift' => 'morning',
                ]);
            }
        }

        $driver->update($validated);

        return redirect()
            ->route('drivers.index')
            ->with('success', 'Personel güncellendi.');
    }

    public function destroy(Driver $driver)
    {
        if (!auth()->user()->hasPermission('drivers.delete')) {
            abort(403, 'Personel silme yetkiniz yok.');
        }
        $driver->delete();

        return redirect()
            ->route('drivers.index')
            ->with('success', 'Personel silindi.');
    }

    public function leaveWork(Request $request, Driver $driver)
    {
        $validated = $request->validate([
            'leave_date' => 'required|date',
            'leave_shift' => 'required|string|in:morning,evening,full_day',
        ]);

        // Aktif araç zimmetini kapat
        $activeAssignment = DriverVehicleAssignment::where('driver_id', $driver->id)
            ->whereNull('unassigned_at')
            ->first();

        if ($activeAssignment) {
            $activeAssignment->close(
                $validated['leave_date'],
                $validated['leave_shift']
            );
        }

        $driver->update([
            'is_active' => false,
            'leave_date' => $validated['leave_date'],
            'leave_shift' => $validated['leave_shift'],
            // vehicle_id'yi silmiyoruz — geriye uyumluluk ve fallback için tutuluyor
        ]);

        return redirect()->back()->with('success', 'Personel işten ayrılma kaydı başarıyla yapıldı.');
    }

    public function changeVehicle(Request $request, Driver $driver)
    {
        $validated = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'assignment_date' => 'nullable|date',
            'assignment_shift' => 'nullable|string|in:morning,evening,full_day',
        ]);

        $assignmentDate = $validated['assignment_date'] ?? now()->toDateString();
        $assignmentShift = $validated['assignment_shift'] ?? 'morning';
        $oldVehicleId = $driver->vehicle_id;
        $newVehicleId = $validated['vehicle_id'];

        // Aynı araçsa işlem yapma
        if ((int) $oldVehicleId === (int) $newVehicleId) {
            return redirect()->back()->with('info', 'Personel zaten bu araca atanmış.');
        }

        // Eski aktif zimmeti kapat
        $activeAssignment = DriverVehicleAssignment::where('driver_id', $driver->id)
            ->whereNull('unassigned_at')
            ->first();

        if ($activeAssignment) {
            // Eski zimmeti, yeni zimmetin bir gün öncesinde kapat
            $closeDate = Carbon::parse($assignmentDate)->subDay()->toDateString();
            // Eğer kapanış tarihi açılış tarihinden önceyse, açılış tarihini kullan
            if ($closeDate < $activeAssignment->assigned_at->toDateString()) {
                $closeDate = $activeAssignment->assigned_at->toDateString();
            }
            $activeAssignment->close($closeDate, 'full_day');
        }

        // Yeni zimmet kaydı oluştur
        DriverVehicleAssignment::create([
            'company_id' => $driver->company_id,
            'driver_id' => $driver->id,
            'vehicle_id' => $newVehicleId,
            'assigned_at' => $assignmentDate,
            'assigned_shift' => $assignmentShift,
        ]);

        // Geriye uyumluluk: vehicle_id'yi de güncelle
        $driver->update([
            'vehicle_id' => $newVehicleId,
        ]);

        return redirect()->back()->with('success', 'Personel araç ataması başarıyla güncellendi. Zimmet geçmişi kaydedildi.');
    }

    public function downloadDocumentsZip(Driver $driver)
    {
        if (!auth()->user()->hasPermission('drivers.view')) {
            abort(403, 'Personel belgelerini indirme yetkiniz yok.');
        }

        $documents = $driver->documents()->get();

        if ($documents->isEmpty()) {
            return redirect()->back()->with('error', 'Bu personele ait indirilebilir belge bulunamadı.');
        }

        // Türkçe karakterleri ASCII'ye çevir
        $safeName = str_replace(
            ['ç','Ç','ğ','Ğ','ı','İ','ö','Ö','ş','Ş','ü','Ü',' '],
            ['c','C','g','G','i','I','o','O','s','S','u','U','_'],
            $driver->full_name
        );
        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '', $safeName);

        $zipFileName = $safeName . '_' . now()->format('d-m-Y') . '.zip';
        $zipPath = storage_path('app/' . $zipFileName);

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'ZIP dosyası oluşturulamadı.');
        }

        $addedFiles = 0;

        foreach ($documents as $document) {
            if (!empty($document->file_path) && Storage::disk('public')->exists($document->file_path)) {
                $absolutePath = Storage::disk('public')->path($document->file_path);
                $extension = pathinfo($absolutePath, PATHINFO_EXTENSION);

                $docType = preg_replace('/[\\\\\/:"*?<>|]+/', '-', ($document->document_type ?: 'Belge'));
                $docName = preg_replace('/[\\\\\/:"*?<>|]+/', '-', ($document->document_name ?: 'Dosya'));

                $fileNameInZip = $docType . '/' . $docName . '_' . $document->id . '.' . $extension;

                $zip->addFile($absolutePath, $fileNameInZip);
                $addedFiles++;
            }
        }

        $zip->close();

        if ($addedFiles === 0) {
            @unlink($zipPath);
            return redirect()->back()->with('error', 'Bu personele ait indirilebilir dosya bulunamadı.');
        }

        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
    }

    protected function resolveDriverDocumentStatus(Driver $driver): array
    {
        $documents = $driver->documents->filter(function($doc) {
            return is_null($doc->archived_at);
        })
        ->sortByDesc('created_at')
        ->unique('document_type')
        ->map(function ($document) use ($driver) {
            return $this->decorateDriverDocument($document, $driver);
        });

        $nonImageDocuments = $documents->filter(function ($document) {
            $extension = strtolower(pathinfo((string) $document->file_path, PATHINFO_EXTENSION));
            return !in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);
        });

        if ($nonImageDocuments->contains(fn ($document) => $document->alert_status === 'expired')) {
            return [
                'label' => 'Süresi Geçmiş',
                'class' => 'bg-rose-100 text-rose-700',
                'priority' => 'expired',
            ];
        }

        if ($nonImageDocuments->contains(fn ($document) => $document->alert_status === 'expiring')) {
            return [
                'label' => '7 Gün İçinde Bitecek',
                'class' => 'bg-orange-100 text-orange-700',
                'priority' => 'soon7',
            ];
        }

        if ($nonImageDocuments->contains(fn ($document) => $document->alert_status === 'active_soon')) {
            return [
                'label' => 'Yakında Bitecek',
                'class' => 'bg-amber-100 text-amber-700',
                'priority' => 'expiring',
            ];
        }

        return [
            'label' => 'Uygun',
            'class' => 'bg-emerald-100 text-emerald-700',
            'priority' => 'ok',
        ];
    }

    protected function decorateDriverDocument(Document $document, Driver $driver): Document
    {
        $today = now()->startOfDay();
        $sevenDaysLater = now()->copy()->addDays(7)->endOfDay();
        $thirtyDaysLater = now()->copy()->addDays(30)->endOfDay();

        $document->remaining_days = null;
        $document->alert_status = null;
        $document->alert_text = 'Süresiz belge';

        if ($document->end_date) {
            $remainingDays = $today->diffInDays(Carbon::parse($document->end_date)->startOfDay(), false);
            $document->remaining_days = $remainingDays;

            if ($remainingDays < 0) {
                $document->alert_status = 'expired';
                $document->alert_text = 'Süresi doldu';
            } elseif ($remainingDays <= 7) {
                $document->alert_status = 'expiring';
                $document->alert_text = $remainingDays . ' gün kaldı';
            } elseif (Carbon::parse($document->end_date)->between($today, $thirtyDaysLater)) {
                $document->alert_status = 'active_soon';
                $document->alert_text = $remainingDays . ' gün kaldı';
            } else {
                $document->alert_status = 'active';
                $document->alert_text = $remainingDays . ' gün kaldı';
            }
        }

        return $document;
    }

    /**
     * Belge türüne göre uyarı eşiğini gün cinsinden döndürür.
     * Ehliyet → 30 gün (1 ay)
     * SRC Belgeleri → 30 gün (1 ay)
     * Adli Sicil → 7 gün (1 hafta)
     * Psikoteknik → 7 gün (1 hafta)
     * Diğer tüm belgeler → 7 gün (1 hafta)
     */
    public static function getAlertThresholdDays(?string $documentType): int
    {
        $type = mb_strtolower(trim($documentType ?? ''), 'UTF-8');

        // Ehliyet - 1 ay (30 gün)
        if (str_contains($type, 'ehliyet') || str_contains($type, 'kimlik ve ehliyet')) {
            return 30;
        }

        // SRC Belgeleri - 1 ay (30 gün)
        if (str_contains($type, 'src')) {
            return 30;
        }

        // Adli Sicil - 1 hafta (7 gün)
        if (str_contains($type, 'adli sicil')) {
            return 7;
        }

        // Psikoteknik - 1 hafta (7 gün)
        if (str_contains($type, 'psikoteknik')) {
            return 7;
        }

        // Diğer tüm belgeler - 1 hafta (7 gün)
        return 7;
    }

    public static function getDriverDocumentAlertsForLayout(): array
    {
        $today = now()->startOfDay();

        return Document::query()
            ->where('documentable_type', Driver::class)
            ->whereNull('archived_at') // Arşivlenmiş belgeleri yoksay
            ->whereNotNull('end_date')
            ->with('documentable')
            ->orderByDesc('end_date')
            ->get()
            ->filter(fn ($doc) => $doc->documentable && $doc->documentable->is_active)
            ->unique(fn ($doc) => $doc->documentable_id . '_' . $doc->document_type) // Sadece en güncel belgeyi tut
            ->filter(function ($doc) use ($today) {
                // Her belge türüne özel eşik uygula
                $endDate = Carbon::parse($doc->end_date)->startOfDay();
                $remainingDays = $today->diffInDays($endDate, false);
                $threshold = self::getAlertThresholdDays($doc->document_type ?: $doc->document_name);

                // Süresi geçmiş VEYA eşik içindeki belgeleri göster
                return $remainingDays < 0 || $remainingDays <= $threshold;
            })
            ->sortBy('end_date')
            ->map(function ($doc) use ($today) {
                $endDate = Carbon::parse($doc->end_date)->startOfDay();
                $remainingDays = $today->diffInDays($endDate, false);
                $docType = $doc->document_type ?: $doc->document_name;
                $threshold = self::getAlertThresholdDays($docType);

                // Uyarı seviyesi belirle
                if ($remainingDays < 0) {
                    $status = 'expired';
                    $urgency = 'critical';
                } elseif ($remainingDays <= 7) {
                    $status = 'expiring';
                    $urgency = 'high';
                } else {
                    $status = 'expiring';
                    $urgency = 'medium';
                }

                return [
                    'document_id' => $doc->id,
                    'driver_id' => $doc->documentable_id,
                    'driver_name' => $doc->documentable?->full_name ?? 'Personel',
                    'document_type' => $docType,
                    'end_date' => $doc->end_date,
                    'remaining_days' => $remainingDays,
                    'threshold_days' => $threshold,
                    'status' => $status,
                    'urgency' => $urgency,
                    'route' => route('drivers.show', [
                        'driver' => $doc->documentable_id,
                        'tab' => 'documents',
                    ]),
                ];
            })
            ->values()
            ->all();
    }
}