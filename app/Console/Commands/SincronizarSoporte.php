<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SincronizarSoporte extends Command
{
    protected $signature = 'app:soporte-sincronizar';

    protected $description = 'Crea (o repara) la cuenta de soporte master a partir de SUPPORT_EMAIL y SUPPORT_PASSWORD';

    public function handle(): int
    {
        $email = config('services.support.email');
        $password = config('services.support.password');
        if (! $email || ! $password) {
            $this->line('SUPPORT_EMAIL / SUPPORT_PASSWORD no están definidos: no se hace nada.');

            return self::SUCCESS;
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            User::create(['name' => 'Soporte', 'email' => $email, 'password' => $password, 'role' => 'soporte', 'is_active' => true]);
            $this->info('Cuenta de soporte creada.');
        } elseif ($user->role !== 'soporte' || ! $user->is_active) {
            // No se toca la contraseña: si ya existe, se mantiene la que tenga.
            $user->update(['role' => 'soporte', 'is_active' => true]);
            $this->info('Cuenta existente restaurada como soporte.');
        } else {
            $this->line('La cuenta de soporte ya existe.');
        }

        return self::SUCCESS;
    }
}
