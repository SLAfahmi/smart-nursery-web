<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZoneLog extends Model
{
    protected $fillable = [
        'sensor_log_id',
        'zone_id',
        'soil_moisture',
        'relay_status',
        'state',
        'fuzzy_duration',
    ];

    protected $casts = [
        'zone_id' => 'integer',
        'soil_moisture' => 'float',
        'relay_status' => 'boolean',
        'fuzzy_duration' => 'float',
    ];

    public function sensorLog(): BelongsTo
    {
        return $this->belongsTo(SensorLog::class);
    }

    /**
     * Batas selisih antar baris (detik) yang dihitung sebagai waktu valve ON. Dipakai tab Statistik dan export.
     *
     * Riwayat disimpan tiap history_interval dan setiap status valve berubah, jadi lama valve ON
     * = selisih waktu dari baris ON ke baris berikutnya zona itu. Selisih dibatasi supaya celah
     * data (alat offline, batch offline yang jarang) tidak terhitung sebagai waktu menyiram.
     */
    public static function maxValveGap(): int
    {
        return max(120, 2 * (int) config('nursery.history_interval'));
    }
}
