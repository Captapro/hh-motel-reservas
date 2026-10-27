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

    /**
     * @param  array<int|string, array<int|string, int|string>>  $choices  para combos a
     *                                                                     elección: [combo_item_id => [product_id => cantidad]] por el total vendido
     *                                                                     (ej. 2 packs de "2 bebidas a elegir" = 4 bebidas repartidas entre sabores).
     */
    public function addCombo(Booking $booking, Combo $combo, int $quantity, ?int $userId, array $choices = []): BookingAddon
    {
        return DB::transaction(function () use ($booking, $combo, $quantity, $userId, $choices) {
            // Lo que se descuenta realmente: [product_id => unidades].
            $needs = [];
            $picked = [];
            foreach ($combo->items()->with('product')->get() as $item) {
                $total = $item->quantity * $quantity;
                if (! $item->isChoice()) {
                    $needs[$item->product_id] = ($needs[$item->product_id] ?? 0) + $total;

                    continue;
                }
                $chosen = collect($choices[$item->id] ?? [])
                    ->mapWithKeys(fn ($count, $productId) => [(int) $productId => (int) $count])
                    ->filter(fn ($count) => $count > 0);
                $allowed = collect($item->optionIds());
                if ($chosen->sum() !== $total || $chosen->keys()->diff($allowed)->isNotEmpty()) {
                    throw new InsufficientStockException("{$combo->name}: elige {$total} entre las opciones del combo.");
                }
                foreach ($chosen as $productId => $count) {
                    $needs[$productId] = ($needs[$productId] ?? 0) + $count;
                    $picked[$productId] = ($picked[$productId] ?? 0) + $count;
                }
            }

            // Bloquear los productos en un orden estable (por id) para evitar
            // interbloqueos si dos combos comparten un producto.
            ksort($needs);
            $lockedProducts = [];
            foreach ($needs as $productId => $needed) {
                $locked = Product::lockForUpdate()->findOrFail($productId);
                $lockedProducts[$productId] = $locked;
                if ($locked->track_inventory && $locked->stock < $needed) {
                    $available = match (true) {
                        $locked->stock <= 0 => 'no queda stock',
                        $locked->stock === 1 => 'solo queda 1 unidad',
                        default => "solo quedan {$locked->stock} unidades",
                    };
                    throw new InsufficientStockException("{$combo->name} necesita {$locked->name} — {$available}, se necesitan {$needed}.");
                }
            }

            foreach ($needs as $productId => $needed) {
                if ($lockedProducts[$productId]->track_inventory) {
                    $lockedProducts[$productId]->decrement('stock', $needed);
                }
            }

            $description = $quantity > 1 ? "{$quantity} x {$combo->name}" : $combo->name;
            if ($picked) {
                $description .= ' ('.collect($picked)->map(fn ($count, $productId) => $count.' '.$lockedProducts[$productId]->name)->join(', ').')';
            }

            $addon = BookingAddon::create([
                'booking_id' => $booking->id,
                'combo_id' => $combo->id,
                'description' => $description,
                'quantity' => $quantity,
                'amount' => $combo->price * $quantity,
                'components' => collect($needs)->map(fn ($units, $productId) => ['product_id' => $productId, 'units' => $units])->values()->all(),
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
