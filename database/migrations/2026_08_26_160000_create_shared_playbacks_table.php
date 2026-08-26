<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('shared_playbacks', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 16)->unique(); // e.g. "a1b2c3d4"
            $table->unsignedBigInteger('company_id'); // Multitenancy rule
            $table->unsignedBigInteger('vehicle_id');
            $table->string('start_date');
            $table->string('end_date');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Depending on the schema, company_id might be constrained or not. We'll leave it without FK just in case, or add it if standard.
            // But vehicle_id usually exists. We will omit foreign keys to avoid migration errors if table names differ, we just need the columns.
        });
    }

    public function down()
    {
        Schema::dropIfExists('shared_playbacks');
    }
};
