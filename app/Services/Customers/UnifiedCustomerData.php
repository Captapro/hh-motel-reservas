<?php

namespace App\Services\Customers;

use App\Models\Booking;
use App\Models\HistoricalSale;
use App\Support\Rut;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Un solo historial de clientes: las planillas de recepción (antes del sistema) y las
 * reservas del sistema, como si fueran una misma fuente. La persona es su RUT (titular o
 * acompañante); un cliente del sistema sin RUT válido cuenta como persona propia.
 *
 * Para no contar dos veces una misma estadía, hasta el último día cargado de la planilla
 * manda la planilla y desde el día siguiente manda el sistema.
 */
class UnifiedCustomerData
{
    private const TZ = 'America/Santiago';

    private const EXCLUDED = ['CANCELADA', 'EXPIRADA', 'NO_SHOW'];

    /**
     * @return array{
     *   stays: Collection<int, array{key: string, at: Carbon, source: string, category: string, amount: int, guests: ?int, minutes: ?int, has_time: bool, extra_person: bool, extra_hour: bool, consumos: bool}>,
     *   visits: Collection<int, array{person: string, at: Carbon, stay: string, holder: bool, amount: int, name: ?string, customer_id: ?int, category: string}>,
     *   cutoff: ?Carbon
     * }
     */
    public function load(): array
    {
        $last = HistoricalSale::max('sold_on');
        $cutoff = $last ? Carbon::parse(Carbon::parse($last)->toDateString(), self::TZ)->endOfDay() : null;
        $stays = collect();
        $visits = collect();

        if ($cutoff) {
            $sales = HistoricalSale::with('guests')->orderBy('source')->orderBy('source_row')->get();
            $added = $this->attachToStays($sales);
            foreach ($sales->where('sale_type', 'ALOJAMIENTO') as $sale) {
                $time = $this->time($sale->starts_time);
                $at = Carbon::parse($sale->sold_on->toDateString().' '.($time ?? '00:00'), self::TZ);
                $key = 'p'.$sale->id;
                $category = $sale->categoryName() ?? 'Sin tipo';
                $extra = $added[$sale->id] ?? ['amount' => 0, 'persons' => 0, 'hour' => false, 'consumos' => false];
                $total = $sale->total() + $extra['amount'];
                // El goloso va en su propia fila y el «N° de personas» de la estadía casi siempre
                // queda en 2: cada goloso es una persona más sobre las 2 de base.
                $guests = $sale->guests_count ?: null;
                if ($extra['persons'] > 0) {
                    $guests = max($guests ?? 2, 2 + $extra['persons']);
                }
                $stays->push([
                    'key' => $key, 'at' => $at, 'source' => 'planilla', 'category' => $category, 'amount' => $total,
                    'guests' => $guests,
                    'minutes' => $sale->hours_paid ? $sale->hours_paid * 60 : ($sale->duration_minutes ?: null),
                    'has_time' => $time !== null,
                    'extra_person' => $extra['persons'] > 0, 'extra_hour' => $extra['hour'], 'consumos' => $extra['consumos'],
                ]);
                $seen = [];
                $paid = false;
                foreach ($sale->guests->sortByDesc('is_holder') as $guest) {
                    if ($guest->document_kind !== 'rut' || blank($guest->document) || isset($seen[$guest->document])) {
                        continue;
                    }
                    $seen[$guest->document] = true;
                    $holder = (bool) $guest->is_holder && ! $paid;
                    $paid = $paid || $holder;
                    $visits->push([
                        'person' => $guest->document, 'at' => $at, 'stay' => $key, 'holder' => $holder,
                        'amount' => $holder ? $total : 0, 'name' => $guest->name, 'customer_id' => null, 'category' => $category,
                    ]);
                }
            }
        }

        $bookings = Booking::with(['room.category', 'addons', 'customer', 'guests'])
            ->whereNotIn('booking_status', self::EXCLUDED)
            ->where('starts_at', '<=', now())
            ->when($cutoff, fn ($q) => $q->where('starts_at', '>', $cutoff->copy()))
            ->get();
        foreach ($bookings as $booking) {
            $at = $booking->starts_at->copy()->timezone(self::TZ);
            $key = 'b'.$booking->id;
            $category = $booking->room?->category?->name ?? 'Sin tipo';
            $amount = (int) ($booking->price_final + $booking->addons->sum('amount'));
            $hour = fn ($a) => str_contains(mb_strtolower((string) $a->description), 'hora extra') || str_contains(mb_strtolower((string) $a->description), 'hora adicional');
            $stays->push([
                'key' => $key, 'at' => $at, 'source' => 'sistema', 'category' => $category, 'amount' => $amount,
                'guests' => $booking->guests_count, 'minutes' => $booking->duration_minutes, 'has_time' => true,
                'extra_person' => (int) $booking->guests_count > 2,
                'extra_hour' => $booking->addons->contains($hour),
                'consumos' => $booking->addons->reject($hour)->isNotEmpty(),
            ]);
            $holderKey = $this->personKey($booking->customer?->rut, $booking->customer_id);
            $seen = [$holderKey => true];
            $visits->push([
                'person' => $holderKey, 'at' => $at, 'stay' => $key, 'holder' => true, 'amount' => $amount,
                'name' => $booking->customer?->name, 'customer_id' => $booking->customer_id, 'category' => $category,
            ]);
            foreach ($booking->guests as $guest) {
                $rut = $this->validRut($guest->id_document);
                if ($rut === null || isset($seen[$rut])) {
                    continue;
                }
                $seen[$rut] = true;
                $visits->push([
                    'person' => $rut, 'at' => $at, 'stay' => $key, 'holder' => false, 'amount' => 0,
                    'name' => $guest->name, 'customer_id' => null, 'category' => $category,
                ]);
            }
        }

        return ['stays' => $stays, 'visits' => $visits, 'cutoff' => $cutoff];
    }

    /**
     * En la planilla el goloso (persona adicional), la hora extra, los consumos y los pagos
     * fraccionados van en filas propias, debajo de la estadía a la que pertenecen. Cada una se
     * suma a la última estadía anterior de la misma planilla y el mismo día.
     *
     * @return array<int, array{amount: int, persons: int, hour: bool, consumos: bool}>
     */
    private function attachToStays(Collection $sales): array
    {
        $added = [];
        $stay = null;
        foreach ($sales as $sale) {
            if ($sale->sale_type === 'ALOJAMIENTO') {
                $stay = $sale;

                continue;
            }
            if ($stay === null || $stay->source !== $sale->source || ! $stay->sold_on->isSameDay($sale->sold_on)) {
                continue;
            }
            $row = $added[$stay->id] ?? ['amount' => 0, 'persons' => 0, 'hour' => false, 'consumos' => false];
            $row['amount'] += $sale->total();
            match ($sale->concept()) {
                'persona_adicional' => $row['persons'] += max(1, (int) $sale->quantity),
                'hora_extra' => $row['hour'] = true,
                'extras' => $row['consumos'] = true,
                default => null,
            };
            $added[$stay->id] = $row;
        }

        return $added;
    }

    /** La persona es su RUT válido; sin RUT, el cliente del sistema cuenta aparte. */
    private function personKey(?string $rut, ?int $customerId): string
    {
        return $this->validRut($rut) ?? 'c'.$customerId;
    }

    private function validRut(?string $raw): ?string
    {
        if (blank($raw) || ! Rut::isValid((string) $raw)) {
            return null;
        }

        return Rut::normalize((string) $raw);
    }

    /** «10:00», «9:05:00» → «10:00»; lo demás, null. */
    private function time(?string $raw): ?string
    {
        return preg_match('/^(\d{1,2}):(\d{2})/', trim((string) $raw), $m) && (int) $m[1] < 24
            ? sprintf('%02d:%s', $m[1], $m[2]) : null;
    }
}
