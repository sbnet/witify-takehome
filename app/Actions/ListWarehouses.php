<?php

namespace App\Actions;

use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class ListWarehouses implements Action
{
    /**
     * All warehouses with how many products they hold and the total units in
     * stock, computed from the denormalized balances in a single aggregated
     * query.
     *
     * @return array<int, array{id: int, name: string, code: string, products_in_stock: int, units_in_stock: int}>
     */
    public function handle(): array
    {
        $totals = DB::table('inventory_balances')
            ->selectRaw('warehouse_id, SUM(quantity) as units_in_stock')
            ->selectRaw('SUM(CASE WHEN quantity > 0 THEN 1 ELSE 0 END) as products_in_stock')
            ->groupBy('warehouse_id')
            ->get()
            ->keyBy('warehouse_id');

        return Warehouse::query()
            ->orderBy('name')
            ->get()
            ->map(function (Warehouse $warehouse) use ($totals): array {
                $total = $totals->get($warehouse->id);

                return [
                    'id' => $warehouse->id,
                    'name' => $warehouse->name,
                    'code' => $warehouse->code,
                    'products_in_stock' => (int) ($total->products_in_stock ?? 0),
                    'units_in_stock' => (int) ($total->units_in_stock ?? 0),
                ];
            })
            ->all();
    }
}
