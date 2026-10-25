<?php

namespace App\Services\Booking;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Una fila por cada salida real (checked_out_at) — el evento que manda la
 * habitación a aseo. El aseo se cierra solo cuando recepción aprieta "Marcar
 * aseo listo" (registrado como habitacion.aseo_listo en el audit log); si
 * todavía no lo hizo, la fila queda "pendiente". Compartido entre el resumen
 * diario, semanal y mensual.
 */
class AseoReportService
{
    /**
     * @return Collection<int, array{booking: Booking, room: Room, checkout_at: Carbon, ready_at: ?Carbon, pending: bool, cleaned_by: ?string}>
     */
    public function rowsBetween(Carbon $start, Carbon $end): Collection
    {
        $checkouts = Booking::with(['room.category', 'customer'])
            ->whereNotNull('checked_out_at')
            ->whereBetween('checked_out_at', [$start, $end])
            ->orderBy('checked_out_at')
            ->get();

        // "Aseo listo" de cada habitación, desde el inicio del rango en
        // adelante — el cierre puede caer días después del check-out.
        $readyLogsByRoom = AuditLog::where('action', 'habitacion.aseo_listo')
            ->where('entity_type', 'Room')
            ->where('created_at', '>=', $start)
            ->orderBy('created_at')
            ->get()
            ->groupBy('entity_id');

        // Para acotar cada aseo a su check-out: el siguiente check-out de la
        // misma habitación cierra la ventana del anterior.
        $nextCheckoutByBooking = [];
        foreach ($checkouts->groupBy('room_id') as $roomBookings) {
            $ordered = $roomBookings->sortBy('checked_out_at')->values();
            foreach ($ordered as $i => $b) {
                $nextCheckoutByBooking[$b->id] = $ordered->get($i + 1)?->checked_out_at;
            }
        }

        return $checkouts->map(function (Booking $booking) use ($readyLogsByRoom, $nextCheckoutByBooking) {
            $checkoutAt = $booking->checked_out_at;
            $windowEnd = $nextCheckoutByBooking[$booking->id] ?? null;

            $readyLog = ($readyLogsByRoom->get($booking->room_id) ?? collect())
                ->first(fn (AuditLog $log) => $log->created_at->gte($checkoutAt)
                    && ($windowEnd === null || $log->created_at->lt($windowEnd)));

            return [
                'booking' => $booking,
                'room' => $booking->room,
                'checkout_at' => $checkoutAt,
                'ready_at' => $readyLog?->created_at,
                'pending' => $readyLog === null,
                // Quién hizo el aseo -- va dentro del propio audit log de
                // "aseo listo" (new_value.cleaned_by), sin tabla aparte.
                'cleaned_by' => $readyLog?->new_value['cleaned_by'] ?? null,
            ];
        })->sortBy('checkout_at')->values();
    }

    /**
     * Filas que suman a la carga de ropa de cama — todo menos GO, que son
     * cápsulas sin cama.
     *
     * @param  Collection<int, array{room: Room}>  $rows
     * @return Collection<int, array{room: Room}>
     */
    public function linenRows(Collection $rows): Collection
    {
        return $rows->reject(fn (array $row) => $row['room']->category->name === 'GO');
    }
}
