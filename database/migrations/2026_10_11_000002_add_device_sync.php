<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sinkronisasi dua arah web <-> firmware:
 * - device_controls.command_version naik setiap user mengubah konfigurasi di web.
 *   Firmware melaporkan versi terakhir yang ia terapkan; selama berbeda, server
 *   mengirim device_command. Jika sama, server menyalin kondisi alat ke web.
 * - device_controls.pending_relays: perintah relay sekali kirim dari web.
 * - device_states: kondisi terakhir yang dilaporkan alat.
 * - fuzzy_config dihapus: parameter fuzzy tertanam di firmware (fuzzy_logic.cpp).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_controls', function (Blueprint $table) {
            $table->unsignedInteger('command_version')->default(0)->after('sys_config')
                ->comment('Naik setiap konfigurasi diubah dari web');
            $table->json('pending_relays')->nullable()->after('command_version')
                ->comment('{"main"|zona: {"on": bool, "at": waktu}}');
        });

        Schema::table('device_controls', function (Blueprint $table) {
            $table->dropColumn('fuzzy_config');
        });

        // Konfigurasi yang sudah diatur di web dikirim sekali ke alat setelah update ini
        DB::table('device_controls')->update(['command_version' => 1]);

        Schema::create('device_states', function (Blueprint $table) {
            $table->id();
            $table->string('device_key')->default('NURSERY_01')->unique();
            $table->unsignedInteger('reported_version')->nullable()
                ->comment('command_version yang sudah diterapkan alat; null = firmware lama');
            $table->timestamp('last_seen_at')->nullable();
            $table->json('state')->nullable()->comment('Telemetry live terakhir');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_states');

        Schema::table('device_controls', function (Blueprint $table) {
            $table->json('fuzzy_config')->nullable()->after('schedules_json');
        });

        Schema::table('device_controls', function (Blueprint $table) {
            $table->dropColumn(['command_version', 'pending_relays']);
        });
    }
};
