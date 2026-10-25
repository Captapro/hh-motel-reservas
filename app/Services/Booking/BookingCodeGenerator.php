<?php

namespace App\Services\Booking;

use App\Models\Booking;
use Carbon\Carbon;

class BookingCodeGenerator
{
    public function generate(Carbon $date): string
    {
        $prefix = 'HH-'.$date->format('Ymd').'-';

        // El siguiente número sale del mayor código del día, no de cuántas hay:
        // si se eliminó una reserva del medio, contar chocaba con una que ya existe.
        $last = Booking::where('code', 'like', $prefix.'%')->pluck('code')
            ->map(fn (string $code) => (int) substr($code, strlen($prefix)))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 5, '0', STR_PAD_LEFT);
    }
}
