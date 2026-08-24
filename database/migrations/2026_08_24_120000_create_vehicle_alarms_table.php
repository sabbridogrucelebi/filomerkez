<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_alarms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->enum('alarm_type', ['speed', 'stop', 'ignition', 'geofence']);
            $table->decimal('threshold_value', 10, 2)->nullable(); // Hız limiti (km/h) veya durak süresi (dakika)
            $table->boolean('is_active')->default(true);
            $table->boolean('notify_email')->default(false);
            $table->boolean('notify_sms')->default(false);
            $table->boolean('notify_panel')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'vehicle_id']);
            $table->index(['alarm_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_alarms');
    }
};
