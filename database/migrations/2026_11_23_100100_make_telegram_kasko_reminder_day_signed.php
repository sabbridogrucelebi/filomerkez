<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_kasko_notification_logs', function (Blueprint $table) {
            $table->smallInteger('reminder_day')->change();
        });
    }

    public function down(): void
    {
        Schema::table('telegram_kasko_notification_logs', function (Blueprint $table) {
            $table->unsignedSmallInteger('reminder_day')->change();
        });
    }
};
