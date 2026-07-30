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
        Schema::create('device_controls', function (Blueprint $table) {
            $table->id();
            $table->string('device_key')->default('NURSERY_01')->unique();
            $table->tinyInteger('system_mode')->default(0)->comment('0=Auto Fuzzy, 1=Manual, 2=Schedule');
            $table->tinyInteger('manual_mode_type')->default(0)->comment('0=Toggle Direct, 1=Timer');
            $table->integer('manual_duration')->default(10)->comment('Timer duration in seconds');
            $table->json('schedules_json')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_controls');
    }
};
