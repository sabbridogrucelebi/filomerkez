<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_kasko_notification_settings', function (Blueprint $table) {
            $table->boolean('upcoming_summary_enabled')->default(true)->after('is_enabled');
            $table->unsignedSmallInteger('upcoming_summary_days')->default(20)->after('upcoming_summary_enabled');
            $table->date('last_upcoming_summary_date')->nullable()->after('last_telegram_update_id');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_kasko_notification_settings', function (Blueprint $table) {
            $table->dropColumn(['upcoming_summary_enabled', 'upcoming_summary_days', 'last_upcoming_summary_date']);
        });
    }
};
