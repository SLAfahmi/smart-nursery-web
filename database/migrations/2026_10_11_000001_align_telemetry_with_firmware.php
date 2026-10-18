<?php

use App\Models\DeviceControl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyamakan struktur data dengan payload firmware (cloud_task.cpp: buildTelemetry):
 * - nilai sensor boleh null (sensor error / tidak terpasang) dan berupa desimal
 * - status pompa utama, state zona, dan durasi fuzzy ikut disimpan
 * - jadwal memakai format firmware: [{"zone":1,"slots":[...]}]
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sensor_logs', function (Blueprint $table) {
            $table->float('temperature')->nullable()->change();
            $table->float('humidity')->nullable()->change();
            $table->float('light_level')->nullable()->change();
        });

        Schema::table('sensor_logs', function (Blueprint $table) {
            $table->boolean('relay_main')->default(false)->after('system_mode');
        });

        Schema::table('zone_logs', function (Blueprint $table) {
            $table->float('soil_moisture')->nullable()->change();
        });

        Schema::table('zone_logs', function (Blueprint $table) {
            $table->dropColumn(['zone_mode', 'duration']);
        });

        Schema::table('zone_logs', function (Blueprint $table) {
            $table->string('state', 16)->default('idle')->after('relay_status')->comment('idle, queued, watering, soaking');
            $table->float('fuzzy_duration')->default(0)->after('state')->comment('Rekomendasi fuzzy (detik)');
        });

        // Jadwal lama [{"schedules":[...]}, ...] -> [{"zone":1,"slots":[...]}, ...]
        $zones = config('nursery.zones');
        foreach (DB::table('device_controls')->get(['id', 'schedules_json']) as $row) {
            $old = json_decode($row->schedules_json ?? '', true);
            if (!is_array($old) || isset($old[0]['slots'])) {
                continue;
            }

            $defaults = DeviceControl::defaultSchedules();
            $new = [];
            for ($i = 0; $i < $zones; $i++) {
                $new[] = [
                    'zone' => $i + 1,
                    'slots' => $old[$i]['schedules'] ?? $defaults[$i]['slots'],
                ];
            }

            DB::table('device_controls')->where('id', $row->id)->update(['schedules_json' => json_encode($new)]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('device_controls')->get(['id', 'schedules_json']) as $row) {
            $new = json_decode($row->schedules_json ?? '', true);
            if (!is_array($new) || !isset($new[0]['slots'])) {
                continue;
            }

            $old = array_map(fn ($zone) => ['schedules' => $zone['slots']], $new);
            DB::table('device_controls')->where('id', $row->id)->update(['schedules_json' => json_encode($old)]);
        }

        Schema::table('zone_logs', function (Blueprint $table) {
            $table->dropColumn(['state', 'fuzzy_duration']);
        });

        Schema::table('zone_logs', function (Blueprint $table) {
            $table->tinyInteger('zone_mode')->default(0);
            $table->integer('duration')->default(0);
        });

        // Tetap nullable: data yang masuk setelah migrasi ini bisa berisi null
        Schema::table('zone_logs', function (Blueprint $table) {
            $table->integer('soil_moisture')->nullable()->change();
        });

        Schema::table('sensor_logs', function (Blueprint $table) {
            $table->dropColumn('relay_main');
        });

        Schema::table('sensor_logs', function (Blueprint $table) {
            $table->float('temperature')->nullable()->change();
            $table->float('humidity')->nullable()->change();
            $table->integer('light_level')->nullable()->change();
        });
    }
};
