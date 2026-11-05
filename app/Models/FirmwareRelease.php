<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Rilis firmware untuk OTA cloud. File .bin (sudah berisi dashboard lokal alat)
 * disimpan di disk local, tidak bisa diakses publik.
 */
class FirmwareRelease extends Model
{
    protected $fillable = [
        'version',
        'path',
        'size',
        'md5',
        'notes',
        'is_active',
        'uploaded_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'size' => 'integer',
    ];

    /** Rilis yang sedang ditawarkan ke alat (paling banyak satu) */
    public static function active(): ?self
    {
        return static::where('is_active', true)->latest('id')->first();
    }

    /**
     * Isi key "ota" pada balasan telemetry (lihat firmware src/ota.cpp: otaOffer).
     * path relatif: alat menyusun URL dari CLOUD_BASE_URL + path.
     */
    public function toOtaOffer(): array
    {
        return [
            'version' => $this->version,
            'path' => "/api/v1/firmware/{$this->id}/download",
            'size' => $this->size,
            'md5' => $this->md5,
        ];
    }
}
