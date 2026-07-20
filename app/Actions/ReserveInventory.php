<?php

namespace App\Actions;

use App\Models\InventoryBalance;
use App\Models\InventoryReservation;
use App\Models\OrderLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReserveInventory implements Action
{
    /**
     * Reserve a quantity of inventory for an order line in a warehouse.
     *
     * Concurrent reservations are serialized with two row locks taken in a
     * constant order (order line, then balance) to avoid deadlocks. Locking
     * the balance row is the same lock AdjustInventory takes, so reservations
     * also serialize against stock adjustments: neither can overshoot based on
     * a stale read of the other. Both business rules are re-checked under the
     * locks before the reservation is inserted.
     *
     * @throws ValidationException when the quantity exceeds what remains to
     *                             reserve on the line or what is available in the warehouse
     */
    public function handle(int $orderLineId, int $warehouseId, int $quantity, ?int $userId = null): InventoryReservation
    {
        return DB::transaction(function () use ($orderLineId, $warehouseId, $quantity, $userId): InventoryReservation {
            $line = OrderLine::query()
                ->whereKey($orderLineId)
                ->lockForUpdate()
                ->firstOrFail();

            $reservedOnLine = (int) InventoryReservation::query()
                ->where('order_line_id', $line->id)
                ->sum('quantity');

            $remainingOnLine = $line->quantity_ordered - $reservedOnLine;

            if ($quantity > $remainingOnLine) {
                throw ValidationException::withMessages([
                    'quantity' => __('Only :remaining units remain to reserve on this line.', [
                        'remaining' => max(0, $remainingOnLine),
                    ]),
                ]);
            }

            $balance = InventoryBalance::query()
                ->where('product_id', $line->product_id)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->first();

            $reservedInWarehouse = (int) InventoryReservation::query()
                ->where('product_id', $line->product_id)
                ->where('warehouse_id', $warehouseId)
                ->sum('quantity');

            $available = ($balance?->quantity ?? 0) - $reservedInWarehouse;

            if ($quantity > $available) {
                throw ValidationException::withMessages([
                    'quantity' => __('Only :available units are available in this warehouse.', [
                        'available' => max(0, $available),
                    ]),
                ]);
            }

            return InventoryReservation::create([
                'order_line_id' => $line->id,
                'product_id' => $line->product_id,
                'warehouse_id' => $warehouseId,
                'user_id' => $userId,
                'quantity' => $quantity,
            ]);
        }, attempts: 3);
    }
}
