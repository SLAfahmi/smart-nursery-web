<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom sensor_logs.device_key ditambahkan ke migration create_sensor_logs_table
 * setelah migration itu sudah dijalankan di hosting, sehingga database hosting
 * tidak pernah mendapatkannya (telemetry gagal: "Unknown column 'device_key'").
 * Database baru sudah punya kolom ini dari create_sensor_logs_table, jadi dilewati.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sensor_logs', 'device_key')) {
            return;
        }

        Schema::table('sensor_logs', function (Blueprint $table) {
            // Data lama milik satu-satunya alat (DeviceControl::DEVICE_KEY)
            $table->string('device_key')->default('NURSERY_01')->after('id')->index();
        });
    }

    public function down(): void
    {
        // Sengaja kosong: di database baru kolom ini milik create_sensor_logs_table,
        // jadi tidak boleh dihapus oleh rollback migration ini.
    }
};
