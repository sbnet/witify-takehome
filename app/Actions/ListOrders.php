<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\OrderLine;

class ListOrders implements Action
{
    /**
     * All orders with their line count and total, computed in a single query.
     *
     * @return array<int, array{id: int, number: string, customer_name: string, status: string, lines_count: int|null, total: float}>
     */
    public function handle(): array
    {
        return Order::query()
            ->withCount('lines')
            ->addSelect([
                'total' => OrderLine::query()
                    ->selectRaw('COALESCE(SUM(quantity_ordered * unit_price), 0)')
                    ->whereColumn('order_id', 'orders.id'),
            ])
            ->latest()
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'number' => $order->number,
                'customer_name' => $order->customer_name,
                'status' => $order->status,
                'lines_count' => $order->lines_count,
                'total' => (float) $order->total,
            ])
            ->all();
    }
}
