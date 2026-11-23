<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Permission::updateOrCreate(
            ['key' => 'telegram.manage'],
            ['label' => 'Telegram Kasko Uyarılarını Yönetme']
        );

        Schema::create('telegram_kasko_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->text('bot_token')->nullable();
            $table->text('chat_id')->nullable();
            $table->json('reminder_days');
            $table->text('message_template');
            $table->timestamps();
        });

        Schema::create('telegram_kasko_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->date('kasko_end_date');
            $table->unsignedSmallInteger('reminder_day');
            $table->string('telegram_message_id')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(
                ['company_id', 'vehicle_id', 'kasko_end_date', 'reminder_day'],
                'telegram_kasko_alert_once_per_expiry'
            );
            $table->index(['company_id', 'kasko_end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_kasko_notification_logs');
        Schema::dropIfExists('telegram_kasko_notification_settings');
        Permission::where('key', 'telegram.manage')->delete();
    }
};
