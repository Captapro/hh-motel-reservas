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
     *   stays: Collection<int, array{key: string, at: Carbon, source: string, category: string, amount: int, guests: ?int, minutes: ?int, has_time: bool}>,
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
            foreach (HistoricalSale::with('guests')->where('sale_type', 'ALOJAMIENTO')->get() as $sale) {
                $time = $this->time($sale->starts_time);
                $at = Carbon::parse($sale->sold_on->toDateString().' '.($time ?? '00:00'), self::TZ);
                $key = 'p'.$sale->id;
                $category = $sale->categoryName() ?? 'Sin tipo';
                $stays->push([
                    'key' => $key, 'at' => $at, 'source' => 'planilla', 'category' => $category, 'amount' => $sale->total(),
                    'guests' => $sale->guests_count ?: null,
                    'minutes' => $sale->hours_paid ? $sale->hours_paid * 60 : ($sale->duration_minutes ?: null),
                    'has_time' => $time !== null,
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
                        'amount' => $holder ? $sale->total() : 0, 'name' => $guest->name, 'customer_id' => null, 'category' => $category,
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
            $stays->push([
                'key' => $key, 'at' => $at, 'source' => 'sistema', 'category' => $category, 'amount' => $amount,
                'guests' => $booking->guests_count, 'minutes' => $booking->duration_minutes, 'has_time' => true,
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
