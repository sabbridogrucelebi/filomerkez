<?php

/**
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  WEB ↔ MOBILE PARITY                                                     ║
 * ║  Every endpoint here is consumed by both web and mobile-app.             ║
 * ║  When you add/modify a route, also update:                               ║
 * ║   - mobile-app/src/api/*.js (axios calls)                                ║
 * ║   - mobile-app/src/screens/*.js (UI consumption + hasPermission gates)   ║
 * ║  See WEB_MOBIL_SENKRON_KURALLARI.md                                      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\PersonnelController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\TripController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckCompanyStatus::class])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'updatePassword']);
    Route::post('/user/push-token', [AuthController::class, 'updatePushToken']);
    
    Route::middleware(['permission:vehicles.view'])->get('/vehicles', [VehicleController::class, 'index']);
    Route::middleware(['permission:vehicles.view'])->get('/vehicles/{vehicle}', [VehicleController::class, 'show']);
    Route::middleware(['permission:vehicles.create'])->post('/vehicles', [VehicleController::class, 'store']);
    Route::middleware(['permission:vehicles.edit'])->put('/vehicles/{vehicle}', [VehicleController::class, 'update']);
    Route::middleware(['permission:vehicles.delete'])->delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy']);
    
    Route::middleware(['permission:documents.view'])->get('/vehicles/{vehicle}/documents', [VehicleController::class, 'documents']);
    Route::middleware(['permission:fuels.view'])->get('/vehicles/{vehicle}/fuels', [VehicleController::class, 'fuels']);
    Route::middleware(['permission:maintenances.view'])->get('/vehicles/{vehicle}/maintenances', [VehicleController::class, 'maintenances']);
    Route::middleware(['permission:maintenances.view'])->get('/vehicles/{vehicle}/maintenances/export-pdf', [VehicleController::class, 'exportMaintenancesPdf']);
    Route::middleware(['permission:penalties.view'])->get('/vehicles/{vehicle}/penalties', [VehicleController::class, 'penalties']);
    Route::middleware(['permission:reports.view'])->get('/vehicles/{vehicle}/reports', [VehicleController::class, 'reports']);
    Route::middleware(['permission:vehicles.view'])->get('/vehicles/{vehicle}/gallery', [VehicleController::class, 'gallery']);
    Route::middleware(['permission:vehicles.edit'])->post('/vehicles/{vehicle}/gallery', [VehicleController::class, 'uploadImage']);
    Route::middleware(['permission:vehicles.edit'])->post('/vehicles/{vehicle}/gallery/{image}/featured', [VehicleController::class, 'setFeaturedImage']);
    Route::middleware(['permission:vehicles.delete'])->delete('/vehicles/{vehicle}/gallery/{image}', [VehicleController::class, 'deleteImage']);
    
    Route::middleware(['permission:drivers.view'])->get('/personnel', [PersonnelController::class, 'index']);
    Route::middleware(['permission:customers.view'])->get('/customers', [CustomerController::class, 'index']);
    Route::middleware(['permission:trips.view'])->get('/trips', [TripController::class, 'index']);
});

/*
|--------------------------------------------------------------------------
| V1 API ROUTES (New Standard)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    // Internal Microservice Endpoint (protected by X-Telemetry-Secret header)
    Route::post('/vehicle-tracking/telemetry', [\App\Http\Controllers\Api\V1\VehicleTelemetryApiController::class, 'store']);
});

Route::prefix('v1')->middleware(['auth:sanctum', \App\Http\Middleware\CheckCompanyStatus::class])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'updatePassword']);
    Route::post('/user/push-token', [AuthController::class, 'updatePushToken']);

    Route::get('/dashboard', [\App\Http\Controllers\Api\V1\DashboardApiController::class, 'index']);
    Route::get('/personnel', [\App\Http\Controllers\Api\V1\PersonnelApiController::class, 'index']);
    Route::get('/personnel/options', [\App\Http\Controllers\Api\V1\PersonnelApiController::class, 'options']);
    Route::post('/personnel', [\App\Http\Controllers\Api\V1\PersonnelApiController::class, 'store']);
    Route::get('/personnel/{id}', [\App\Http\Controllers\Api\V1\PersonnelApiController::class, 'show']);
    Route::put('/personnel/{id}', [\App\Http\Controllers\Api\V1\PersonnelApiController::class, 'update']);
    Route::delete('/personnel/{id}', [\App\Http\Controllers\Api\V1\PersonnelApiController::class, 'destroy']);
    Route::put('/personnel/{id}/status', [\App\Http\Controllers\Api\V1\PersonnelApiController::class, 'updateStatus']);
    Route::put('/personnel/{id}/vehicle', [\App\Http\Controllers\Api\V1\PersonnelApiController::class, 'updateVehicle']);
    Route::post('/personnel/{id}/documents', [\App\Http\Controllers\Api\V1\PersonnelApiController::class, 'uploadDocument']);
    Route::delete('/personnel/{id}/documents/{documentId}', [\App\Http\Controllers\Api\V1\PersonnelApiController::class, 'deleteDocument']);
    Route::get('/customers', [\App\Http\Controllers\Api\V1\CustomerApiController::class, 'index']);
    Route::post('/customers', [\App\Http\Controllers\Api\V1\CustomerApiController::class, 'store']);
    Route::get('/customers/{id}', [\App\Http\Controllers\Api\V1\CustomerApiController::class, 'show']);
    Route::get('/customers/{id}/invoices', [\App\Http\Controllers\Api\V1\CustomerApiController::class, 'invoices']);
    Route::put('/customers/{id}', [\App\Http\Controllers\Api\V1\CustomerApiController::class, 'update']);
    Route::delete('/customers/{id}', [\App\Http\Controllers\Api\V1\CustomerApiController::class, 'destroy']);
    
    // Customer Portal Users
    Route::post('/customers/{id}/portal-users', [\App\Http\Controllers\Api\V1\CustomerPortalUserApiController::class, 'store']);
    Route::put('/customers/{id}/portal-users/{portalUserId}', [\App\Http\Controllers\Api\V1\CustomerPortalUserApiController::class, 'update']);
    Route::patch('/customers/{id}/portal-users/{portalUserId}/toggle-status', [\App\Http\Controllers\Api\V1\CustomerPortalUserApiController::class, 'toggleStatus']);
    Route::delete('/customers/{id}/portal-users/{portalUserId}', [\App\Http\Controllers\Api\V1\CustomerPortalUserApiController::class, 'destroy']);

    // Customer Statement (Cari Ekstresi)
    Route::get('/customers/{id}/statement', [\App\Http\Controllers\Api\V1\CustomerStatementApiController::class, 'statement']);

    Route::get('/trips', [\App\Http\Controllers\Api\V1\TripApiController::class, 'index']);
    Route::get('/trips/export-excel', [\App\Http\Controllers\Api\V1\TripApiController::class, 'exportExcel']);
    Route::get('/trips/export-pdf', [\App\Http\Controllers\Api\V1\TripApiController::class, 'exportPdf']);
    Route::get('/trips/matrix', [\App\Http\Controllers\Api\V1\TripApiController::class, 'matrix']);
    Route::get('/trips/options', [\App\Http\Controllers\Api\V1\TripApiController::class, 'options']);
    Route::post('/trips/upsert-cell', [\App\Http\Controllers\Api\V1\TripApiController::class, 'upsertCell']);
    Route::post('/trips', [\App\Http\Controllers\Api\V1\TripApiController::class, 'store']);
    Route::get('/trips/{id}', [\App\Http\Controllers\Api\V1\TripApiController::class, 'show']);
    Route::put('/trips/{id}', [\App\Http\Controllers\Api\V1\TripApiController::class, 'update']);
    Route::delete('/trips/{id}', [\App\Http\Controllers\Api\V1\TripApiController::class, 'destroy']);
    Route::get('/fuels', [\App\Http\Controllers\Api\V1\FuelApiController::class, 'index']);
    Route::get('/fuels/options', [\App\Http\Controllers\Api\V1\FuelApiController::class, 'options']);
    Route::post('/fuels', [\App\Http\Controllers\Api\V1\FuelApiController::class, 'store']);
    Route::get('/fuels/{id}', [\App\Http\Controllers\Api\V1\FuelApiController::class, 'show']);
    Route::put('/fuels/{id}', [\App\Http\Controllers\Api\V1\FuelApiController::class, 'update']);
    Route::delete('/fuels/{id}', [\App\Http\Controllers\Api\V1\FuelApiController::class, 'destroy']);
    
    Route::get('/maintenances', [\App\Http\Controllers\Api\V1\MaintenanceApiController::class, 'index']);
    Route::get('/maintenances/export-pdf', [\App\Http\Controllers\Api\V1\MaintenanceApiController::class, 'exportPdf']);
    Route::get('/maintenances/export-excel', [\App\Http\Controllers\Api\V1\MaintenanceApiController::class, 'exportExcel']);
    Route::get('/maintenances/options', [\App\Http\Controllers\Api\V1\MaintenanceApiController::class, 'options']);
    Route::post('/maintenances', [\App\Http\Controllers\Api\V1\MaintenanceApiController::class, 'store']);
    Route::get('/maintenances/{id}', [\App\Http\Controllers\Api\V1\MaintenanceApiController::class, 'show']);
    Route::put('/maintenances/{id}', [\App\Http\Controllers\Api\V1\MaintenanceApiController::class, 'update']);
    Route::delete('/maintenances/{id}', [\App\Http\Controllers\Api\V1\MaintenanceApiController::class, 'destroy']);

    Route::get('/maintenances-settings', [\App\Http\Controllers\Api\V1\MaintenanceApiController::class, 'settings']);
    Route::post('/maintenances-settings', [\App\Http\Controllers\Api\V1\MaintenanceApiController::class, 'saveSettings']);
    
    Route::post('/maintenances-mechanics', [\App\Http\Controllers\Api\V1\MaintenanceApiController::class, 'storeMechanic']);
    Route::put('/maintenances-mechanics/{id}', [\App\Http\Controllers\Api\V1\MaintenanceApiController::class, 'updateMechanic']);
    Route::patch('/maintenances-mechanics/{id}/toggle', [\App\Http\Controllers\Api\V1\MaintenanceApiController::class, 'toggleMechanic']);
    Route::delete('/maintenances-mechanics/{id}', [\App\Http\Controllers\Api\V1\MaintenanceApiController::class, 'destroyMechanic']);
    
    Route::get('/penalties/statistics', [\App\Http\Controllers\Api\V1\PenaltyApiController::class, 'statistics']);
    Route::get('/penalties', [\App\Http\Controllers\Api\V1\PenaltyApiController::class, 'index']);
    Route::get('/penalties/options', [\App\Http\Controllers\Api\V1\PenaltyApiController::class, 'options']);
    Route::post('/penalties', [\App\Http\Controllers\Api\V1\PenaltyApiController::class, 'store']);
    Route::get('/penalties/{id}', [\App\Http\Controllers\Api\V1\PenaltyApiController::class, 'show']);
    Route::post('/penalties/{id}', [\App\Http\Controllers\Api\V1\PenaltyApiController::class, 'update']);
    Route::delete('/penalties/{id}', [\App\Http\Controllers\Api\V1\PenaltyApiController::class, 'destroy']);
    
    Route::get('/documents', [\App\Http\Controllers\Api\V1\DocumentApiController::class, 'index']);
    Route::get('/documents/options', [\App\Http\Controllers\Api\V1\DocumentApiController::class, 'options']);
    Route::post('/documents', [\App\Http\Controllers\Api\V1\DocumentApiController::class, 'store']);
    Route::get('/documents/{id}', [\App\Http\Controllers\Api\V1\DocumentApiController::class, 'show']);
    Route::post('/documents/{id}', [\App\Http\Controllers\Api\V1\DocumentApiController::class, 'update']); // Since we might use FormData for files, POST is safer with _method=PUT
    Route::delete('/documents/{id}', [\App\Http\Controllers\Api\V1\DocumentApiController::class, 'destroy']);
    
    // Company Documents
    Route::get('/company-documents', [\App\Http\Controllers\Api\V1\CompanyDocumentApiController::class, 'index']);
    Route::post('/company-documents', [\App\Http\Controllers\Api\V1\CompanyDocumentApiController::class, 'store']);
    Route::delete('/company-documents/{id}', [\App\Http\Controllers\Api\V1\CompanyDocumentApiController::class, 'destroy']);
    Route::post('/company-documents/bulk-delete', [\App\Http\Controllers\Api\V1\CompanyDocumentApiController::class, 'bulkDelete']);
    Route::get('/company-documents/download-zip', [\App\Http\Controllers\Api\V1\CompanyDocumentApiController::class, 'downloadZip']);

    Route::get('/tenders', [\App\Http\Controllers\Api\V1\TenderApiController::class, 'index']);
    Route::post('/tenders', [\App\Http\Controllers\Api\V1\TenderApiController::class, 'store']);
    Route::get('/tenders/{id}', [\App\Http\Controllers\Api\V1\TenderApiController::class, 'show']);
    Route::put('/tenders/{id}', [\App\Http\Controllers\Api\V1\TenderApiController::class, 'update']);
    Route::delete('/tenders/{id}', [\App\Http\Controllers\Api\V1\TenderApiController::class, 'destroy']);
    
    Route::post('/tenders/{id}/records', [\App\Http\Controllers\Api\V1\TenderApiController::class, 'storeRecord']);
    Route::put('/tenders/{id}/records/{recordId}', [\App\Http\Controllers\Api\V1\TenderApiController::class, 'updateRecord']);
    Route::delete('/tenders/{id}/records/{recordId}', [\App\Http\Controllers\Api\V1\TenderApiController::class, 'destroyRecord']);
    
    Route::get('/contracts', [\App\Http\Controllers\Api\V1\ContractApiController::class, 'index']);
    Route::post('/contracts', [\App\Http\Controllers\Api\V1\ContractApiController::class, 'store']);
    Route::post('/contracts/{id}', [\App\Http\Controllers\Api\V1\ContractApiController::class, 'update']);
    Route::delete('/contracts/{id}', [\App\Http\Controllers\Api\V1\ContractApiController::class, 'destroy']);
    
    Route::get('/service-routes', [\App\Http\Controllers\Api\V1\ServiceRouteApiController::class, 'index']);
    Route::get('/service-routes/options', [\App\Http\Controllers\Api\V1\ServiceRouteApiController::class, 'options']);
    Route::post('/service-routes', [\App\Http\Controllers\Api\V1\ServiceRouteApiController::class, 'store']);
    Route::get('/service-routes/{id}', [\App\Http\Controllers\Api\V1\ServiceRouteApiController::class, 'show']);
    Route::put('/service-routes/{id}', [\App\Http\Controllers\Api\V1\ServiceRouteApiController::class, 'update']);
    Route::delete('/service-routes/{id}', [\App\Http\Controllers\Api\V1\ServiceRouteApiController::class, 'destroy']);
    
    Route::get('/route-stops', [\App\Http\Controllers\Api\V1\RouteStopApiController::class, 'index']);
    Route::post('/route-stops', [\App\Http\Controllers\Api\V1\RouteStopApiController::class, 'store']);
    Route::get('/route-stops/{id}', [\App\Http\Controllers\Api\V1\RouteStopApiController::class, 'show']);
    Route::put('/route-stops/{id}', [\App\Http\Controllers\Api\V1\RouteStopApiController::class, 'update']);
    Route::delete('/route-stops/{id}', [\App\Http\Controllers\Api\V1\RouteStopApiController::class, 'destroy']);
    
    Route::get('/fuel-stations', [\App\Http\Controllers\Api\V1\FuelStationApiController::class, 'index']);
    Route::post('/fuel-stations', [\App\Http\Controllers\Api\V1\FuelStationApiController::class, 'store']);
    Route::get('/fuel-stations/{id}', [\App\Http\Controllers\Api\V1\FuelStationApiController::class, 'show']);
    Route::get('/fuel-stations/{id}/statement', [\App\Http\Controllers\Api\V1\FuelStationApiController::class, 'statement']);
    Route::put('/fuel-stations/{id}', [\App\Http\Controllers\Api\V1\FuelStationApiController::class, 'update']);
    Route::delete('/fuel-stations/{id}', [\App\Http\Controllers\Api\V1\FuelStationApiController::class, 'destroy']);
    Route::get('/activity-logs', [\App\Http\Controllers\Api\V1\ActivityLogApiController::class, 'index']);
    
    Route::get('/finance/summary', [\App\Http\Controllers\Api\V1\FinanceApiController::class, 'summary']);
    Route::get('/reports/summary', [\App\Http\Controllers\Api\V1\ReportApiController::class, 'summary']);
    
    Route::get('/payrolls', [\App\Http\Controllers\Api\V1\PayrollApiController::class, 'index']);
    Route::get('/payrolls/options', [\App\Http\Controllers\Api\V1\PayrollApiController::class, 'options']);
    Route::get('/payrolls/period/{period}', [\App\Http\Controllers\Api\V1\PayrollApiController::class, 'period']);
    Route::post('/payrolls/period/{period}/lock', [\App\Http\Controllers\Api\V1\PayrollApiController::class, 'toggleLock']);
    Route::post('/payrolls/single-update', [\App\Http\Controllers\Api\V1\PayrollApiController::class, 'updateSingle']);
    Route::post('/payrolls', [\App\Http\Controllers\Api\V1\PayrollApiController::class, 'store']);
    Route::get('/payrolls/{id}', [\App\Http\Controllers\Api\V1\PayrollApiController::class, 'show']);
    Route::put('/payrolls/{id}', [\App\Http\Controllers\Api\V1\PayrollApiController::class, 'update']);
    Route::delete('/payrolls/{id}', [\App\Http\Controllers\Api\V1\PayrollApiController::class, 'destroy']);
    
    // Expenses
    Route::get('/expenses', [\App\Http\Controllers\Api\V1\ExpenseApiController::class, 'index']);
    Route::post('/expenses', [\App\Http\Controllers\Api\V1\ExpenseApiController::class, 'store']);
    Route::put('/expenses/{expense}', [\App\Http\Controllers\Api\V1\ExpenseApiController::class, 'update']);
    Route::delete('/expenses/{expense}', [\App\Http\Controllers\Api\V1\ExpenseApiController::class, 'destroy']);

    // Leaves
    Route::get('/leaves', [\App\Http\Controllers\Api\V1\LeaveApiController::class, 'index']);
    Route::post('/leaves', [\App\Http\Controllers\Api\V1\LeaveApiController::class, 'store']);
    Route::put('/leaves/{leave}', [\App\Http\Controllers\Api\V1\LeaveApiController::class, 'update']);
    Route::delete('/leaves/{leave}', [\App\Http\Controllers\Api\V1\LeaveApiController::class, 'destroy']);

    // Holidays
    Route::get('/holidays', [\App\Http\Controllers\Api\V1\HolidayApiController::class, 'index']);
    Route::post('/holidays', [\App\Http\Controllers\Api\V1\HolidayApiController::class, 'store']);
    Route::delete('/holidays/{holiday}', [\App\Http\Controllers\Api\V1\HolidayApiController::class, 'destroy']);

    // Vehicles (Read-Only)
    Route::get('/vehicles', [\App\Http\Controllers\Api\V1\VehicleApiController::class, 'index']);
    Route::get('/vehicles/{id}', [\App\Http\Controllers\Api\V1\VehicleApiController::class, 'show']);
    Route::get('/vehicles/{id}/documents', [\App\Http\Controllers\Api\V1\VehicleApiController::class, 'documents']);
    Route::get('/vehicles/{id}/fuels', [\App\Http\Controllers\Api\V1\VehicleApiController::class, 'fuels']);
    Route::get('/vehicles/{id}/maintenances', [\App\Http\Controllers\Api\V1\VehicleApiController::class, 'maintenances']);
    Route::get('/vehicles/{id}/penalties', [\App\Http\Controllers\Api\V1\VehicleApiController::class, 'penalties']);
    Route::get('/vehicles/{id}/gallery', [\App\Http\Controllers\Api\V1\VehicleApiController::class, 'gallery']);
    Route::post('/vehicles/{id}/gallery', [\App\Http\Controllers\Api\V1\VehicleApiController::class, 'storeGallery']);
    Route::delete('/vehicles/{id}/gallery/{imageId}', [\App\Http\Controllers\Api\V1\VehicleApiController::class, 'deleteGallery']);
    Route::post('/vehicles/{id}/gallery/{imageId}/featured', [\App\Http\Controllers\Api\V1\VehicleApiController::class, 'setFeaturedGallery']);
    
    Route::get('/vehicles/{id}/reports', [\App\Http\Controllers\Api\V1\VehicleApiController::class, 'reports']);

    // Vehicle Tracking
    Route::get('/vehicle-tracking/live', [\App\Http\Controllers\Api\V1\VehicleTrackingApiController::class, 'live']);
    Route::get('/vehicle-tracking/reports/daily-work', [\App\Http\Controllers\Api\V1\VehicleTrackingApiController::class, 'dailyWorkReport']);

    // Company Users
    Route::get('/company-users', [\App\Http\Controllers\Api\V1\CompanyUserApiController::class, 'index']);
    Route::get('/company-users/options', [\App\Http\Controllers\Api\V1\CompanyUserApiController::class, 'options']);
    Route::post('/company-users', [\App\Http\Controllers\Api\V1\CompanyUserApiController::class, 'store']);
    Route::get('/company-users/{id}', [\App\Http\Controllers\Api\V1\CompanyUserApiController::class, 'show']);
    Route::put('/company-users/{id}', [\App\Http\Controllers\Api\V1\CompanyUserApiController::class, 'update']);
    Route::delete('/company-users/{id}', [\App\Http\Controllers\Api\V1\CompanyUserApiController::class, 'destroy']);

    // PilotCell - Konum Takip ve Filo
    Route::prefix('pilotcell')->group(function () {
        Route::post('/location/update', [\App\Http\Controllers\Api\V1\PilotCellLocationApiController::class, 'update']);
        Route::get('/location/latest/{tripId}', [\App\Http\Controllers\Api\V1\PilotCellLocationApiController::class, 'latest']);
        Route::get('/location/history/{tripId}', [\App\Http\Controllers\Api\V1\PilotCellLocationApiController::class, 'history']);
        
        Route::get('/trips/active', [\App\Http\Controllers\Api\V1\PilotCellTripApiController::class, 'activeTrips']);
        Route::post('/trips/start', [\App\Http\Controllers\Api\V1\PilotCellTripApiController::class, 'start']);
        Route::post('/trips/{id}/end', [\App\Http\Controllers\Api\V1\PilotCellTripApiController::class, 'end']);
        Route::post('/attendance/update', [\App\Http\Controllers\Api\V1\PilotCellTripApiController::class, 'updateAttendance']);

        // Personnel Portal
        Route::get('/personnel/my-routes', [\App\Http\Controllers\Api\V1\PilotCellPersonnelApiController::class, 'myRoutes']);
        Route::get('/personnel/route-absences', [\App\Http\Controllers\Api\V1\PilotCellPersonnelApiController::class, 'routeAbsences']);
        Route::post('/personnel/set-student-point', [\App\Http\Controllers\Api\V1\PilotCellPersonnelApiController::class, 'setStudentPoint']);
        Route::post('/personnel/set-student-radius', [\App\Http\Controllers\Api\V1\PilotCellPersonnelApiController::class, 'setStudentRadius']);
        Route::post('/personnel/set-bulk-radius', [\App\Http\Controllers\Api\V1\PilotCellPersonnelApiController::class, 'setBulkRadius']);

        // Parent Portal
        Route::get('/parent/student-info', [\App\Http\Controllers\Api\V1\PilotCellParentApiController::class, 'getStudentInfo']);
        Route::put('/parent/student-info', [\App\Http\Controllers\Api\V1\PilotCellParentApiController::class, 'updateStudentInfo']);
        Route::get('/parent/absences', [\App\Http\Controllers\Api\V1\PilotCellParentApiController::class, 'getAbsences']);
        Route::post('/parent/absences/toggle', [\App\Http\Controllers\Api\V1\PilotCellParentApiController::class, 'toggleAbsence']);
        Route::get('/parent/active-trip', [\App\Http\Controllers\Api\V1\PilotCellParentApiController::class, 'getActiveTrip']);
    });
});

/*
|--------------------------------------------------------------------------
| SUPER ADMIN API ROUTES
|--------------------------------------------------------------------------
*/
Route::prefix('v1/super-admin')->middleware(['auth:sanctum', \App\Http\Middleware\CheckSuperAdmin::class])->group(function () {
    // Şirket Yönetimi (Lisans, Modül, Status, Impersonation)
    Route::get('/companies/{id}/license', [\App\Http\Controllers\Api\V1\SuperAdminCompanyApiController::class, 'getLicense']);
    Route::put('/companies/{id}/license', [\App\Http\Controllers\Api\V1\SuperAdminCompanyApiController::class, 'updateLicense']);
    Route::get('/companies/{id}/modules', [\App\Http\Controllers\Api\V1\SuperAdminCompanyApiController::class, 'getModules']);
    Route::put('/companies/{id}/modules', [\App\Http\Controllers\Api\V1\SuperAdminCompanyApiController::class, 'updateModules']);
    Route::put('/companies/{id}/status', [\App\Http\Controllers\Api\V1\SuperAdminCompanyApiController::class, 'updateStatus']);
    Route::post('/companies/{id}/impersonate', [\App\Http\Controllers\Api\V1\SuperAdminCompanyApiController::class, 'impersonate']);
    
    // Global Duyurular
    Route::get('/announcements', [\App\Http\Controllers\Api\V1\SuperAdminAnnouncementApiController::class, 'index']);
    Route::post('/announcements', [\App\Http\Controllers\Api\V1\SuperAdminAnnouncementApiController::class, 'store']);
    Route::get('/announcements/{id}', [\App\Http\Controllers\Api\V1\SuperAdminAnnouncementApiController::class, 'show']);
    Route::put('/announcements/{id}', [\App\Http\Controllers\Api\V1\SuperAdminAnnouncementApiController::class, 'update']);
    Route::delete('/announcements/{id}', [\App\Http\Controllers\Api\V1\SuperAdminAnnouncementApiController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| GLOBAL PUBLIC (TENANT) ROUTES
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/global/announcements/active', [\App\Http\Controllers\Api\V1\SuperAdminAnnouncementApiController::class, 'active']);
});

/*
|--------------------------------------------------------------------------
| CHAT (MESSAGING) API
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/chat/users', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'users']);
    Route::get('/chat/unread', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'unreadCount']);
    Route::get('/chat/conversations', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'conversations']);
    Route::post('/chat/conversations', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'storeConversation']);
    Route::delete('/chat/conversations/{conversation}', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'deleteConversation']);
    Route::post('/chat/conversations/bulk-delete', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'bulkDeleteConversations']);
    Route::get('/chat/conversations/{conversation}/messages', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'messages']);
    Route::post('/chat/conversations/{conversation}/messages', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'sendMessage']);
    Route::delete('/chat/conversations/{conversation}/messages/{message}', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'deleteMessage']);
    Route::post('/chat/conversations/{conversation}/call', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'handleCall']);
    Route::post('/chat/profile-photo', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'uploadProfilePhoto']);
});

// V1 prefixed Chat routes (for mobile app with /api/v1 base URL)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/chat/users', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'users']);
    Route::get('/chat/unread', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'unreadCount']);
    Route::get('/chat/conversations', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'conversations']);
    Route::post('/chat/conversations', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'storeConversation']);
    Route::delete('/chat/conversations/{conversation}', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'deleteConversation']);
    Route::post('/chat/conversations/bulk-delete', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'bulkDeleteConversations']);
    Route::get('/chat/conversations/{conversation}/messages', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'messages']);
    Route::post('/chat/conversations/{conversation}/messages', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'sendMessage']);
    Route::delete('/chat/conversations/{conversation}/messages/{message}', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'deleteMessage']);
    Route::post('/chat/conversations/{conversation}/call', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'handleCall']);
    Route::post('/chat/profile-photo', [\App\Http\Controllers\Api\V1\ChatApiController::class, 'uploadProfilePhoto']);
});
