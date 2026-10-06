<?php

use App\Http\Controllers\FuelStationController;
use App\Models\Fuel;
use App\Models\FuelStation;
use App\Services\FuelPricingService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Fuel::withoutEvents(function () {
            app(FuelPricingService::class)->repriceAll();

            $controller = app(FuelStationController::class);
            foreach (FuelStation::query()->orderBy('id')->get() as $station) {
                $controller->recalculateStationFuels($station);
            }
        });
    }

    public function down(): void
    {
        // Eski hatalı (KDV'nin pompa fiyatının üzerine eklendiği) tutarlar geri yazılmaz.
    }
};
