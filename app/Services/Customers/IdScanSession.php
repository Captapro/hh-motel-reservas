<?php

namespace App\Services\Customers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Sesión corta que une el computador de recepción con un celular que hace de cámara: el computador
 * muestra un QR, el celular lo abre (sin iniciar sesión) y cada carnet que escanea queda acá unos
 * minutos hasta que el computador lo recoge. Lo recogido se borra al instante; todo vence solo.
 * Nunca se guarda la foto, solo lo leído (RUT y nombres).
 */
class IdScanSession
{
    public const TTL_SECONDS = 900;   // 15 minutos
    public const MAX_READS = 12;      // tope por sesión: titular + acompañantes de sobra, y corta abusos

    public function create(int $userId): string
    {
        $token = Str::random(40);
        Cache::put($this->key($token), ['user' => $userId, 'next' => 1, 'reads' => []], self::TTL_SECONDS);

        return $token;
    }

    public function exists(string $token): bool
    {
        return Cache::has($this->key($token));
    }

    /** Guarda una lectura y devuelve su número; null si la sesión venció o ya llegó al tope. */
    public function addRead(string $token, array $read): ?int
    {
        return $this->locked($token, function () use ($token, $read) {
            $state = Cache::get($this->key($token));
            if (! $state || $state['next'] > self::MAX_READS) {
                return null;
            }
            $n = $state['next'];
            $state['reads'][] = ['n' => $n, 'data' => $read];
            $state['next'] = $n + 1;
            Cache::put($this->key($token), $state, self::TTL_SECONDS);

            return $n;
        });
    }

    /**
     * Las lecturas nuevas (número mayor a $after) de una sesión que es de este usuario. Lo ya recibido
     * ($after) se borra. Devuelve null si la sesión no existe o es de otra persona.
     *
     * @return array{reads: array<int, array<string, mixed>>, last: int}|null
     */
    public function takeAfter(string $token, int $userId, int $after): ?array
    {
        return $this->locked($token, function () use ($token, $userId, $after) {
            $state = Cache::get($this->key($token));
            if (! $state || (int) $state['user'] !== $userId) {
                return null;
            }
            $state['reads'] = array_values(array_filter($state['reads'], fn ($r) => $r['n'] > $after));
            Cache::put($this->key($token), $state, self::TTL_SECONDS);

            return ['reads' => $state['reads'], 'last' => max($after, $state['next'] - 1)];
        });
    }

    public function owner(string $token): ?int
    {
        $state = Cache::get($this->key($token));

        return $state ? (int) $state['user'] : null;
    }

    public function close(string $token, int $userId): void
    {
        if ($this->owner($token) === $userId) {
            Cache::forget($this->key($token));
        }
    }

    private function key(string $token): string
    {
        return 'idscan:'.$token;
    }

    private function locked(string $token, callable $callback): mixed
    {
        return Cache::lock('idscan-lock:'.$token, 5)->block(3, $callback);
    }
}
