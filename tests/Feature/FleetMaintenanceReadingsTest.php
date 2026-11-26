<?php

namespace Tests\Feature;

use App\Models\Fleet\Vehicle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FleetMaintenanceReadingsTest extends TestCase
{
    public function test_fleet_readings_match_individual_calculations_in_two_queries(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::statement('CREATE TABLE vehicles (id INTEGER PRIMARY KEY, company_id INTEGER, current_km INTEGER, deleted_at TEXT)');
        DB::statement('CREATE TABLE fuels (vehicle_id INTEGER, company_id INTEGER, km INTEGER, date TEXT)');
        DB::statement('CREATE TABLE vehicle_maintenance_settings (vehicle_id INTEGER, company_id INTEGER, oil_change_interval_km INTEGER, under_lubrication_interval_km INTEGER)');
        DB::statement('CREATE TABLE vehicle_maintenances (id INTEGER PRIMARY KEY, vehicle_id INTEGER, company_id INTEGER, km INTEGER, maintenance_type TEXT, status TEXT, service_date TEXT)');
        $this->actingAs(new User(['company_id' => 1, 'role' => 'company_admin']));
        foreach (range(1, 45) as $id) {
            DB::table('vehicles')->insert(['id' => $id, 'company_id' => 1, 'current_km' => 1000]);
            DB::table('vehicle_maintenance_settings')->insert(['vehicle_id' => $id, 'company_id' => 1, 'oil_change_interval_km' => 500, 'under_lubrication_interval_km' => 250]);
        }
        DB::table('fuels')->insert(['vehicle_id' => 1, 'company_id' => 1, 'km' => 1300]);
        DB::table('vehicle_maintenances')->insert(['vehicle_id' => 1, 'company_id' => 1, 'km' => 900, 'maintenance_type' => 'YAĞ BAKIMI', 'status' => 'completed', 'service_date' => '2026-11-15']);
        DB::enableQueryLog();
        $readStatus = fn (Vehicle $vehicle) => $vehicle->maintenance_status;
        $individual = Vehicle::with(['maintenanceSetting', 'maintenances'])->orderBy('id')->get()->map($readStatus);
        $individualQueryCount = count(DB::getQueryLog());
        DB::flushQueryLog();
        $bulk = Vehicle::withMaintenanceReadings()->orderBy('id')->get()->map($readStatus);
        $bulkQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame($individual->all(), $bulk->all());
        $this->assertSame(100, $bulk[0]['oil_remaining']);
        $this->assertSame(250, $bulk[0]['lube_remaining']);
        $this->assertGreaterThan($bulkQueryCount, $individualQueryCount);
        $this->assertSame(2, $bulkQueryCount);
    }
}
