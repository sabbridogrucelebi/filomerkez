<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->integer('worked_days')->default(0)->after('period_month');
            $table->integer('unpaid_leave_days')->default(0)->after('worked_days');
            $table->integer('paid_leave_days')->default(0)->after('unpaid_leave_days');
            $table->decimal('holiday_bonus', 10, 2)->default(0)->after('extra_payment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['worked_days', 'unpaid_leave_days', 'paid_leave_days', 'holiday_bonus']);
        });
    }
};
