<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\OrderLine;

class GetOrderDetails implements Action
{
    /**
     * An order with its lines, ready for the order page.
     *
     * @return array<string, mixed>
     */
    public function handle(Order $order): array
    {
        $order->load(['lines.product']);

        $lines = $order->lines->map(fn (OrderLine $line): array => [
            'id' => $line->id,
            'product' => [
                'id' => $line->product->id,
                'name' => $line->product->name,
                'sku' => $line->product->sku,
            ],
            'quantity_ordered' => $line->quantity_ordered,
            // Reservations are not implemented yet: nothing is reserved,
            // so the full ordered quantity remains to be reserved.
            'quantity_reserved' => 0,
            'quantity_remaining' => $line->quantity_ordered,
            'unit_price' => (float) $line->unit_price,
            'line_total' => round($line->quantity_ordered * (float) $line->unit_price, 2),
        ]);

        return [
            'id' => $order->id,
            'number' => $order->number,
            'customer_name' => $order->customer_name,
            'status' => $order->status,
            'lines' => $lines,
            'total' => round($lines->sum('line_total'), 2),
        ];
    }
}
