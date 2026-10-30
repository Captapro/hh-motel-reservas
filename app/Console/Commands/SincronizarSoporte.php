<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

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
        } else {
            // Render manda: si SUPPORT_PASSWORD cambió, se aplica; así la cuenta master
            // siempre se puede recuperar cambiando la variable y reiniciando.
            $changes = [];
            if ($user->role !== 'soporte' || ! $user->is_active) {
                $changes += ['role' => 'soporte', 'is_active' => true];
            }
            if (! Hash::check($password, $user->password)) {
                $changes['password'] = $password;
            }
            if ($changes) {
                $user->update($changes);
                $this->info('Cuenta de soporte actualizada: '.implode(', ', array_keys($changes)).'.');
            } else {
                $this->line('La cuenta de soporte ya existe y está al día.');
            }
        }

        return self::SUCCESS;
    }
}
