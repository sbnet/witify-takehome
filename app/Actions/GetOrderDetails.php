<?php

namespace App\Actions;

use App\Models\InventoryBalance;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Warehouse;
use Illuminate\Support\Collection;

class GetOrderDetails implements Action
{
    /**
     * An order with its lines and per-warehouse availability, ready for the
     * order page.
     *
     * Reserved and available quantities are computed with a constant number
     * of queries (grouped sums), regardless of how many lines or warehouses
     * exist. Available = physical balance - sum of reservations for the
     * product/warehouse pair; warehouses without a balance are simply absent
     * from the availability map (the UI treats them as zero).
     *
     * @return array{
     *     order: array<string, mixed>,
     *     warehouses: Collection<int, array{id: int, name: string, code: string}>,
     * }
     */
    public function handle(Order $order): array
    {
        $order->load(['lines.product']);

        $lineIds = $order->lines->pluck('id');
        $productIds = $order->lines->pluck('product_id')->unique();

        $reservedByLine = InventoryReservation::query()
            ->whereIn('order_line_id', $lineIds)
            ->groupBy('order_line_id')
            ->selectRaw('order_line_id, SUM(quantity) as reserved')
            ->pluck('reserved', 'order_line_id');

        $reservedByPair = InventoryReservation::query()
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id', 'warehouse_id')
            ->selectRaw('product_id, warehouse_id, SUM(quantity) as reserved')
            ->get()
            ->keyBy(fn ($row): string => "{$row->product_id}-{$row->warehouse_id}");

        $availabilityByProduct = InventoryBalance::query()
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'warehouse_id', 'quantity'])
            ->groupBy('product_id')
            ->map(fn (Collection $balances): Collection => $balances
                ->mapWithKeys(fn (InventoryBalance $balance): array => [
                    $balance->warehouse_id => max(
                        0,
                        $balance->quantity - (int) ($reservedByPair["{$balance->product_id}-{$balance->warehouse_id}"]->reserved ?? 0),
                    ),
                ]));

        $lines = $order->lines->map(function (OrderLine $line) use ($reservedByLine, $availabilityByProduct): array {
            $reserved = (int) ($reservedByLine[$line->id] ?? 0);

            return [
                'id' => $line->id,
                'product' => [
                    'id' => $line->product->id,
                    'name' => $line->product->name,
                    'sku' => $line->product->sku,
                ],
                'quantity_ordered' => $line->quantity_ordered,
                'quantity_reserved' => $reserved,
                'quantity_remaining' => $line->quantity_ordered - $reserved,
                'availability' => $availabilityByProduct[$line->product_id] ?? collect(),
                'unit_price' => (float) $line->unit_price,
                'line_total' => round($line->quantity_ordered * (float) $line->unit_price, 2),
            ];
        });

        $warehouses = Warehouse::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Warehouse $warehouse): array => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'code' => $warehouse->code,
            ]);

        return [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'customer_name' => $order->customer_name,
                'status' => $order->status,
                'lines' => $lines,
                'total' => round($lines->sum('line_total'), 2),
            ],
            'warehouses' => $warehouses,
        ];
    }
}
