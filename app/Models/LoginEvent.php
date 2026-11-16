<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Throwable;

/** Entrada, salida o intento fallido de una cuenta de acceso (ver pantalla «Actividad y accesos»). */
#[Fillable(['user_id', 'email', 'result', 'ip', 'user_agent', 'created_at'])]
class LoginEvent extends Model
{
    public $timestamps = false;

    public const RESULT_LABELS = ['ok' => 'Entró', 'salida' => 'Salió', 'fallido' => 'Clave incorrecta', 'desactivada' => 'Cuenta desactivada'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Registra el evento sin que un fallo del registro pueda impedir entrar o salir. */
    public static function note(Request $request, string $result, ?User $user = null, ?string $email = null): void
    {
        try {
            static::create([
                'user_id' => $user?->id,
                'email' => $email ?? $user?->email,
                'result' => $result,
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Dispositivo en palabras cortas: «Chrome · Mac». */
    public function device(): string
    {
        $ua = (string) $this->user_agent;
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Navegador',
        };
        $os = match (true) {
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iPhone/iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Macintosh') || str_contains($ua, 'Mac OS') => 'Mac',
            str_contains($ua, 'Linux') => 'Linux',
            default => '—',
        };

        return $ua === '' ? '—' : $browser.' · '.$os;
    }
}
