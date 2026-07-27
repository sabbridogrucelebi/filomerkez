<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vehicle_maintenance_settings', function (Blueprint $table) {
            $table->unsignedInteger('oil_last_notified_km')->nullable()->after('oil_change_interval_km');
            $table->unsignedInteger('lube_last_notified_km')->nullable()->after('under_lubrication_interval_km');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_maintenance_settings', function (Blueprint $table) {
            $table->dropColumn(['oil_last_notified_km', 'lube_last_notified_km']);
        });
    }
};
