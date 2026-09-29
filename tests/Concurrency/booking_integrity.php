<?php

/**
 * Pruebas reales de concurrencia, solo en un PostgreSQL temporal por socket.
 * HH_TEST_PG_SOCKET=/private/tmp/hh-... HH_TEST_PG_PORT=55439 php tests/Concurrency/booking_integrity.php
 * Crea su propio esquema, aplica migraciones y elimina solo ese esquema al terminar.
 */
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Exceptions\InvalidCouponException;
use App\Exceptions\PaymentExceedsBalanceException;
use App\Exceptions\RoomNotAvailableException;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\RateRule;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Services\Booking\AvailabilityChecker;
use App\Services\Booking\BookingService;
use App\Services\Booking\PaymentService;
use App\Services\Integrations\GhlBookingSync;
use App\Services\Pricing\CouponValidator;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$socket = getenv('HH_TEST_PG_SOCKET');
if (! $socket || ! str_starts_with($socket, '/private/tmp/hh-') || ! is_dir($socket) || ! function_exists('pcntl_fork')) {
    fwrite(STDERR, "Se requiere un socket PostgreSQL temporal /private/tmp/hh-* y pcntl.\n");
    exit(1);
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$schema = 'audit_'.bin2hex(random_bytes(8));
config(['app.env' => 'testing', 'database.default' => 'pgsql', 'database.connections.pgsql' => [
    'driver' => 'pgsql', 'host' => $socket, 'port' => getenv('HH_TEST_PG_PORT') ?: '55439',
    'database' => 'postgres', 'username' => getenv('HH_TEST_PG_USER') ?: get_current_user(), 'password' => '',
    'charset' => 'utf8', 'prefix' => '', 'search_path' => $schema.',public', 'sslmode' => 'disable',
]]);
DB::purge();
$work = sys_get_temp_dir().'/hh-concurrency-'.bin2hex(random_bytes(6));
mkdir($work, 0700);
$app->instance(GhlBookingSync::class, new class extends GhlBookingSync
{
    public function __construct() {}

    public function sync(Booking $booking): void
    {
        if (DB::transactionLevel() !== 0) {
            throw new RuntimeException('GHL debe ejecutarse después del commit');
        }
    }
});

function verify(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

class SlowAvailability extends AvailabilityChecker
{
    public function isAvailable(Room $room, Carbon $startsAt, Carbon $endsAt, ?int $excludeBookingId = null): bool
    {
        $result = parent::isAvailable($room, $startsAt, $endsAt, $excludeBookingId);
        usleep(200000); // Amplía deliberadamente la ventana de carrera original.

        return $result;
    }
}
class SlowCoupon extends CouponValidator
{
    public function validate(Coupon $coupon, Room $room, Carbon $startsAt, int $durationMinutes, ?Customer $customer, int $priceOriginal, ?int $excludeBookingId = null): int
    {
        $result = parent::validate($coupon, $room, $startsAt, $durationMinutes, $customer, $priceOriginal, $excludeBookingId);
        usleep(200000);

        return $result;
    }
}
function race(string $name, callable $operation): array
{
    global $work;
    DB::disconnect();
    $children = [];
    for ($i = 0; $i < 2; $i++) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('No se pudo iniciar el proceso de prueba');
        }
        if ($pid === 0) {
            try {
                DB::statement("SET statement_timeout = '10s'");
                file_put_contents($work.'/'.$name.'.ready.'.$i, 'ready');
                $deadline = microtime(true) + 8;
                while (! file_exists($work.'/'.$name.'.ready.'.(1 - $i))) {
                    if (microtime(true) > $deadline) {
                        throw new RuntimeException('Barrera agotada');
                    }
                    usleep(10000);
                }
                $result = ['ok' => true, 'value' => $operation($i)];
            } catch (Throwable $e) {
                $result = ['ok' => false, 'type' => get_class($e), 'error' => $e->getMessage()];
            }
            file_put_contents($work.'/'.$name.'.result.'.$i, json_encode($result));
            DB::disconnect();
            exit(0);
        }
        $children[] = $pid;
    }
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
    }
    $results = [];
    foreach ([0, 1] as $i) {
        $results[] = json_decode(file_get_contents($work.'/'.$name.'.result.'.$i), true, flags: JSON_THROW_ON_ERROR);
    }
    echo json_encode(['scenario' => $name, 'results' => $results], JSON_UNESCAPED_UNICODE)."\n";

    return $results;
}

$exitCode = 0;
try {
    DB::statement('CREATE SCHEMA '.$schema);
    if (Artisan::call('migrate', ['--force' => true]) !== 0) {
        throw new RuntimeException(Artisan::output());
    }
    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00'));
    $category = RoomCategory::create(['name' => 'TEST', 'base_capacity' => 2]);
    $customer = Customer::create(['name' => 'Cliente ficticio', 'phone_e164' => '+56900000000']);
    $rate = RateRule::create(['name' => 'TEST', 'is_active' => true, 'extra_person_price' => 10000]);
    $rate->windows()->create(['weekday' => 1, 'start_time' => '00:00:00', 'end_time' => '00:00:00', 'wraps_midnight' => true]);
    $rate->prices()->create(['room_category_id' => $category->id, 'duration_minutes' => 60, 'price' => 20000]);
    $room = fn ($name) => Room::create(['name' => $name, 'room_category_id' => $category->id, 'operational_status' => 'activa', 'buffer_minutes' => 30]);
    $data = fn ($r, $time = '12:00', $extra = []) => array_merge(['room' => $r, 'customer' => $customer, 'starts_at' => Carbon::parse('2026-10-05 '.$time), 'duration_minutes' => 60, 'guests_count' => 2], $extra);

    $r = $room('BUFFER');
    $results = race('buffer', function ($i) use ($r, $data) {
        app()->bind(AvailabilityChecker::class, fn () => new SlowAvailability);

        return app(BookingService::class)->create($data($r, $i === 0 ? '12:00' : '13:10'))->id;
    });
    verify(count(array_filter($results, fn ($r) => $r['ok'])) === 1 && Booking::where('room_id', $r->id)->count() === 1, 'Solo una reserva respeta el margen de aseo');
    verify(count(array_filter($results, fn ($r) => ($r['type'] ?? '') === RoomNotAvailableException::class)) === 1, 'El conflicto devuelve un error operativo');

    $r = $room('OVERLAP');
    $results = race('overlap', fn ($i) => app(BookingService::class)->create($data($r))->id);
    verify(count(array_filter($results, fn ($r) => $r['ok'])) === 1, 'Se rechaza la superposición real');

    $coupon = Coupon::create(['code' => 'ONCE', 'internal_name' => 'Uso único', 'is_active' => true, 'discount_type' => 'percentage', 'discount_value' => 10, 'max_uses_total' => 1]);
    $rooms = [$room('COUPON-A'), $room('COUPON-B')];
    $results = race('coupon', function ($i) use ($rooms, $data) {
        app()->bind(CouponValidator::class, fn () => new SlowCoupon);

        return app(BookingService::class)->create($data($rooms[$i], '12:00', ['coupon_code' => 'ONCE']))->id;
    });
    verify($coupon->redemptions()->count() === 1 && count(array_filter($results, fn ($r) => $r['ok'])) === 1, 'El cupón limitado se utiliza una vez');
    verify(count(array_filter($results, fn ($r) => ($r['type'] ?? '') === InvalidCouponException::class)) === 1, 'El segundo cupón falla por límite, no por SQL');

    $offer = Coupon::create(['internal_name' => 'Oferta una vez', 'auto_apply' => true, 'is_active' => true, 'discount_type' => 'percentage', 'discount_value' => 20, 'max_uses_per_customer' => 1]);
    $rooms = [$room('OFFER-A'), $room('OFFER-B')];
    $results = race('offer', function ($i) use ($rooms, $data) {
        app()->bind(CouponValidator::class, fn () => new SlowCoupon);

        return app(BookingService::class)->create($data($rooms[$i]))->price_final;
    });
    verify(count(array_filter($results, fn ($r) => $r['ok'])) === 2 && $offer->redemptions()->count() === 1, 'Una sola reserva recibe la oferta limitada por cliente');
    $offer->update(['is_active' => false]);

    $method = PaymentMethod::create(['code' => 'test', 'name' => 'Prueba']);
    $booking = app(BookingService::class)->create($data($room('PAYMENT')));
    $token = (string) Str::uuid();
    $results = race('payment_replay', fn ($i) => app(PaymentService::class)->register($booking, $method, 5000, 'REPLAY-'.$i, null, null, null, '123', $token)->id);
    verify($results[0]['ok'] && $results[1]['ok'] && $results[0]['value'] === $results[1]['value'] && $booking->paidAmount() === 5000, 'El reenvío simultáneo registra un único abono');
    $results = race('payment_balance', fn ($i) => app(PaymentService::class)->register($booking, $method, 15000, 'BALANCE-'.$i, null, null)->id);
    verify(count(array_filter($results, fn ($r) => $r['ok'])) === 1 && $booking->paidAmount() === 20000, 'Los pagos simultáneos no superan el saldo');
    verify(count(array_filter($results, fn ($r) => ($r['type'] ?? '') === PaymentExceedsBalanceException::class)) === 1, 'El segundo pago devuelve un error de saldo');
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).': '.$e->getMessage()."\n");
    $exitCode = 1;
} finally {
    Carbon::setTestNow();
    DB::statement('DROP SCHEMA IF EXISTS '.$schema.' CASCADE');
    DB::disconnect();
    foreach (glob($work.'/*') as $file) {
        unlink($file);
    }
    rmdir($work);
}
exit($exitCode);
