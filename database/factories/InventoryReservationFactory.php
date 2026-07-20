<?php

namespace Database\Factories;

use App\Models\InventoryReservation;
use App\Models\OrderLine;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryReservation>
 */
class InventoryReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_line_id' => OrderLine::factory(),
            'product_id' => fn (array $attributes): int => OrderLine::query()
                ->findOrFail($attributes['order_line_id'])
                ->product_id,
            'warehouse_id' => Warehouse::factory(),
            'user_id' => null,
            'quantity' => fake()->numberBetween(1, 20),
        ];
    }
}
