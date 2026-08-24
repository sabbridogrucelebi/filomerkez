<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name'); // "Varsayılan Mesai", "Hafta Sonu Vardiya" vs.
            $table->json('schedule'); // {"monday": {"start":"08:00","end":"18:00"}, "tuesday": {...}, ...}
            $table->boolean('alert_outside_hours')->default(false); // Mesai dışı hareket alarmı
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
