<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_alert_bots', function (Blueprint $table) {
            $table->boolean('upcoming_summary_enabled')->default(true);
            $table->date('last_upcoming_summary_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('telegram_alert_bots', function (Blueprint $table) {
            $table->dropColumn(['upcoming_summary_enabled', 'last_upcoming_summary_date']);
        });
    }
};
