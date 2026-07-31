<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceControl extends Model
{
    protected $fillable = [
        'device_key',
        'system_mode',
        'manual_mode_type',
        'manual_duration',
        'schedules_json',
        'fuzzy_config',
        'sys_config',
        'updated_by_user_id',
    ];

    protected $casts = [
        'system_mode' => 'integer',
        'manual_mode_type' => 'integer',
        'manual_duration' => 'integer',
        'schedules_json' => 'array',
        'fuzzy_config' => 'array',
        'sys_config' => 'array',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
