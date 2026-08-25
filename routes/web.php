<?php

/**
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  WEB PANEL ROUTES                                                        ║
 * ║  If you add functionality here that is also relevant for mobile users,   ║
 * ║  you MUST add the equivalent endpoint to routes/api.php and the          ║
 * ║  corresponding screen/api call in mobile-app/.                           ║
 * ║  Web-only routes (admin-panel/companies, exports, email previews) are    ║
 * ║  exempt — see WEB_MOBIL_SENKRON_KURALLARI.md for the exemption list.     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */

use App\Models\Trip;
use App\Models\Fuel;
use App\Models\Document;
use App\Models\Payroll;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\TrafficPenalty;
use App\Models\VehicleMaintenance;
use App\Models\Fleet\Driver;
use App\Models\Fleet\Vehicle;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TripController;
use App\Http\Controllers\FuelController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RouteStopController;
use App\Http\Controllers\FuelStationController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\CompanyUserController;
use App\Http\Controllers\ServiceRouteController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\TrafficPenaltyController;
use App\Http\Controllers\CustomerContractController;
use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\CustomerPortalUserController;
use App\Http\Controllers\CustomerServiceRouteController;
use App\Http\Controllers\VehicleTrackingController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\CompanyController as SuperAdminCompanyController;

Route::get('/run-migrations-secret', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        return "Veritabanı başarıyla güncellendi! Çıktı:<br><pre>" . \Illuminate\Support\Facades\Artisan::output() . "</pre>";
    } catch (\Exception $e) {
        return "Hata oluştu: " . $e->getMessage();
    }
});

Route::get('/clear-cache-secret', function() {
    \Illuminate\Support\Facades\Artisan::call('optimize:clear');
    \Illuminate\Support\Facades\Artisan::call('cache:clear');
    \Illuminate\Support\Facades\Artisan::call('view:clear');
    \Illuminate\Support\Facades\Artisan::call('config:clear');
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    return 'Tüm önbellekler (Cache, Config, Views, OPcache) başarıyla temizlendi!';
});

Route::get('/', function () {
    return redirect()->route('login');
});

// Taşeron (Ortak Girişim) Portalı
Route::prefix('portal/jv/{token}')->name('portal.jv.')->group(function () {
    Route::get('/', [\App\Http\Controllers\JointVenturePortalController::class, 'index'])->name('index');
    Route::get('/routes/{route}/documents', [\App\Http\Controllers\JointVenturePortalController::class, 'documents'])->name('documents.index');
    Route::post('/routes/{route}/documents', [\App\Http\Controllers\JointVenturePortalController::class, 'storeDocument'])->name('documents.store');
    Route::delete('/routes/{route}/documents/{document}', [\App\Http\Controllers\JointVenturePortalController::class, 'destroyDocument'])->name('documents.destroy');
});

Route::get('/run-migrations', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        return 'Veritabanı güncellemeleri (migrations) başarıyla tamamlandı! <br><br> Çıktı: <br><pre>' . \Illuminate\Support\Facades\Artisan::output() . '</pre>';
    } catch (\Exception $e) {
        return 'Hata oluştu: ' . $e->getMessage();
    }
});

/*
|--------------------------------------------------------------------------
| PUBLIC PAGES (Privacy Policy - required by App Store & Google Play)
|--------------------------------------------------------------------------
*/
Route::get('/privacy-policy', function () {
    return view('privacy-policy');
})->name('privacy-policy');

// Geçici: Yeni yetkileri veritabanına ekle (kullandıktan sonra SİLİNECEK)
Route::get('/run-permission-seeder-2026', function () {
    \Artisan::call('db:seed', ['--class' => 'UpdatePermissionsSeeder', '--force' => true]);
    return 'Tüm yetkiler başarıyla güncellendi! ✅ Bu rotayı artık silebilirsiniz.';
});

Route::get('/gizlilik', function () {
    return view('privacy-policy');
})->name('gizlilik');

/*
|--------------------------------------------------------------------------
| CRON TRIGGER (cPanel uyumlu — auth gerektirmez)
| cPanel cron komutu: curl -s "https://domain.com/app/cron/backup?key=spilot-cron-2026" > /dev/null
|--------------------------------------------------------------------------
*/
Route::get('/cron/backup', [\App\Http\Controllers\BackupController::class, 'cronTrigger']);

Route::get('/cron/maintenance-check', function (\Illuminate\Http\Request $request) {
    if ($request->key !== 'filo-deploy-2026') {
        abort(403, 'Yetkisiz erişim');
    }
    \Illuminate\Support\Facades\Artisan::call('maintenance:check-health');
    return "Bakım kontrolü çalıştırıldı: " . \Illuminate\Support\Facades\Artisan::output();
});

Route::get('/cron/deploy', function (\Illuminate\Http\Request $request) {
    if ($request->key !== 'filo-deploy-2026') {
        abort(403, 'Yetkisiz erişim');
    }
    
    // 1. Cache'leri temizle (View, Route, Config vb.)
    \Illuminate\Support\Facades\Artisan::call('optimize:clear');
    echo "CACHE TEMIZLENDI\n\n";

    // DEBUG MUSTAFA
    $mustafa = \App\Models\Fleet\Driver::where('full_name', 'like', '%Mustafa PEKUYAR%')->first();
    if ($mustafa) {
        $docs = $mustafa->documents()->get(['id', 'document_type', 'start_date', 'end_date', 'archived_at']);
        echo "MUSTAFA DOCS: \n" . json_encode($docs) . "\n\n";
    }

    // 2. Git Pull İşlemi
    $repoPath = '/home/u2545454/repositories/filomerkez_v2';
    
    $pullOutput = 'shell_exec disabled';
    $deployOutput = 'shell_exec disabled';
    
    if (function_exists('shell_exec')) {
        // 2. Github'dan yeni kodları çek (Update from remote)
        $pullOutput = shell_exec("cd {$repoPath} && git pull origin main 2>&1");
        
        // 3. cPanel deploy işlemini tetikle (Deploy HEAD commit)
        $deployOutput = shell_exec("/usr/local/cpanel/bin/uapi VersionControl deploy repository_root={$repoPath} 2>&1");
    }
    
    return "CACHE TEMIZLENDI\n\nPULL SONUCU:\n" . $pullOutput . "\n\nDEPLOY SONUCU:\n" . $deployOutput;
});
/*
|--------------------------------------------------------------------------
| PUBLIC VEHICLE IMAGE UPLOAD
|--------------------------------------------------------------------------
*/
Route::get('/vehicle-photo-upload/{vehicle}/{token}', [VehicleController::class, 'publicImageUploadForm'])
    ->name('vehicles.public-images.form');

Route::post('/vehicle-photo-upload/{vehicle}/{token}', [VehicleController::class, 'publicImageUploadStore'])
    ->name('vehicles.public-images.store');

Route::get('/dashboard', function () {

    $vehicleCount = Vehicle::count();
    $driverCount = Driver::count();
    $customerCount = Customer::count();

    $todayTrips = Trip::whereDate('trip_date', now()->toDateString())->count();

    $monthlyIncome = Trip::whereMonth('trip_date', now()->month)
        ->whereYear('trip_date', now()->year)
        ->sum('trip_price');

    $monthlyFuel = Fuel::whereMonth('date', now()->month)
        ->whereYear('date', now()->year)
        ->sum('total_cost');

    $monthlyPenalty = TrafficPenalty::whereMonth('penalty_date', now()->month)
        ->whereYear('penalty_date', now()->year)
        ->sum('penalty_amount');

    $activeMaintenances = VehicleMaintenance::where('status', 'in_progress')->count();
    $waitingMaintenances = VehicleMaintenance::where('status', 'waiting')->count();

    $today = now()->startOfDay();
    $sevenDaysLater = now()->copy()->addDays(7)->startOfDay();
    $thirtyDaysLater = now()->copy()->addDays(30)->startOfDay();

    $expiredDocumentsCount = Document::whereNotNull('end_date')
        ->whereDate('end_date', '<', $today)
        ->count();

    $documentsExpiringIn7DaysCount = Document::whereNotNull('end_date')
        ->whereDate('end_date', '>=', $today)
        ->whereDate('end_date', '<=', $sevenDaysLater)
        ->count();

    $documentsExpiringIn30DaysCount = Document::whereNotNull('end_date')
        ->whereDate('end_date', '>', $sevenDaysLater)
        ->whereDate('end_date', '<=', $thirtyDaysLater)
        ->count();

    $upcomingDocuments = Document::whereNotNull('end_date')
        ->whereDate('end_date', '>=', $today)
        ->whereDate('end_date', '<=', $thirtyDaysLater)
        ->orderBy('end_date')
        ->take(10)
        ->get();

    $totalFuel = Fuel::sum('total_cost');
    $totalSalary = Payroll::sum('net_salary');
    $netProfit = $monthlyIncome - ($totalFuel + $totalSalary);

    $recentTrips = Trip::with(['vehicle', 'driver'])
        ->orderBy('trip_date', 'desc')
        ->orderBy('id', 'desc')
        ->take(5)
        ->get();

    $recentActivity = ActivityLog::with('user')
        ->orderBy('created_at', 'desc')
        ->take(6)
        ->get();

    // Son Yedekleme Bilgisi
    $companyId = auth()->user()->company_id;
    $baseDir = storage_path("app/YEDEKLEMELER");
    $dirs = glob($baseDir . "/firma_{$companyId}_*");
    $backupDir = !empty($dirs) ? $dirs[0] : null;

    $lastBackupDate = null;
    $lastBackupSize = null;

    if ($backupDir && \Illuminate\Support\Facades\File::exists($backupDir)) {
        $files = \Illuminate\Support\Facades\File::files($backupDir);
        $latestFile = null;
        $latestTime = 0;
        foreach ($files as $file) {
            if ($file->getExtension() === 'zip' && $file->getMTime() > $latestTime) {
                $latestTime = $file->getMTime();
                $latestFile = $file;
            }
        }
        if ($latestFile) {
            $lastBackupDate = \Carbon\Carbon::createFromTimestamp($latestFile->getMTime())->format('d.m.Y H:i');
            $lastBackupSize = round($latestFile->getSize() / 1024, 2) . ' KB';
        }
    }

    $fuelAnomalies = collect();
    $vehiclesWithBounds = Vehicle::whereNotNull('min_km_per_liter')->orWhereNotNull('max_km_per_liter')->get();
    foreach ($vehiclesWithBounds as $v) {
        $recentFuels = Fuel::where('vehicle_id', $v->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->take(2)
            ->get();
            
        if ($recentFuels->count() === 2) {
            $f = $recentFuels[0];
            $prevFuel = $recentFuels[1];
            
            if (!is_null($f->km) && !is_null($prevFuel->km) && $f->km > $prevFuel->km && $f->liters > 0) {
                $kmDiff = $f->km - $prevFuel->km;
                $kpl = round($kmDiff / $f->liters, 2);
                
                $isAnomaly = false;
                $type = '';
                if ($v->min_km_per_liter && $kpl < $v->min_km_per_liter) {
                    $isAnomaly = true;
                    $type = 'low';
                } elseif ($v->max_km_per_liter && $kpl > $v->max_km_per_liter) {
                    $isAnomaly = true;
                    $type = 'high';
                }
                
                if ($isAnomaly) {
                    $f->kpl = $kpl;
                    $f->anomaly_type = $type;
                    $f->vehicle = $v;
                    $fuelAnomalies->push($f);
                }
            }
        }
    }
    $fuelAnomalies = $fuelAnomalies->sortByDesc('date')->take(10)->values();

    return view('dashboard', compact(
        'vehicleCount',
        'driverCount',
        'customerCount',
        'todayTrips',
        'monthlyIncome',
        'monthlyFuel',
        'monthlyPenalty',
        'activeMaintenances',
        'waitingMaintenances',
        'expiredDocumentsCount',
        'documentsExpiringIn7DaysCount',
        'documentsExpiringIn30DaysCount',
        'upcomingDocuments',
        'totalFuel',
        'totalSalary',
        'netProfit',
        'recentTrips',
        'recentActivity',
        'lastBackupDate',
        'lastBackupSize',
        'fuelAnomalies'
    ));

})->middleware(['auth', 'verified', 'permission:dashboard.view'])->name('dashboard');

Route::get('/vehicle-tracking', [\App\Http\Controllers\LiveTrackingController::class, 'index'])
    ->middleware(['auth', 'permission:vehicle_tracking.view'])
    ->name('vehicle-tracking.index');

Route::get('/vehicle-tracking/live', [\App\Http\Controllers\LiveTrackingController::class, 'liveData'])
    ->middleware(['auth', 'permission:vehicle_tracking.view'])
    ->name('vehicle-tracking.live');

Route::post('/vehicle-tracking/assign-imei', [\App\Http\Controllers\LiveTrackingController::class, 'assignImei'])
    ->middleware(['auth', 'permission:vehicles.edit'])
    ->name('vehicle-tracking.assign-imei');

// --- TANIMLAMALAR PANELİ ---
Route::get('/vehicle-tracking/definitions', [\App\Http\Controllers\LiveTrackingController::class, 'definitions'])
    ->middleware(['auth', 'permission:vehicle_tracking.view'])
    ->name('vehicle-tracking.definitions');

Route::post('/vehicle-tracking/assign-imei', [\App\Http\Controllers\LiveTrackingController::class, 'assignImei'])
    ->middleware(['auth', 'permission:vehicles.edit'])
    ->name('vehicle-tracking.assign-imei');

// Alarms
Route::post('/vehicle-tracking/definitions/alarms', [\App\Http\Controllers\LiveTrackingController::class, 'storeAlarm'])
    ->middleware(['auth', 'permission:vehicle_tracking.view'])
    ->name('vehicle-tracking.alarms.store');
Route::patch('/vehicle-tracking/definitions/alarms/{alarm}/toggle', [\App\Http\Controllers\LiveTrackingController::class, 'toggleAlarm'])
    ->middleware(['auth', 'permission:vehicle_tracking.view'])
    ->name('vehicle-tracking.alarms.toggle');
Route::delete('/vehicle-tracking/definitions/alarms/{alarm}', [\App\Http\Controllers\LiveTrackingController::class, 'destroyAlarm'])
    ->middleware(['auth', 'permission:vehicle_tracking.view'])
    ->name('vehicle-tracking.alarms.destroy');

// Geofences
Route::post('/vehicle-tracking/definitions/geofences', [\App\Http\Controllers\LiveTrackingController::class, 'storeGeofence'])
    ->middleware(['auth', 'permission:vehicle_tracking.view'])
    ->name('vehicle-tracking.geofences.store');
Route::delete('/vehicle-tracking/definitions/geofences/{geofence}', [\App\Http\Controllers\LiveTrackingController::class, 'destroyGeofence'])
    ->middleware(['auth', 'permission:vehicle_tracking.view'])
    ->name('vehicle-tracking.geofences.destroy');

// Work Schedules
Route::post('/vehicle-tracking/definitions/schedules', [\App\Http\Controllers\LiveTrackingController::class, 'storeSchedule'])
    ->middleware(['auth', 'permission:vehicle_tracking.view'])
    ->name('vehicle-tracking.schedules.store');
Route::delete('/vehicle-tracking/definitions/schedules/{schedule}', [\App\Http\Controllers\LiveTrackingController::class, 'destroySchedule'])
    ->middleware(['auth', 'permission:vehicle_tracking.view'])
    ->name('vehicle-tracking.schedules.destroy');



Route::middleware('auth')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | CHAT (MESSAGING)
    |--------------------------------------------------------------------------
    */
    Route::get('/chat', function () { return view('chat.index'); })->name('chat.index');

    /*
    |--------------------------------------------------------------------------
    | CHAT (MESSAGING) API FOR WEB
    |--------------------------------------------------------------------------
    */
    Route::prefix('chat-api')->group(function () {
        Route::get('/users', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'users']);
        Route::get('/conversations', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'conversations']);
        Route::post('/conversations', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'storeConversation']);
        Route::delete('/conversations/{conversation}', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'deleteConversation']);
        Route::post('/conversations/bulk-delete', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'bulkDeleteConversations']);
        Route::get('/conversations/{conversation}/messages', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'messages']);
        Route::post('/conversations/{conversation}/messages', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'sendMessage']);
        Route::delete('/conversations/{conversation}/messages/{message}', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'deleteMessage']);
        Route::post('/profile-photo', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'uploadProfilePhoto']);
    });
    Route::get('/chat/unread-count', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'unreadCount'])->name('chat.unread');


    /*
    |--------------------------------------------------------------------------
    | CHAT (MESSAGING)
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER PORTAL
    |--------------------------------------------------------------------------
    */
    Route::get('/customer-portal', [CustomerPortalController::class, 'dashboard'])
        ->name('customer.portal.dashboard');

    Route::get('/customer-portal/trips', [CustomerPortalController::class, 'trips'])
        ->name('customer.portal.trips');

    Route::get('/customer-portal/service-routes/{route}/stops', [\App\Http\Controllers\CustomerPortalController::class, 'stops'])->name('customer.portal.dashboard.stops');
    Route::get('/customer-portal/service-routes/{route}/stops/export', [\App\Http\Controllers\CustomerPortalController::class, 'exportStops'])->name('customer.portal.dashboard.stops.export');
    
    // Portal Documents
    Route::get('/customer-portal/company-documents', [\App\Http\Controllers\CustomerPortalController::class, 'companyDocuments'])->name('customer.portal.company-documents');
    Route::get('/customer-portal/service-routes/{route}/documents', [\App\Http\Controllers\CustomerPortalController::class, 'documents'])->name('customer.portal.documents');

    /*
    |--------------------------------------------------------------------------
    | VEHICLE EXTRA
    |--------------------------------------------------------------------------
    */
    Route::get('/vehicles/export/excel', [VehicleController::class, 'exportExcel'])
        ->middleware('permission:vehicles.view')
        ->name('vehicles.export.excel');

    Route::get('/vehicles/import/template', [VehicleController::class, 'downloadTemplate'])
        ->middleware('permission:vehicles.create')
        ->name('vehicles.import.template');

    Route::post('/vehicles/import', [VehicleController::class, 'import'])
        ->middleware('permission:vehicles.create')
        ->name('vehicles.import');

    Route::post('/vehicles/{vehicle}/images', [VehicleController::class, 'uploadImage'])
        ->middleware('permission:vehicles.view')
        ->name('vehicles.images.store');

    Route::patch('/vehicles/{vehicle}/images/{image}/featured', [VehicleController::class, 'setFeaturedImage'])
        ->middleware('permission:vehicles.view')
        ->name('vehicles.images.featured');

    Route::delete('/vehicles/{vehicle}/images/{image}', [VehicleController::class, 'deleteImage'])
        ->middleware('permission:vehicles.view')
        ->name('vehicles.images.destroy');

    Route::post('/vehicles/{vehicle}/documents', [VehicleController::class, 'uploadDocument'])
        ->middleware('permission:vehicles.view')
        ->name('vehicles.documents.store');

    Route::delete('/vehicles/{vehicle}/documents/{document}', [VehicleController::class, 'deleteDocument'])
        ->middleware('permission:vehicles.view')
        ->name('vehicles.documents.destroy');

    Route::get('/vehicles/{vehicle}/documents/download-zip', [VehicleController::class, 'downloadDocumentsZip'])
        ->middleware('permission:vehicles.view')
        ->name('vehicles.documents.zip');

    /*
    |--------------------------------------------------------------------------
    | DRIVER EXTRA
    |--------------------------------------------------------------------------
    */
    Route::get('/drivers/import/template', [DriverController::class, 'downloadTemplate'])
        ->middleware('permission:drivers.create')
        ->name('drivers.import.template');

    Route::post('/drivers/import', [DriverController::class, 'importExcel'])
        ->middleware('permission:drivers.create')
        ->name('drivers.import');

    Route::post('/drivers/{driver}/documents', [DriverController::class, 'uploadDocument'])
        ->middleware('permission:drivers.view')
        ->name('drivers.documents.store');

    Route::post('/drivers/{driver}/crop-photo', [DriverController::class, 'cropPhoto'])
        ->middleware('permission:drivers.view')
        ->name('drivers.crop-photo');

    Route::delete('/drivers/{driver}/documents/{document}', [DriverController::class, 'deleteDocument'])
        ->middleware('permission:drivers.view')
        ->name('drivers.documents.destroy');

    Route::get('/drivers/{driver}/documents/download-zip', [DriverController::class, 'downloadDocumentsZip'])
        ->middleware('permission:drivers.view')
        ->name('drivers.documents.zip');

    /*
    |--------------------------------------------------------------------------
    | MAINTENANCES
    |--------------------------------------------------------------------------
    */
    Route::get('/maintenances/export/excel', [MaintenanceController::class, 'exportExcel'])
        ->middleware('permission:vehicles.view')
        ->name('maintenances.export.excel');

    Route::get('/maintenances/export/pdf', [MaintenanceController::class, 'exportPdf'])
        ->middleware('permission:vehicles.view')
        ->name('maintenances.export.pdf');

    Route::get('/maintenances/settings', [MaintenanceController::class, 'settings'])
        ->middleware('permission:vehicles.view')
        ->name('maintenances.settings');

    Route::post('/maintenances/settings', [MaintenanceController::class, 'saveSettings'])
        ->middleware('permission:vehicles.view')
        ->name('maintenances.settings.store');

    Route::post('/maintenances/settings/mechanics', [MaintenanceController::class, 'storeMechanic'])
        ->middleware('permission:vehicles.view')
        ->name('maintenances.mechanics.store');

    Route::put('/maintenances/settings/mechanics/{mechanic}', [MaintenanceController::class, 'updateMechanic'])
        ->middleware('permission:vehicles.view')
        ->name('maintenances.mechanics.update');

    Route::patch('/maintenances/settings/mechanics/{mechanic}/toggle', [MaintenanceController::class, 'toggleMechanic'])
        ->middleware('permission:vehicles.view')
        ->name('maintenances.mechanics.toggle');

    Route::delete('/maintenances/settings/mechanics/{mechanic}', [MaintenanceController::class, 'destroyMechanic'])
        ->middleware('permission:vehicles.view')
        ->name('maintenances.mechanics.destroy');

    Route::get('/maintenances/import/template', [MaintenanceController::class, 'downloadImportTemplate'])
        ->middleware('permission:vehicles.view')
        ->name('maintenances.import.template');

    Route::post('/maintenances/import', [MaintenanceController::class, 'importMaintenances'])
        ->middleware('permission:vehicles.view')
        ->name('maintenances.import');

    Route::post('/maintenances/bulk-delete', [MaintenanceController::class, 'bulkDelete'])
        ->middleware('permission:vehicles.view')
        ->name('maintenances.bulk-delete');

    Route::resource('maintenances', MaintenanceController::class)
        ->parameters(['maintenances' => 'maintenance'])
        ->middleware('permission:vehicles.view');

    /*
    |--------------------------------------------------------------------------
    | FUEL STATIONS
    |--------------------------------------------------------------------------
    */
    Route::get('/fuel-stations', [FuelStationController::class, 'index'])
        ->middleware('permission:fuels.view')
        ->name('fuel-stations.index');

    Route::post('/fuel-stations', [FuelStationController::class, 'store'])
        ->middleware('permission:fuels.view')
        ->name('fuel-stations.store');

    /*
    |--------------------------------------------------------------------------
    | PAYMENTS
    |--------------------------------------------------------------------------
    */
    Route::post('/fuel-stations/payments', [FuelStationController::class, 'storePayment'])
        ->middleware('permission:fuels.view')
        ->name('fuel-stations.payments.store');

    Route::get('/fuel-stations/calculate-debt', [FuelStationController::class, 'calculateDebt'])
        ->middleware('permission:fuels.view')
        ->name('fuel-stations.calculate-debt');

    Route::get('/fuel-stations/payments/{payment}', [FuelStationController::class, 'showPayment'])
        ->middleware('permission:fuels.view')
        ->name('fuel-stations.payments.show');

    Route::put('/fuel-stations/payments/{payment}', [FuelStationController::class, 'updatePayment'])
        ->middleware('permission:fuels.view')
        ->name('fuel-stations.payments.update');

    Route::delete('/fuel-stations/payments/{payment}', [FuelStationController::class, 'destroyPayment'])
        ->middleware('permission:fuels.view')
        ->name('fuel-stations.payments.destroy');

    /*
    |--------------------------------------------------------------------------
    | EXTRA
    |--------------------------------------------------------------------------
    */

    Route::post('/fuel-stations/payments/bulk', [FuelStationController::class, 'storeBulkPayment'])
        ->middleware('permission:fuels.view')
        ->name('fuel-stations.payments.bulk');

    Route::get('/fuel-stations/{station}/statement', [FuelStationController::class, 'statement'])
        ->middleware('permission:fuels.view')
        ->name('fuel-stations.statement');

    Route::get('/activity-logs', [ActivityLogController::class, 'index'])
        ->middleware('permission:reports.view')
        ->name('activity-logs.index');

    /*
    |--------------------------------------------------------------------------
    | BACKUPS
    |--------------------------------------------------------------------------
    */
    Route::get('/backups', [\App\Http\Controllers\BackupController::class, 'index'])
        ->name('backups.index');
    Route::get('/backups/download/{file}', [\App\Http\Controllers\BackupController::class, 'download'])
        ->name('backups.download');
    Route::post('/backups/restore', [\App\Http\Controllers\BackupController::class, 'restore'])
        ->name('backups.restore');
    Route::post('/backups/run-now', [\App\Http\Controllers\BackupController::class, 'runNow'])
        ->name('backups.run-now');

    /*
    |--------------------------------------------------------------------------
    | TRAFFIC PENALTIES
    |--------------------------------------------------------------------------
    */
    Route::get('/traffic-penalties/export/excel', [TrafficPenaltyController::class, 'exportExcel'])
        ->middleware('permission:vehicles.view')
        ->name('traffic-penalties.export.excel');

    Route::get('/traffic-penalties/export/pdf', [TrafficPenaltyController::class, 'exportPdf'])
        ->middleware('permission:vehicles.view')
        ->name('traffic-penalties.export.pdf');

    Route::post('/traffic-penalties/{trafficPenalty}/quick-pay', [TrafficPenaltyController::class, 'quickPay'])
        ->middleware('permission:vehicles.view')
        ->name('traffic-penalties.quick-pay');

    Route::resource('traffic-penalties', TrafficPenaltyController::class)
        ->except(['show'])
        ->middleware('permission:vehicles.view');

    /*
    |--------------------------------------------------------------------------
    | RESOURCES
    |--------------------------------------------------------------------------
    */
    Route::post('/vehicles/ai-assistant', [VehicleController::class, 'aiAssistant'])
        ->middleware('permission:vehicles.view')
        ->name('vehicles.ai.assistant');

    Route::post('/vehicles/bulk-delete', [VehicleController::class, 'bulkDelete'])
        ->middleware('permission:vehicles.delete')
        ->name('vehicles.bulk-delete');

    Route::resource('vehicles', VehicleController::class)
        ->middleware('permission:vehicles.view');

    Route::resource('drivers', DriverController::class)
        ->middleware('permission:drivers.view');
    Route::post('/drivers/{driver}/approve', [\App\Http\Controllers\DriverController::class, 'approve'])
        ->middleware(['auth', 'permission:drivers.edit'])
        ->name('drivers.approve');

    Route::post('/drivers/toggle-invite-link', [\App\Http\Controllers\DriverController::class, 'toggleInviteLink'])
        ->name('drivers.toggle-invite-link');

    Route::post('/drivers/{driver}/reject', [\App\Http\Controllers\DriverController::class, 'reject'])
        ->middleware(['auth', 'permission:drivers.delete'])
        ->name('drivers.reject');

    Route::post('drivers/{driver}/leave-work', [\App\Http\Controllers\DriverController::class, 'leaveWork'])->name('drivers.leave-work');
    Route::post('drivers/{driver}/change-vehicle', [DriverController::class, 'changeVehicle'])->name('drivers.change-vehicle');

    Route::get('/customers/{customer}/company-documents', [\App\Http\Controllers\CustomerCompanyDocumentController::class, 'index'])
        ->middleware('permission:customers.view')
        ->name('customers.company-documents.index');
    Route::post('/customers/{customer}/company-documents', [\App\Http\Controllers\CustomerCompanyDocumentController::class, 'store'])
        ->middleware('permission:customers.view')
        ->name('customers.company-documents.store');
    Route::post('/customers/{customer}/company-documents/import', [\App\Http\Controllers\CustomerCompanyDocumentController::class, 'importFromCompany'])
        ->middleware('permission:customers.view')
        ->name('customers.company-documents.import');
    Route::delete('/customers/{customer}/company-documents/{document}', [\App\Http\Controllers\CustomerCompanyDocumentController::class, 'destroy'])
        ->middleware('permission:customers.view')
        ->name('customers.company-documents.destroy');

    // Güzergah Araç/Şoför Evrakları
    Route::get('/customers/{customer}/service-routes/{route}/documents', [\App\Http\Controllers\CustomerRouteDocumentController::class, 'index'])
        ->middleware('permission:customers.view')
        ->name('customers.service-routes.documents.index');
    Route::post('/customers/{customer}/service-routes/{route}/documents', [\App\Http\Controllers\CustomerRouteDocumentController::class, 'store'])
        ->middleware('permission:customers.view')
        ->name('customers.service-routes.documents.store');
    Route::post('/customers/{customer}/service-routes/{route}/documents/import', [\App\Http\Controllers\CustomerRouteDocumentController::class, 'importFromSource'])
        ->middleware('permission:customers.view')
        ->name('customers.service-routes.documents.import');
    Route::delete('/customers/{customer}/service-routes/{route}/documents/{document}', [\App\Http\Controllers\CustomerRouteDocumentController::class, 'destroy'])
        ->middleware('permission:customers.view')
        ->name('customers.service-routes.documents.destroy');

    // Ortak Girişimler
    Route::post('/customers/{customer}/joint-ventures', [\App\Http\Controllers\CustomerJointVentureController::class, 'store'])
        ->middleware('permission:customers.edit')
        ->name('customers.joint-ventures.store');
    Route::put('/customers/{customer}/joint-ventures/{jointVenture}', [\App\Http\Controllers\CustomerJointVentureController::class, 'update'])
        ->middleware('permission:customers.edit')
        ->name('customers.joint-ventures.update');
    Route::delete('/customers/{customer}/joint-ventures/{jointVenture}', [\App\Http\Controllers\CustomerJointVentureController::class, 'destroy'])
        ->middleware('permission:customers.delete')
        ->name('customers.joint-ventures.destroy');

    Route::resource('customers', CustomerController::class)
        ->middleware('permission:customers.view');

    Route::post('/customers/{customer}/contracts', [CustomerContractController::class, 'store'])
        ->middleware('permission:customers.view')
        ->name('customers.contracts.store');

    Route::delete('/customers/{customer}/contracts/{contract}', [CustomerContractController::class, 'destroy'])
        ->middleware('permission:customers.view')
        ->name('customers.contracts.destroy');

    Route::post('/customers/{customer}/portal-users', [CustomerPortalUserController::class, 'store'])
        ->middleware('permission:customers.view')
        ->name('customers.portal-users.store');

    Route::put('/customers/{customer}/portal-users/{portalUser}', [CustomerPortalUserController::class, 'update'])
        ->middleware('permission:customers.view')
        ->name('customers.portal-users.update');

    Route::patch('/customers/{customer}/portal-users/{portalUser}/toggle-status', [CustomerPortalUserController::class, 'toggleStatus'])
        ->middleware('permission:customers.view')
        ->name('customers.portal-users.toggle-status');

    Route::delete('/customers/{customer}/portal-users/{portalUser}', [CustomerPortalUserController::class, 'destroy'])
        ->middleware('permission:customers.view')
        ->name('customers.portal-users.destroy');

    Route::post('/customers/{customer}/service-routes', [CustomerServiceRouteController::class, 'store'])
        ->middleware('permission:customers.view')
        ->name('customers.service-routes.store');

    Route::put('/customers/{customer}/service-routes/{serviceRoute}', [CustomerServiceRouteController::class, 'update'])
        ->middleware('permission:customers.view')
        ->name('customers.service-routes.update');

    Route::patch('/customers/{customer}/service-routes/{serviceRoute}/toggle-status', [CustomerServiceRouteController::class, 'toggleStatus'])
        ->middleware('permission:customers.view')
        ->name('customers.service-routes.toggle-status');

    Route::delete('/customers/{customer}/service-routes/{serviceRoute}', [CustomerServiceRouteController::class, 'destroy'])
        ->middleware('permission:customers.view')
        ->name('customers.service-routes.destroy');

    Route::get('/customers/{customer}/service-routes/{route}/stops', [\App\Http\Controllers\CustomerServiceRouteStopController::class, 'index'])
        ->middleware('permission:customers.view')
        ->name('customers.service-routes.stops.index');
    Route::post('/customers/{customer}/service-routes/{route}/stops', [\App\Http\Controllers\CustomerServiceRouteStopController::class, 'store'])
        ->middleware('permission:customers.view')
        ->name('customers.service-routes.stops.store');
    Route::post('/customers/{customer}/service-routes/{route}/stops/import', [\App\Http\Controllers\CustomerServiceRouteStopController::class, 'import'])
        ->middleware('permission:customers.view')
        ->name('customers.service-routes.stops.import');
    Route::get('/customers/{customer}/service-routes/{route}/stops/export', [\App\Http\Controllers\CustomerServiceRouteStopController::class, 'export'])
        ->middleware('permission:customers.view')
        ->name('customers.service-routes.stops.export');

    Route::resource('service-routes', ServiceRouteController::class)
        ->middleware('permission:service_routes.view');

    Route::resource('route-stops', RouteStopController::class)
        ->middleware('permission:route_stops.view');

    /*
    |--------------------------------------------------------------------------
    | TRIPS
    |--------------------------------------------------------------------------
    */
    Route::post('/trips/upsert-cell', [TripController::class, 'upsertCell'])
        ->middleware('permission:trips.view')
        ->name('trips.upsert-cell');

    Route::resource('trips', TripController::class)
        ->middleware('permission:trips.view');

    Route::get('/payrolls/bulk-report', [PayrollController::class, 'bulkReport'])->name('payrolls.bulk-report');
    Route::post('/payrolls/toggle-lock', [PayrollController::class, 'toggleLock'])->name('payrolls.toggle-lock');
    Route::get('/payrolls/print/{driver}/{period}', [PayrollController::class, 'printSingle'])->name('payrolls.print');
    Route::get('/payrolls/report/{driver}/{period}', [PayrollController::class, 'showReport'])->name('payrolls.report');
    Route::post('/payrolls/bulk-store', [PayrollController::class, 'bulkStore'])->name('payrolls.bulk-store');
    Route::post('/payrolls/update-single', [PayrollController::class, 'updateSingle'])->name('payrolls.update-single');
    Route::get('/payrolls/settings', [PayrollController::class, 'settings'])->name('payrolls.settings');
    Route::post('/payrolls/settings/toggle-fixed-salary', [PayrollController::class, 'toggleFixedSalary'])->name('payrolls.settings.toggle-fixed-salary');
    Route::post('/payrolls/settings/upload-logo', [PayrollController::class, 'uploadLogo'])->name('payrolls.settings.upload-logo');
    Route::delete('/payrolls/settings/remove-logo', [PayrollController::class, 'removeLogo'])->name('payrolls.settings.remove-logo');

    Route::get('/vehicles/admin/fast-photo-upload', [VehicleController::class, 'adminFastPhotoUpload'])->name('vehicles.admin.fast-photo-upload');
    Route::get('/vehicles/export/excel', [VehicleController::class, 'exportExcel'])->name('vehicles.export.excel');
    Route::post('/vehicles/ai-assistant', [VehicleController::class, 'aiAssistant'])->name('vehicles.ai.assistant');

    // Destek Masası (Tenant)
    Route::get('/support-tickets', [\App\Http\Controllers\SupportTicketController::class, 'index'])->name('support-tickets.index');
    Route::get('/support-tickets/create', [\App\Http\Controllers\SupportTicketController::class, 'create'])->name('support-tickets.create');
    Route::post('/support-tickets', [\App\Http\Controllers\SupportTicketController::class, 'store'])->name('support-tickets.store');
    Route::get('/support-tickets/{supportTicket}', [\App\Http\Controllers\SupportTicketController::class, 'show'])->name('support-tickets.show');
    Route::post('/support-tickets/{supportTicket}/reply', [\App\Http\Controllers\SupportTicketController::class, 'reply'])->name('support-tickets.reply');

    Route::resource('payrolls', PayrollController::class)
        ->middleware('permission:payrolls.view');

    Route::resource('expenses', ExpenseController::class)
        ->middleware('permission:expenses.view');

    Route::resource('documents', DocumentController::class)
        ->except(['show'])
        ->middleware('permission:documents.view');

    Route::resource('fuels', FuelController::class)
        ->middleware('permission:fuels.view');

    Route::post('/fuels/import', [FuelController::class, 'import'])
        ->middleware('permission:fuels.create')
        ->name('fuels.import');

    Route::resource('company-users', CompanyUserController::class)
        ->parameters(['company-users' => 'companyUser'])
        ->middleware('permission:company_users.view');

    /*
    |--------------------------------------------------------------------------
    | COMPANY DOCUMENTS
    |--------------------------------------------------------------------------
    */
    Route::post('/company-documents/bulk-delete', [\App\Http\Controllers\CompanyDocumentController::class, 'bulkDelete'])
        ->middleware('permission:company_documents.delete')
        ->name('company-documents.bulk-delete');

    Route::get('/company-documents/download-zip', [\App\Http\Controllers\CompanyDocumentController::class, 'downloadZip'])
        ->middleware('permission:company_documents.view')
        ->name('company-documents.zip');

    Route::resource('company-documents', \App\Http\Controllers\CompanyDocumentController::class)
        ->middleware('permission:company_documents.view');

    /*
    |--------------------------------------------------------------------------
    | TENDERS (İHALELER)
    |--------------------------------------------------------------------------
    */
    Route::resource('tenders', \App\Http\Controllers\TenderController::class);
    Route::post('tenders/{tender}/records', [\App\Http\Controllers\TenderRecordController::class, 'store'])->name('tenders.records.store');
    Route::put('tenders/{tender}/records/{record}', [\App\Http\Controllers\TenderRecordController::class, 'update'])->name('tenders.records.update');
    Route::delete('tenders/{tender}/records/{record}', [\App\Http\Controllers\TenderRecordController::class, 'destroy'])->name('tenders.records.destroy');

    /*
    |--------------------------------------------------------------------------
    | REPORTS
    |--------------------------------------------------------------------------
    */
    Route::get('/reports', [ReportController::class, 'index'])
        ->middleware('permission:reports.view')
        ->name('reports.index');

    Route::get('/reports/trips-csv', [ReportController::class, 'exportTripsCsv'])
        ->middleware('permission:reports.view')
        ->name('reports.trips.csv');

    Route::get('/reports/payrolls-csv', [ReportController::class, 'exportPayrollsCsv'])
        ->middleware('permission:reports.view')
        ->name('reports.payrolls.csv');

    Route::get('/reports/fuels-csv', [ReportController::class, 'exportFuelsCsv'])
        ->middleware('permission:reports.view')
        ->name('reports.fuels.csv');

    Route::get('/reports/documents-csv', [ReportController::class, 'exportDocumentsCsv'])
        ->middleware('permission:reports.view')
        ->name('reports.documents.csv');

    /*
    |--------------------------------------------------------------------------
    | SETTINGS
    |--------------------------------------------------------------------------
    */
    Route::get('/company-settings', [CompanySettingsController::class, 'edit'])
        ->name('company-settings.edit');

    Route::put('/company-settings', [CompanySettingsController::class, 'update'])
        ->name('company-settings.update');

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');
    Route::resource('payrolls', PayrollController::class)
        ->middleware('permission:payrolls.view');

    Route::resource('documents', DocumentController::class)
        ->except(['show'])
        ->middleware('permission:documents.view');

    Route::resource('fuels', FuelController::class)
        ->middleware('permission:fuels.view');

    Route::get('/fuels/import/template', [FuelController::class, 'downloadTemplate'])
        ->middleware('permission:fuels.create')
        ->name('fuels.import.template');

    Route::post('/fuels/import', [FuelController::class, 'import'])
        ->middleware('permission:fuels.create')
        ->name('fuels.import');

    Route::get('/fuels-settings/consumption', [FuelController::class, 'consumptionSettings'])
        ->middleware('permission:fuels.edit')
        ->name('fuels.consumption-settings');
        
    Route::post('/fuels-settings/consumption', [FuelController::class, 'saveConsumptionSettings'])
        ->middleware('permission:fuels.edit')
        ->name('fuels.consumption-settings.save');

    Route::resource('company-users', CompanyUserController::class)
        ->parameters(['company-users' => 'companyUser'])
        ->middleware('permission:company_users.view');

    /*
    |--------------------------------------------------------------------------
    | REPORTS
    |--------------------------------------------------------------------------
    */
    Route::get('/reports', [ReportController::class, 'index'])
        ->middleware('permission:reports.view')
        ->name('reports.index');

    Route::get('/reports/trips-csv', [ReportController::class, 'exportTripsCsv'])
        ->middleware('permission:reports.view')
        ->name('reports.trips.csv');

    Route::get('/reports/payrolls-csv', [ReportController::class, 'exportPayrollsCsv'])
        ->middleware('permission:reports.view')
        ->name('reports.payrolls.csv');

    Route::get('/reports/fuels-csv', [ReportController::class, 'exportFuelsCsv'])
        ->middleware('permission:reports.view')
        ->name('reports.fuels.csv');

    Route::get('/reports/documents-csv', [ReportController::class, 'exportDocumentsCsv'])
        ->middleware('permission:reports.view')
        ->name('reports.documents.csv');

    /*
    |--------------------------------------------------------------------------
    | SETTINGS
    |--------------------------------------------------------------------------
    */
    Route::get('/company-settings', [CompanySettingsController::class, 'edit'])
        ->name('company-settings.edit');

    Route::put('/company-settings', [CompanySettingsController::class, 'update'])
        ->name('company-settings.update');

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');

    Route::get('/support', function () {
        return view('support');
    })->name('support');

    // Abonelik ve Faturalandırma (Tenant)
    Route::get('/billing', [\App\Http\Controllers\BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/plans/{plan}/select', [\App\Http\Controllers\BillingController::class, 'selectPlan'])->name('billing.plans.select');
    Route::get('/billing/invoices/{invoice}', [\App\Http\Controllers\BillingController::class, 'showInvoice'])->name('billing.invoice');
    Route::post('/billing/invoices/{invoice}/upload-receipt', [\App\Http\Controllers\BillingController::class, 'uploadReceipt'])->name('billing.invoice.upload-receipt');

    // PilotCell
    Route::get('/pilotcell', [\App\Http\Controllers\PilotCellController::class, 'dashboard'])
        ->middleware(['auth', 'permission:pilotcell.view'])
        ->name('pilotcell.dashboard');

    Route::get('/pilotcell/school/{id}', [\App\Http\Controllers\PilotCellController::class, 'school'])
        ->middleware(['auth', 'permission:pilotcell.view'])
        ->name('pilotcell.school');

    Route::post('/pilotcell/school/{id}/routes', [\App\Http\Controllers\PilotCellController::class, 'storeRoute'])
        ->middleware(['auth', 'permission:pilotcell.view'])
        ->name('pilotcell.school.routes.store');

    Route::put('/pilotcell/school/{school_id}/routes/{route_id}', [\App\Http\Controllers\PilotCellController::class, 'updateRoute'])
        ->middleware(['auth', 'permission:pilotcell.view'])
        ->name('pilotcell.school.routes.update');

    Route::delete('/pilotcell/school/{school_id}/routes/{route_id}', [\App\Http\Controllers\PilotCellController::class, 'destroyRoute'])
        ->middleware(['auth', 'permission:pilotcell.view'])
        ->name('pilotcell.school.routes.destroy');

    Route::get('/pilotcell/school/{school_id}/routes/{route_id}', [\App\Http\Controllers\PilotCellController::class, 'routeDetails'])
        ->middleware(['auth', 'permission:pilotcell.view'])
        ->name('pilotcell.school.routes.show');

    Route::get('/pilotcell/school/{school_id}/routes/{route_id}/users', [\App\Http\Controllers\PilotCellController::class, 'routeUsers'])
        ->middleware(['auth', 'permission:pilotcell.view'])
        ->name('pilotcell.school.routes.users');

    Route::post('/pilotcell/school/{school_id}/routes/{route_id}/users', [\App\Http\Controllers\PilotCellController::class, 'storeRouteUser'])
        ->middleware(['auth', 'permission:pilotcell.view'])
        ->name('pilotcell.school.routes.users.store');

    Route::put('/pilotcell/school/{school_id}/routes/{route_id}/users/{user_id}', [\App\Http\Controllers\PilotCellController::class, 'updateRouteUser'])
        ->middleware(['auth', 'permission:pilotcell.view'])
        ->name('pilotcell.school.routes.users.update');

    Route::delete('/pilotcell/school/{school_id}/routes/{route_id}/users/{user_id}', [\App\Http\Controllers\PilotCellController::class, 'destroyRouteUser'])
        ->middleware(['auth', 'permission:pilotcell.view'])
        ->name('pilotcell.school.routes.users.destroy');

    Route::post('/pilotcell/school/{school_id}/routes/{route_id}/students', [\App\Http\Controllers\PilotCellController::class, 'storeStudent'])
        ->middleware(['auth', 'permission:pilotcell.view'])
        ->name('pilotcell.school.routes.students.store');

    Route::put('/pilotcell/school/{school_id}/routes/{route_id}/students/{student_id}', [\App\Http\Controllers\PilotCellController::class, 'updateStudent'])
        ->middleware(['auth', 'permission:pilotcell.view'])
        ->name('pilotcell.school.routes.students.update');

    Route::delete('/pilotcell/school/{school_id}/routes/{route_id}/students/{student_id}', [\App\Http\Controllers\PilotCellController::class, 'destroyStudent'])
        ->middleware(['auth', 'permission:pilotcell.view'])
        ->name('pilotcell.school.routes.students.destroy');

    Route::get('/pilotcell/school/{school_id}/routes/{route_id}/students/{student_id}', [\App\Http\Controllers\PilotCellController::class, 'studentDetails'])
        ->middleware(['auth', 'permission:pilotcell.view'])
        ->name('pilotcell.school.routes.students.show');

    Route::post('/pilotcell/school/{school_id}/routes/{route_id}/students/{student_id}/debts', [\App\Http\Controllers\PilotCellController::class, 'storeDebts'])
        ->middleware(['auth', 'permission:pilotcell.view'])
        ->name('pilotcell.school.routes.students.debts.store');

    Route::post('/pilotcell/school/{school_id}/routes/{route_id}/students/{student_id}/debts/{debt_id}/payment', [\App\Http\Controllers\PilotCellController::class, 'updateDebtPayment'])
        ->middleware(['auth', 'permission:pilotcell.view'])
        ->name('pilotcell.school.routes.students.debts.payment');

    Route::delete('/pilotcell/school/{school_id}/routes/{route_id}/students/{student_id}/debts/{debt_id}', [\App\Http\Controllers\PilotCellController::class, 'destroyDebt'])
        ->middleware(['auth', 'permission:pilotcell.view'])
        ->name('pilotcell.school.routes.students.debts.destroy');

    Route::get('/pilotcell/school/{school_id}/routes/{route_id}/students/{student_id}/users', [\App\Http\Controllers\PilotCellController::class, 'studentUsers'])
        ->middleware(['auth', 'permission:pilotcell.view'])
        ->name('pilotcell.school.routes.students.users');

    Route::post('/pilotcell/school/{school_id}/routes/{route_id}/students/{student_id}/users', [\App\Http\Controllers\PilotCellController::class, 'storeStudentUser'])
        ->middleware(['auth', 'permission:pilotcell.view'])
        ->name('pilotcell.school.routes.students.users.store');

    Route::put('/pilotcell/school/{school_id}/routes/{route_id}/students/{student_id}/users/{user_id}', [\App\Http\Controllers\PilotCellController::class, 'updateStudentUser'])
        ->middleware(['auth', 'permission:pilotcell.view'])
        ->name('pilotcell.school.routes.students.users.update');

    Route::delete('/pilotcell/school/{school_id}/routes/{route_id}/students/{student_id}/users/{user_id}', [\App\Http\Controllers\PilotCellController::class, 'destroyStudentUser'])
        ->middleware(['auth', 'permission:pilotcell.view'])
        ->name('pilotcell.school.routes.students.users.destroy');
});

// Driver Invite Public Routes
Route::get('/invite/driver/{token}', [\App\Http\Controllers\DriverInviteController::class, 'show'])->name('invite.driver.show');
Route::post('/invite/driver/{token}', [\App\Http\Controllers\DriverInviteController::class, 'store'])->name('invite.driver.store');

// Temporary migration route for cPanel without terminal
Route::get('/run-migration-now', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        return "Veritabanı başarıyla güncellendi! (Migrations ran successfully). Bu sekmeyi kapatabilirsiniz.";
    } catch (\Exception $e) {
        return "Hata oluştu: " . $e->getMessage();
    }
});

require __DIR__ . '/auth.php';

/*
|--------------------------------------------------------------------------
| LİSANS SÜRESİ DOLDU SAYFASI
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->get('/license-expired', function () {
    return view('license-expired');
})->name('license.expired');

/*
|--------------------------------------------------------------------------
| SUPER ADMIN PANELİ
|--------------------------------------------------------------------------
*/
Route::prefix('super-admin')
    ->middleware(['auth', 'super_admin'])
    ->name('super-admin.')
    ->group(function () {

        Route::get('/dashboard', [SuperAdminDashboardController::class, 'index'])
            ->name('dashboard');

        Route::resource('companies', SuperAdminCompanyController::class);

        Route::post('/companies/{company}/users', [SuperAdminCompanyController::class, 'storeUser'])
            ->name('companies.users.store');

        Route::post('/companies/{company}/impersonate', [SuperAdminCompanyController::class, 'impersonate'])
            ->name('companies.impersonate');

        Route::put('/companies/{company}/modules', [SuperAdminCompanyController::class, 'updateModules'])
            ->name('companies.modules.update');

        Route::put('/users/{user}/password', [SuperAdminCompanyController::class, 'updateUserPassword'])
            ->name('users.password.update');

        // Global Settings
        Route::get('/settings', [\App\Http\Controllers\SuperAdmin\SettingController::class, 'index'])
            ->name('settings.index');
        Route::post('/settings', [\App\Http\Controllers\SuperAdmin\SettingController::class, 'update'])
            ->name('settings.update');

        // Global Logs
        Route::get('/logs', [\App\Http\Controllers\SuperAdmin\ActivityLogController::class, 'index'])
            ->name('logs.index');

        // Super Admin Support Tickets
        Route::get('/support-tickets', [\App\Http\Controllers\SuperAdmin\SupportTicketController::class, 'index'])
            ->name('support-tickets.index');
        Route::get('/support-tickets/{supportTicket}', [\App\Http\Controllers\SuperAdmin\SupportTicketController::class, 'show'])
            ->name('support-tickets.show');
        Route::post('/support-tickets/{supportTicket}/reply', [\App\Http\Controllers\SuperAdmin\SupportTicketController::class, 'reply'])
            ->name('support-tickets.reply');

        // Paket ve Finans Yönetimi (Super Admin)
        Route::resource('plans', \App\Http\Controllers\SuperAdmin\PlanController::class);
        
        Route::get('/finance', [\App\Http\Controllers\SuperAdmin\FinanceController::class, 'index'])
            ->name('finance.index');
        Route::post('/finance/invoices/{invoice}/approve', [\App\Http\Controllers\SuperAdmin\FinanceController::class, 'approve'])
            ->name('finance.approve');
        Route::post('/finance/invoices/{invoice}/reject', [\App\Http\Controllers\SuperAdmin\FinanceController::class, 'reject'])
            ->name('finance.reject');
    });

Route::get('/run-migrations', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        return "Veritabanı güncellemeleri başarıyla tamamlandı!<br><br><pre>" . \Illuminate\Support\Facades\Artisan::output() . "</pre><br><a href='/'>Ana Sayfaya Dön</a>";
    } catch (\Exception $e) {
        return "Hata oluştu: " . $e->getMessage();
    }
});

Route::get('/clear-cache-now', function () {
    \Illuminate\Support\Facades\Artisan::call('optimize:clear');
    return 'Cache cleared!';
});