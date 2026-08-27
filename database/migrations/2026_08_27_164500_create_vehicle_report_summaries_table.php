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
        Schema::create('vehicle_report_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained('fleet_vehicles')->onDelete('cascade');
            $table->date('report_date');
            
            // Sürüş Skoru (Eco-Driving)
            $table->integer('eco_score')->default(100);
            $table->integer('harsh_braking_count')->default(0);
            $table->integer('rapid_acceleration_count')->default(0);
            $table->integer('sharp_turn_count')->default(0);
            $table->integer('speed_violation_count')->default(0);
            
            // Rölanti & İsraf
            $table->integer('idle_minutes')->default(0);
            $table->decimal('idle_fuel_loss_tl', 10, 2)->default(0.00);
            
            // Kapasite & Çalışma
            $table->integer('active_minutes')->default(0);
            $table->integer('total_distance_km')->default(0);
            $table->decimal('active_capacity_percent', 5, 2)->default(0.00);
            
            // Rota Sapması
            $table->decimal('route_deviation_percent', 5, 2)->default(0.00);
            
            // Kestirimci Bakım
            $table->decimal('brake_pad_wear_percent', 5, 2)->default(0.00);

            $table->timestamps();
            
            // Aynı gün için aynı araca 2 kez kayıt girilmemesi için unique
            $table->unique(['vehicle_id', 'report_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_report_summaries');
    }
};
