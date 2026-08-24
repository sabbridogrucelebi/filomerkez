<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geofences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('type', ['restricted', 'safe'])->default('safe'); // Yasak bölge / Güvenli bölge
            $table->string('color', 7)->default('#4338ca'); // HEX renk
            $table->json('coordinates'); // [{lat, lng}, ...] çokgen noktaları
            $table->decimal('radius', 10, 2)->nullable(); // Çember bölge için metre
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('company_id');
        });

        // Pivot: Hangi araç hangi bölgeye atanmış
        Schema::create('vehicle_geofences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->foreignId('geofence_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['vehicle_id', 'geofence_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_geofences');
        Schema::dropIfExists('geofences');
    }
};
