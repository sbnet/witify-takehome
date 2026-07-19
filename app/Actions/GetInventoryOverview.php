<?php

namespace App\Actions;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Support\Collection;

class GetInventoryOverview implements Action
{
    /**
     * The stock of every product in every warehouse, read from the
     * denormalized balances.
     *
     * @return array{
     *     warehouses: Collection<int, array{id: int, name: string, code: string}>,
     *     products: Collection<int, array{id: int, name: string, sku: string, quantities: Collection<int, int>}>,
     * }
     */
    public function handle(): array
    {
        $products = Product::query()
            ->with('inventoryBalances')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'quantities' => $product->inventoryBalances
                    ->mapWithKeys(fn (InventoryBalance $balance): array => [
                        $balance->warehouse_id => $balance->quantity,
                    ]),
            ]);

        $warehouses = Warehouse::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Warehouse $warehouse): array => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'code' => $warehouse->code,
            ]);

        return [
            'warehouses' => $warehouses,
            'products' => $products,
        ];
    }
}
