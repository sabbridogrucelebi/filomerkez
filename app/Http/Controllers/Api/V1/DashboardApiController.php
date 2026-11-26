<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardApiController extends BaseApiController
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function index(Request $request)
    {
        // Yetki kontrolü (sadece dashboard yetkisi olanlar görebilir)
        if (!$this->userHasPermission($request->user(), 'dashboard.view')) {
            return $this->errorResponse('Bu işlem için yetkiniz bulunmamaktadır.', 403);
        }

        $companyId = $this->getCompanyId();
        $statistics = $this->dashboardService->getStatistics($companyId, $request->query('view') === 'home');

        return $this->successResponse($statistics, 'Dashboard istatistikleri başarıyla getirildi.');
    }
}
