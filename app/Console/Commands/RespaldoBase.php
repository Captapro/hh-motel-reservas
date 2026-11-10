<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\StaffNotice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Respaldo de la base de datos: pg_dump (formato restaurable con pg_restore) que se
 * sube a una carpeta de Google Drive mediante un pequeño script de Apps Script
 * (ver entregables/respaldos). Sin --programado corre al tiro; con --programado
 * lo dispara el ciclo del entrypoint cada 10 min y solo actúa una vez al día,
 * pasada la hora configurada, con hasta 3 intentos y alerta al tercero.
 */
class RespaldoBase extends Command
{
    protected $signature = 'app:respaldo-base {--programado : Solo una vez al día, pasada la hora de BACKUP_HOUR}';

    protected $description = 'Respalda la base de datos completa y la sube a Google Drive';

    public const ACTION_OK = 'sistema.respaldo';

    public const ACTION_FAIL = 'sistema.respaldo_fallo';

    private const MAX_ATTEMPTS = 3;

    /** Tope del archivo: se manda en base64 de una vez; más grande pide subir por partes. */
    private const MAX_BYTES = 20 * 1024 * 1024;

    public function handle(): int
    {
        $tz = 'America/Santiago';
        $now = CarbonImmutable::now($tz);

        if ($this->option('programado')) {
            if ((string) config('services.backup.drive_url') === '') {
                return self::SUCCESS; // sin configurar: no molesta
            }
            if ($now->hour < (int) config('services.backup.hour', 5)) {
                return self::SUCCESS;
            }
            $since = $now->startOfDay()->utc();
            try {
                if (AuditLog::where('action', self::ACTION_OK)->where('created_at', '>=', $since)->exists()) {
                    return self::SUCCESS;
                }
                if (AuditLog::where('action', self::ACTION_FAIL)->where('created_at', '>=', $since)->count() >= self::MAX_ATTEMPTS) {
                    return self::SUCCESS;
                }
            } catch (Throwable) {
                return self::SUCCESS;
            }
        }

        $path = null;
        try {
            [$name, $path] = $this->dump($now);
            $size = filesize($path);
            $this->upload($name, $path, $size);
            $this->record(self::ACTION_OK, ['archivo' => $name, 'bytes' => $size]);
            $this->info("Respaldo subido: {$name} (".number_format($size / 1024, 0, ',', '.').' KB)');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Respaldo fallido: '.$e->getMessage());
            $this->failed($e->getMessage());

            return self::FAILURE;
        } finally {
            if ($path && is_file($path)) {
                @unlink($path);
            }
        }
    }

    /** @return array{0: string, 1: string} nombre del archivo y ruta local */
    private function dump(CarbonImmutable $now): array
    {
        $config = DB::connection()->getConfig();
        if (($config['driver'] ?? '') !== 'pgsql') {
            throw new \RuntimeException('El respaldo solo funciona con PostgreSQL.');
        }

        $dir = storage_path('app/respaldos');
        File::ensureDirectoryExists($dir);
        $name = 'hh-motel-'.$now->format('Y-m-d_His').'.dump';
        $path = $dir.'/'.$name;

        $result = Process::timeout(600)
            ->env(array_filter([
                'PGPASSWORD' => (string) ($config['password'] ?? ''),
                'PGSSLMODE' => (string) ($config['sslmode'] ?? ''),
            ], fn ($v) => $v !== ''))
            ->run([
                'pg_dump', '--format=custom', '--no-owner', '--no-privileges',
                '--host', (string) $config['host'], '--port', (string) ($config['port'] ?? 5432),
                '--username', (string) $config['username'], '--dbname', (string) $config['database'],
                '--file', $path,
            ]);

        if (! $result->successful() || ! is_file($path) || filesize($path) < 1024) {
            throw new \RuntimeException('pg_dump falló: '.trim(substr($result->errorOutput(), 0, 300)));
        }
        if (filesize($path) > self::MAX_BYTES) {
            throw new \RuntimeException('La base ya pesa más de 20 MB respaldada: hay que cambiar el envío a Drive por partes.');
        }

        return [$name, $path];
    }

    private function upload(string $name, string $path, int $size): void
    {
        $url = (string) config('services.backup.drive_url');
        $token = (string) config('services.backup.drive_token');
        if ($url === '' || $token === '') {
            throw new \RuntimeException('Falta BACKUP_DRIVE_URL o BACKUP_DRIVE_TOKEN en Render.');
        }

        $response = Http::timeout(180)->asJson()->post($url, [
            'token' => $token,
            'name' => $name,
            'data' => base64_encode(file_get_contents($path)),
        ]);

        $body = $response->json();
        if (! $response->successful() || ! is_array($body) || ($body['ok'] ?? false) !== true) {
            throw new \RuntimeException('Drive no aceptó el archivo: '.($body['error'] ?? 'HTTP '.$response->status()));
        }
        if ((int) ($body['size'] ?? -1) !== $size) {
            throw new \RuntimeException("Drive guardó {$body['size']} bytes y se enviaron {$size}.");
        }
    }

    private function record(string $action, array $data): void
    {
        try {
            AuditLog::record(null, $action, 'Respaldo', $data['archivo'] ?? 'base', null, $data);
        } catch (Throwable) {
        }
    }

    private function failed(string $reason): void
    {
        try {
            $this->record(self::ACTION_FAIL, ['error' => substr($reason, 0, 300)]);
            $today = CarbonImmutable::now('America/Santiago')->startOfDay()->utc();
            $fails = AuditLog::where('action', self::ACTION_FAIL)->where('created_at', '>=', $today)->count();
            // Un fallo suelto puede ser pasajero (deploy, corte): se avisa recién al tercero, o en la corrida manual.
            if ($fails >= self::MAX_ATTEMPTS || ! $this->option('programado')) {
                $admin = User::where('role', 'administrador')->orderBy('id')->first();
                if ($admin) {
                    StaffNotice::alertAdmins($admin->id, 'Falló el respaldo de la base', substr($reason, 0, 200));
                }
            }
        } catch (Throwable) {
        }
    }
}
