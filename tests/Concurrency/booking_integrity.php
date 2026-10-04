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
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\BookingAddonController;
use App\Http\Controllers\BookingCancelController;
use App\Http\Controllers\BookingCheckInController;
use App\Http\Controllers\BookingFinalizeController;
use App\Models\Booking;
use App\Models\BookingAddon;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RateRule;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Services\Booking\AvailabilityChecker;
use App\Services\Booking\BookingService;
use App\Services\Booking\ConsumptionService;
use App\Services\Booking\PaymentService;
use App\Services\Integrations\GhlBookingSync;
use App\Services\Pricing\CouponValidator;
use App\Services\Pricing\RateRuleResolver;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
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
class SlowConsumption extends ConsumptionService
{
    public function addProduct(Booking $booking, Product $product, int $quantity, ?int $userId): BookingAddon
    {
        usleep(250000); // Simula un check-in "lento" mientras la fila de la pieza sigue bloqueada.

        return parent::addProduct($booking, $product, $quantity, $userId);
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
    // Fuerza ambos órdenes de llegada para cancelar y hacer check-in.
    config(['session.driver' => 'array']);
    foreach ([0, 1] as $first) {
        $booking = app(BookingService::class)->create($data($room('CANCEL-CHECKIN-'.$first), '12:00', ['guests_count' => 1]));
        $results = race('cancel_checkin_'.$first, function ($i) use ($booking, $first) {
            if ($i !== $first) {
                usleep(100000);
            } else {
                Booking::retrieved(function (Booking $read) use ($booking) {
                    if ($read->id === $booking->id) {
                        usleep(300000);
                    }
                });
            }
            $request = Request::create('/reservas/'.$booking->code, 'POST');
            if ($i === 0) {
                $response = app(BookingCancelController::class)->store($request, $booking->code);
            } else {
                $response = app(BookingCheckInController::class)->store(
                    $request, $booking->code, app(ConsumptionService::class),
                    app(AvailabilityChecker::class), app(RateRuleResolver::class)
                );
            }

            return $response->getStatusCode();
        });
        verify($results[0]['ok'] && $results[1]['ok'], 'Cancelación y check-in terminan sin errores de base de datos');
        $booking->refresh();
        verify(
            ($booking->booking_status === 'CANCELADA' && $booking->checked_in_at === null)
                || ($booking->booking_status === 'CHECK_IN' && $booking->checked_in_at !== null),
            'Cancelar y hacer check-in simultáneamente conservan un estado coherente (orden '.$first.')'
        );
    }

    // Finalizar + agregar consumo al mismo tiempo: ninguno de los dos debía
    // poder cerrar la reserva viendo el saldo de ANTES de que el otro
    // terminara -- antes ninguno bloqueaba la fila de la reserva.
    $product = Product::create(['name' => 'Bebida', 'price' => 5000, 'is_active' => true]);
    $booking = app(BookingService::class)->create($data($room('FINALIZE-ADDON'), '12:00', ['guests_count' => 1]));
    $booking->update(['checked_in_at' => now()]);
    app(PaymentService::class)->register($booking, $method, 20000, 'PAGO-FINAL', null, null);
    verify($booking->fresh()->balanceDue() === 0, 'Reserva de prueba queda pagada antes de la carrera');
    $results = race('finalize_addon', function ($i) use ($booking, $product) {
        if ($i === 0) {
            usleep(100000); // deja que el consumo se cuele primero la mitad de las veces
            $request = Request::create('/reservas/'.$booking->code.'/finalizar', 'POST', ['confirm_room_checked' => '1']);

            return app(BookingFinalizeController::class)->store($request, $booking->code, app(ConsumptionService::class))->getStatusCode();
        }
        $request = Request::create('/reservas/'.$booking->code.'/consumo', 'POST', ['item' => 'product:'.$product->id, 'quantity' => 1]);

        return app(BookingAddonController::class)->store($request, $booking->code, app(ConsumptionService::class))->getStatusCode();
    });
    verify($results[0]['ok'] && $results[1]['ok'], 'Finalizar y agregar consumo simultáneos terminan sin errores de base de datos');
    $booking->refresh();
    verify(
        ! ($booking->booking_status === 'FINALIZADA' && $booking->balanceDue() > 0),
        'Finalizar y agregar consumo simultáneos nunca dejan la reserva cerrada con deuda'
    );

    // Trasladar (reschedule) y extender (hora adicional) al mismo tiempo:
    // extraHour() no puede terminar protegiendo la pieza VIEJA si el
    // traslado le cambió la pieza a la reserva en el medio.
    $roomFrom = $room('MOVE-FROM');
    $roomTo = $room('MOVE-TO');
    // Vecino en la pieza destino que deja un hueco de solo 20 min contra el
    // margen de 30 configurado -- si la hora adicional (12:00-13:00 -> 13:00-14:00)
    // se aplicara sin revisar la pieza destino de verdad, este vecino (14:20)
    // no se vería y la extensión pasaría igual, violando el margen.
    Booking::create([
        'code' => 'NEIGHBOR', 'pass_token' => Str::random(40), 'customer_id' => $customer->id, 'room_id' => $roomTo->id,
        'starts_at' => Carbon::parse('2026-10-05 14:20'), 'ends_at' => Carbon::parse('2026-10-05 15:20'),
        'duration_minutes' => 60, 'guests_count' => 2, 'booking_status' => 'CONFIRMADA', 'payment_status' => 'PAGADA',
        'price_original' => 20000, 'price_final' => 20000,
    ]);
    $moving = app(BookingService::class)->create($data($roomFrom, '12:00', ['guests_count' => 1]));
    $results = race('move_extend', function ($i) use ($moving, $roomTo) {
        if ($i === 0) {
            return app(BookingService::class)->reschedule($moving->fresh(), [
                'room' => $roomTo, 'starts_at' => $moving->starts_at, 'duration_minutes' => 60, 'guests_count' => 1,
            ])->room_id;
        }
        usleep(50000); // le da tiempo al traslado de arrancar primero la mayoría de las veces
        $request = Request::create('/reservas/'.$moving->code.'/hora-adicional', 'POST');

        return app(BookingAddonController::class)->extraHour(
            $moving->code, app(ConsumptionService::class), app(AvailabilityChecker::class), app(RateRuleResolver::class)
        )->getStatusCode();
    });
    verify($results[0]['ok'] && $results[1]['ok'], 'Trasladar y extender simultáneos terminan sin errores de base de datos');
    $moving->refresh();
    // Invariante real: cualquiera sea el resultado, ninguna reserva activa en
    // la pieza FINAL de $moving puede quedar a menos del margen de otra.
    $conflict = Booking::where('room_id', $moving->room_id)->where('id', '!=', $moving->id)
        ->whereNotIn('booking_status', ['CANCELADA', 'EXPIRADA', 'NO_SHOW'])
        ->where('starts_at', '<', $moving->ends_at->copy()->addMinutes($roomTo->buffer_minutes))
        ->where('ends_at', '>', $moving->starts_at->copy()->subMinutes($roomTo->buffer_minutes))
        ->exists();
    verify(! $conflict, 'Trasladar y extender simultáneos nunca dejan el margen de aseo incumplido en la pieza final');
    Booking::where('code', 'NEIGHBOR')->delete();

    // Un check-in no puede bloquear una reserva nueva en una pieza sin
    // ninguna relación -- antes se bloqueaban TODAS las piezas de la tabla.
    $slowRoom = $room('SLOW-CHECKIN');
    $unrelatedRoom = $room('UNRELATED');
    $slowProduct = Product::create(['name' => 'Producto lento', 'price' => 1000, 'is_active' => true]);
    $slowBooking = app(BookingService::class)->create($data($slowRoom, '12:00', ['guests_count' => 1]));
    $results = race('checkin_scope', function ($i) use ($slowBooking, $slowProduct, $unrelatedRoom, $customer) {
        $t0 = microtime(true);
        if ($i === 0) {
            // El producto agregado durante el check-in tarda a propósito --
            // simula cualquier procesamiento lento mientras la fila de la
            // pieza propia sigue bloqueada dentro de la transacción.
            $request = Request::create('/reservas/'.$slowBooking->code.'/checkin', 'POST', [
                'guest_names' => '', 'quantities' => [$slowProduct->id => 1],
            ]);
            app(BookingCheckInController::class)->store(
                $request, $slowBooking->code, new SlowConsumption(app(PaymentService::class)),
                app(AvailabilityChecker::class), app(RateRuleResolver::class)
            );
        } else {
            usleep(30000); // deja que el check-in tome su bloqueo primero
            app(BookingService::class)->create(['room' => $unrelatedRoom, 'customer' => $customer, 'starts_at' => Carbon::parse('2026-10-05 12:00'), 'duration_minutes' => 60, 'guests_count' => 1]);
        }

        return microtime(true) - $t0;
    });
    verify($results[0]['ok'] && $results[1]['ok'], 'Check-in y reserva nueva en pieza sin relación terminan sin errores');
    // El check-in lento tarda >=200ms a propósito (SlowAvailability). Si la
    // reserva en la pieza SIN relación tuviera que esperar el bloqueo de
    // TODAS las piezas, tardaría un tiempo comparable -- acotamos bien
    // debajo de eso para probar que no esperó.
    verify($results[1]['value'] < 0.15, 'La reserva en una pieza sin relación no espera a que termine el check-in lento de otra pieza');
    function waitForSignal(string $name): void
    {
        global $work;
        $until = microtime(true) + 6;
        while (! file_exists($work.'/'.$name)) {
            if (microtime(true) > $until) {
                throw new RuntimeException('Timeout '.$name);
            }
            usleep(10000);
        }
    }
    function signalReady(string $name): void
    {
        global $work;
        file_put_contents($work.'/'.$name, 'ok');
    }
    $stockProduct = Product::create(['name' => 'Stock auditado', 'price' => 1000, 'is_active' => true, 'track_inventory' => true, 'stock' => 10]);
    $stockBooking = app(BookingService::class)->create($data($room('STOCK-AUDIT')));
    $results = race('stock_restock_sale', function ($i) use ($stockProduct, $stockBooking) {
        if ($i === 0) {
            $stale = Product::findOrFail($stockProduct->id);
            signalReady('stock_read');
            waitForSignal('sale_done');

            return app(ProductController::class)->restock(
                Request::create('/stock', 'POST', ['delta' => 5]), $stale
            )->getStatusCode();
        }
        waitForSignal('stock_read');
        $result = app(ConsumptionService::class)->addProduct($stockBooking, $stockProduct, 1, null);
        signalReady('sale_done');

        return $result->id;
    });
    verify($results[0]['ok'] && $results[1]['ok'], 'venta y reposición concurrentes terminan correctamente');
    verify($stockProduct->fresh()->stock === 14, 'El stock conserva la venta concurrente: 10 - 1 + 5 = 14');

    $early = app(BookingService::class)->create($data($room('EARLY-CHECKOUT'), '14:00', ['guests_count' => 1]));
    Carbon::setTestNow(Carbon::parse('2026-10-05 11:00'));
    app(BookingCheckInController::class)->store(Request::create('/checkin', 'POST'), $early->code, app(ConsumptionService::class), app(AvailabilityChecker::class), app(RateRuleResolver::class));
    verify($early->fresh()->checked_in_at !== null, 'llegada anticipada a las 11:00 aceptada');
    app(PaymentService::class)->register($early, $method, 20000, 'EARLY-PAY', null, null);
    Carbon::setTestNow(Carbon::parse('2026-10-05 12:00'));
    app(BookingFinalizeController::class)->store(Request::create('/finalizar', 'POST', ['confirm_room_checked' => '1']), $early->code, app(ConsumptionService::class));
    $early->refresh();
    verify($early->booking_status === 'FINALIZADA' && $early->ends_at->format('H:i') === '12:00', 'La salida anticipada guarda el fin real a las 12:00');
    verify(app(AvailabilityChecker::class)->isAvailable($early->room, Carbon::parse('2026-10-05 12:30'), Carbon::parse('2026-10-05 13:30')), 'El intervalo 12:30-13:30 queda disponible tras el margen de aseo');
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
