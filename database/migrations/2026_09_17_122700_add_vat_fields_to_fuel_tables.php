<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_stations', function (Blueprint $table) {
            if (!Schema::hasColumn('fuel_stations', 'vat_rate')) {
                $table->decimal('vat_rate', 5, 2)->default(0)->after('discount_value');
            }
        });

        Schema::table('fuels', function (Blueprint $table) {
            if (!Schema::hasColumn('fuels', 'vat_rate')) {
                $table->decimal('vat_rate', 5, 2)->default(0)->after('total_cost');
                $table->decimal('vat_amount', 12, 2)->default(0)->after('vat_rate');
                $table->decimal('net_cost', 12, 2)->default(0)->after('vat_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fuel_stations', function (Blueprint $table) {
            $table->dropColumn('vat_rate');
        });

        Schema::table('fuels', function (Blueprint $table) {
            $table->dropColumn(['vat_rate', 'vat_amount', 'net_cost']);
        });
    }
};
