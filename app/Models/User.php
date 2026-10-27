<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\AccessSections;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'phone', 'is_active', 'can_use_analyst', 'can_use_help'])]
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
            'can_use_analyst' => 'boolean',
            'can_use_help' => 'boolean',
        ];
    }

    /** Administrador o soporte: soporte es un perfil por encima, con todo lo del administrador. */
    public function isAdministrador(): bool
    {
        return in_array($this->role, ['administrador', 'soporte'], true);
    }

    /** Perfil master: además ve y configura lo técnico (accesos, integraciones) y las cuentas de soporte. */
    public function isSoporte(): bool
    {
        return $this->role === 'soporte';
    }

    /**
     * Herramientas de IA autorizadas persona por persona en Personal. El
     * Master (soporte) siempre puede, para que nadie quede sin poder darlas.
     */
    public function canUseAi(string $tool): bool
    {
        if ($this->isSoporte()) {
            return true;
        }

        return match ($tool) {
            'analista' => (bool) $this->can_use_analyst,
            'ayuda' => (bool) $this->can_use_help,
            default => false,
        };
    }

    /** Si este perfil puede entrar a la sección (el administrador siempre puede). */
    public function canAccess(string $section): bool
    {
        return AccessSections::allows($this->role, $section);
    }

    /** Primera sección a la que este perfil puede entrar, para después del login. */
    public function homeRoute(): string
    {
        foreach (AccessSections::SECTIONS as $key => [, $route]) {
            if ($this->canAccess($key)) {
                return $route;
            }
        }

        return 'login';
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
