<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SincronizarAnfitrion extends Command
{
    protected $signature = 'app:anfitrion-sincronizar';

    protected $description = 'Crea la cuenta de anfitrión (recepción) a partir de HOST_EMAIL y HOST_PASSWORD, si todavía no existe';

    public function handle(): int
    {
        $email = config('services.host.email');
        $password = config('services.host.password');
        if (! $email || ! $password) {
            $this->line('HOST_EMAIL / HOST_PASSWORD no están definidos: no se hace nada.');

            return self::SUCCESS;
        }

        if (User::where('email', $email)->exists()) {
            // Nunca se pisa una cuenta existente (ni su clave ni su rol).
            $this->line('La cuenta de anfitrión ya existe.');

            return self::SUCCESS;
        }

        User::create(['name' => 'Recepción', 'email' => $email, 'password' => $password, 'role' => 'recepcion', 'is_active' => true]);
        $this->info('Cuenta de anfitrión creada.');

        return self::SUCCESS;
    }
}
