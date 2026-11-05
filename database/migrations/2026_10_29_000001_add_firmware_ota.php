<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OTA cloud: admin mengupload firmware.bin dari tab Config, alat mengambilnya
 * lewat key "ota" di balasan telemetry (lihat firmware src/ota.cpp).
 * - firmware_releases: satu file .bin per rilis; hanya satu yang aktif (ditawarkan ke alat).
 * - device_states.firmware_version / ota_status: dilaporkan alat di telemetry live.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firmware_releases', function (Blueprint $table) {
            $table->id();
            $table->string('version', 32)->unique();
            $table->string('path')->comment('Relatif ke disk local');
            $table->unsignedInteger('size');
            $table->char('md5', 32);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('device_states', function (Blueprint $table) {
            $table->string('firmware_version', 32)->nullable()->after('reported_version')
                ->comment('null = firmware lama yang belum mendukung OTA');
            $table->json('ota_status')->nullable()->after('firmware_version')
                ->comment('{"target","state","error"} dari alat');
        });
    }

    public function down(): void
    {
        Schema::table('device_states', function (Blueprint $table) {
            $table->dropColumn(['firmware_version', 'ota_status']);
        });

        Schema::dropIfExists('firmware_releases');
    }
};
