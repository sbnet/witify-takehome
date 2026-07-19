<?php

namespace App\Actions;

use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdjustInventory implements Action
{
    /**
     * Adjust the stock of a product in a warehouse.
     *
     * The movement journal is the source of truth. Adjustments for the same
     * product and warehouse are serialized by locking their balance before a
     * movement is recorded. The resulting quantity is then recomputed from the
     * journal and projected onto the balance in the same transaction.
     *
     * @throws ValidationException when the adjustment would make the stock negative
     */
    public function handle(int $productId, int $warehouseId, int $quantityChange, ?int $userId = null): InventoryMovement
    {
        return DB::transaction(function () use ($productId, $warehouseId, $quantityChange, $userId): InventoryMovement {
            InventoryBalance::query()->upsert(
                [
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => 0,
                ],
                uniqueBy: ['product_id', 'warehouse_id'],
                update: ['updated_at'],
            );

            $balance = InventoryBalance::query()
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->firstOrFail();

            $movement = InventoryMovement::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'user_id' => $userId,
                'quantity_change' => $quantityChange,
            ]);

            $newQuantity = InventoryMovement::currentStock($productId, $warehouseId);

            if ($newQuantity < 0) {
                throw ValidationException::withMessages([
                    'quantity_change' => __('Only :quantity units are in stock in this warehouse.', [
                        'quantity' => $newQuantity - $quantityChange,
                    ]),
                ]);
            }

            $balance->update(['quantity' => $newQuantity]);

            return $movement;
        }, attempts: 3);
    }
}
