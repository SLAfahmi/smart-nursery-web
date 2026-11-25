<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'disabled_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Akses dicabut admin (`php artisan nursery:user <email> --cabut`). Riwayat aktivitas tetap disimpan. */
    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function deviceControls()
    {
        return $this->hasMany(DeviceControl::class, 'updated_by_user_id');
    }
}
