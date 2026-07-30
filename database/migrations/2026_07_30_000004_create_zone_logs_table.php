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
        Schema::create('zone_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sensor_log_id')->constrained('sensor_logs')->cascadeOnDelete();
            $table->tinyInteger('zone_id')->comment('1 to 5');
            $table->integer('soil_moisture');
            $table->tinyInteger('zone_mode')->default(0);
            $table->integer('duration')->default(0);
            $table->boolean('relay_status')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zone_logs');
    }
};
