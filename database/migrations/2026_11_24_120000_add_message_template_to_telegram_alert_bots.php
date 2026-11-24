<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_alert_bots', function (Blueprint $table) {
            $table->text('message_template')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('telegram_alert_bots', function (Blueprint $table) {
            $table->dropColumn('message_template');
        });
    }
};
