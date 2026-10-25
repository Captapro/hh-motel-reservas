<?php

namespace App\Services\Booking;

use App\Exceptions\InsufficientStockException;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingAddon;
use App\Models\Combo;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ConsumptionService
{
    public function __construct(private PaymentService $paymentService) {}

    public function addProduct(Booking $booking, Product $product, int $quantity, ?int $userId): BookingAddon
    {
        return DB::transaction(function () use ($booking, $product, $quantity, $userId) {
            // Bloqueo de fila: si dos recepcionistas descuentan el mismo producto
            // a la vez, la segunda transacción espera y ve el stock ya actualizado
            // — misma lógica que la reserva de habitaciones, a menor escala.
            $locked = Product::lockForUpdate()->findOrFail($product->id);

            if ($locked->track_inventory && $locked->stock < $quantity) {
                $available = match (true) {
                    $locked->stock <= 0 => 'no queda stock',
                    $locked->stock === 1 => 'solo queda 1 unidad',
                    default => "solo quedan {$locked->stock} unidades",
                };
                throw new InsufficientStockException("{$locked->name}: {$available}, pediste {$quantity}.");
            }

            if ($locked->track_inventory) {
                $locked->decrement('stock', $quantity);
            }

            $addon = BookingAddon::create([
                'booking_id' => $booking->id,
                'product_id' => $product->id,
                'description' => $quantity > 1 ? "{$quantity} x {$product->name}" : $product->name,
                'quantity' => $quantity,
                'amount' => $product->price * $quantity,
                'added_by' => $userId,
            ]);

            $this->afterAdd($booking, $addon, $userId);

            return $addon;
        });
    }

    public function addCombo(Booking $booking, Combo $combo, int $quantity, ?int $userId): BookingAddon
    {
        return DB::transaction(function () use ($booking, $combo, $quantity, $userId) {
            // Bloquear los productos componentes en un orden estable (por id)
            // para evitar interbloqueos si dos combos comparten un producto.
            $items = $combo->items()->with('product')->orderBy('product_id')->get();
            $lockedProducts = [];

            foreach ($items as $item) {
                $locked = Product::lockForUpdate()->findOrFail($item->product_id);
                $lockedProducts[$item->id] = $locked;

                $needed = $item->quantity * $quantity;
                if ($locked->track_inventory && $locked->stock < $needed) {
                    $available = match (true) {
                        $locked->stock <= 0 => 'no queda stock',
                        $locked->stock === 1 => 'solo queda 1 unidad',
                        default => "solo quedan {$locked->stock} unidades",
                    };
                    throw new InsufficientStockException("{$combo->name} necesita {$locked->name} — {$available}, se necesitan {$needed}.");
                }
            }

            foreach ($items as $item) {
                $locked = $lockedProducts[$item->id];
                if ($locked->track_inventory) {
                    $locked->decrement('stock', $item->quantity * $quantity);
                }
            }

            $addon = BookingAddon::create([
                'booking_id' => $booking->id,
                'combo_id' => $combo->id,
                'description' => $quantity > 1 ? "{$quantity} x {$combo->name}" : $combo->name,
                'quantity' => $quantity,
                'amount' => $combo->price * $quantity,
                'added_by' => $userId,
            ]);

            $this->afterAdd($booking, $addon, $userId);

            return $addon;
        });
    }

    public function addCustom(Booking $booking, string $description, int $amount, ?int $userId): BookingAddon
    {
        $addon = BookingAddon::create([
            'booking_id' => $booking->id,
            'product_id' => null,
            'description' => $description,
            'quantity' => 1,
            'amount' => $amount,
            'added_by' => $userId,
        ]);

        $this->afterAdd($booking, $addon, $userId);

        return $addon;
    }

    private function afterAdd(Booking $booking, BookingAddon $addon, ?int $userId): void
    {
        AuditLog::record($userId, 'reserva.consumo_agregar', 'BookingAddon', $addon->id, null, $addon->toArray());
        $this->paymentService->recalculateStatus($booking->fresh());
    }
}
