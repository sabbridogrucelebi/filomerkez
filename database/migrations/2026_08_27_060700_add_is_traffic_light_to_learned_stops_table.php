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
        Schema::table('learned_stops', function (Blueprint $table) {
            if (!Schema::hasColumn('learned_stops', 'is_traffic_light')) {
                $table->boolean('is_traffic_light')->default(false)->after('stop_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('learned_stops', function (Blueprint $table) {
            if (Schema::hasColumn('learned_stops', 'is_traffic_light')) {
                $table->dropColumn('is_traffic_light');
            }
        });
    }
};
