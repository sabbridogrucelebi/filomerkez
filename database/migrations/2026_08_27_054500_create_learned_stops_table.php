<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learned_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->integer('radius_meters')->default(50);
            $table->integer('stop_count')->default(1);
            $table->timestamp('last_stopped_at')->nullable();
            $table->timestamps();
            
            // Index for faster geospatial queries if needed, though simple math might be enough for a few stops
            $table->index(['company_id', 'latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learned_stops');
    }
};
