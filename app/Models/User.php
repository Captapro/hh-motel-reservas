<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'phone', 'is_active'])]
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
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isAdministrador(): bool
    {
        return $this->role === 'administrador';
    }

    public function isRecepcion(): bool
    {
        return $this->role === 'recepcion';
    }

    public function shiftLogs(): HasMany
    {
        return $this->hasMany(StaffShiftLog::class);
    }

    public function openShiftLog(): ?StaffShiftLog
    {
        StaffShiftLog::closeStale();

        return $this->shiftLogs()->whereNull('ended_at')->latest('started_at')->first();
    }
}
