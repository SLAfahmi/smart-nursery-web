<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tab Statistik dan export CSV/XLSX memfilter riwayat berdasarkan recorded_at (waktu rekam alat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sensor_logs', function (Blueprint $table) {
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::table('sensor_logs', function (Blueprint $table) {
            $table->dropIndex(['recorded_at']);
        });
    }
};
