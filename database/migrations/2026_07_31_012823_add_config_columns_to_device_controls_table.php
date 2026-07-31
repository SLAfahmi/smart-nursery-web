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
        Schema::table('device_controls', function (Blueprint $table) {
            $table->json('fuzzy_config')->nullable()->after('schedules_json');
            $table->json('sys_config')->nullable()->after('fuzzy_config');
        });
    }

    public function down(): void
    {
        Schema::table('device_controls', function (Blueprint $table) {
            $table->dropColumn(['fuzzy_config', 'sys_config']);
        });
    }
};
