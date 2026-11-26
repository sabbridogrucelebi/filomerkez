<?php

namespace Tests\Feature;

use App\Http\Controllers\VehicleController;
use App\Models\User;
use App\Services\VehicleReadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VehiclePlateOrderTest extends TestCase
{
    public function test_web_and_paginated_mobile_lists_order_mixed_format_c_plates_numerically(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::statement('CREATE TABLE vehicles (id INTEGER PRIMARY KEY, company_id INTEGER, plate TEXT, is_active INTEGER, vehicle_type TEXT, inspection_date TEXT, insurance_end_date TEXT, deleted_at TEXT)');
        DB::statement('CREATE TABLE documents (id INTEGER PRIMARY KEY, company_id INTEGER, documentable_type TEXT, documentable_id INTEGER, document_type TEXT, end_date TEXT, archived_at TEXT, created_at TEXT)');
        DB::statement('CREATE TABLE drivers (id INTEGER PRIMARY KEY, company_id INTEGER, vehicle_id INTEGER, full_name TEXT, phone TEXT, is_active INTEGER, created_at TEXT, deleted_at TEXT)');
        foreach (['fuels', 'vehicle_maintenances', 'traffic_penalties', 'vehicle_images', 'vehicle_maintenance_settings'] as $table) {
            DB::statement("CREATE TABLE $table (id INTEGER PRIMARY KEY, company_id INTEGER, vehicle_id INTEGER, km INTEGER, date TEXT, is_featured INTEGER, sort_order INTEGER, maintenance_type TEXT, service_date TEXT, status TEXT)");
        }

        $this->actingAs(new User(['company_id' => 1, 'role' => 'company_admin']));
        foreach (['42 C 0681', '42AH354', '42C0015', '42 C 0461', '42c51', '42C8', '42CA1'] as $plate) {
            DB::table('vehicles')->insert(['company_id' => 1, 'plate' => $plate, 'is_active' => 1]);
        }
        DB::table('vehicles')->insert(['company_id' => 1, 'plate' => '42C2', 'is_active' => 0]);
        $expected = ['42C8', '42C0015', '42c51', '42 C 0461', '42 C 0681', '42AH354', '42CA1'];
        $request = Request::create('/vehicles', 'GET', ['status' => 'active']);
        $web = app(VehicleController::class)->index($request)->getData()['vehicles'];
        $this->assertSame($expected, $web->pluck('plate')->all());

        $mobile = app(VehicleReadService::class);
        $plates = [];
        for ($page = 1; $page <= 3; $page++) {
            $this->app['request']->query->set('page', $page);
            $result = $mobile->getVehicles(1, ['status' => 'active'], 3);
            $plates = array_merge($plates, array_column($result['vehicles'], 'plate'));
        }
        $this->assertSame($expected, $plates);
    }
}
