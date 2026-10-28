<?php

namespace App\Services\Shifts;

use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\Product;
use App\Models\ShiftHandover;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftHandoverService
{
    /** Productos que se cuentan en la entrega: activos y con inventario. */
    public function countableProducts(): Collection
    {
        return Product::where('is_active', true)->where('track_inventory', true)
            ->orderBy('display_order')->orderBy('name')->get();
    }

    /**
     * Guarda el cuadre. Lo contado pasa a ser la verdad: el stock queda en
     * lo contado y la diferencia de efectivo entra como ingreso o egreso de
     * caja, así el siguiente turno parte cuadrado. Si algo no coincide y no
     * hay nota, no se guarda nada: se le pide recontar o explicar.
     *
     * @param  array<int, int>  $counts  product_id => unidades contadas
     */
    public function record(User $user, string $kind, int $countedCash, array $counts, ?string $notes): ShiftHandover
    {
        return DB::transaction(function () use ($user, $kind, $countedCash, $counts, $notes) {
            $expectedCash = CashMovement::balance()['saldo'];
            $products = Product::whereIn('id', $this->countableProducts()->pluck('id'))->lockForUpdate()->get();

            $stockDiffs = $products->filter(fn (Product $p) => (int) ($counts[$p->id] ?? 0) !== (int) $p->stock);
            $hasDifferences = $countedCash !== $expectedCash || $stockDiffs->isNotEmpty();

            if ($hasDifferences && trim((string) $notes) === '') {
                throw ValidationException::withMessages([
                    'notes' => 'Lo que contaste no coincide con el sistema. Vuelve a contar y, si estás seguro, escribe una nota explicando qué pasó.',
                ]);
            }

            $handover = ShiftHandover::create([
                'user_id' => $user->id,
                'staff_shift_log_id' => $user->openShiftLog()?->id,
                'kind' => $kind,
                'expected_cash' => $expectedCash,
                'counted_cash' => $countedCash,
                'has_differences' => $hasDifferences,
                'notes' => trim((string) $notes) ?: null,
            ]);

            foreach ($products as $product) {
                $counted = (int) ($counts[$product->id] ?? 0);
                $handover->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'expected_stock' => (int) $product->stock,
                    'counted_stock' => $counted,
                ]);
                if ($counted !== (int) $product->stock) {
                    AuditLog::record($user->id, 'producto.cuadre_turno', 'Product', $product->id, ['stock' => $product->stock], ['stock' => $counted, 'entrega_turno' => $handover->id]);
                    $product->update(['stock' => $counted]);
                }
            }

            $cashDiff = $countedCash - $expectedCash;
            if ($cashDiff !== 0) {
                CashMovement::create([
                    'type' => $cashDiff > 0 ? 'ingreso' : 'egreso',
                    'amount' => abs($cashDiff),
                    'description' => 'Ajuste por cuadre de turno ('.($kind === 'recibir' ? 'al recibir' : 'al entregar').') — '.$user->name,
                    'created_by' => $user->id,
                ]);
            }

            return $handover;
        });
    }
}
