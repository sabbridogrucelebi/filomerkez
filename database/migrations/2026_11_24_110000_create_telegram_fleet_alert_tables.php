<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_alert_bots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 24);
            $table->boolean('is_enabled')->default(false);
            $table->text('bot_token')->nullable();
            $table->text('chat_id')->nullable();
            $table->json('reminder_days')->nullable();
            $table->unsignedInteger('maintenance_km')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'channel']);
        });

        Schema::create('telegram_alert_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bot_id')->constrained('telegram_alert_bots')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 100);
            $table->timestamps();
            $table->unique(['bot_id', 'vehicle_id', 'event_key'], 'telegram_fleet_event_once');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_alert_deliveries');
        Schema::dropIfExists('telegram_alert_bots');
    }
};
