<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_kasko_notification_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('last_telegram_update_id')->nullable()->after('message_template');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_kasko_notification_settings', function (Blueprint $table) {
            $table->dropColumn('last_telegram_update_id');
        });
    }
};
