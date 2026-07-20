<?php

use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\InventoryReservation;
use App\Models\OrderLine;
use App\Models\User;
use App\Models\Warehouse;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->post(route('reservations.store'));
    $response->assertRedirect(route('login'));
});

test('users can reserve inventory for an order line', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $line = OrderLine::factory()->create(['quantity_ordered' => 10]);
    $balance = InventoryBalance::factory()->create([
        'product_id' => $line->product_id,
        'quantity' => 10,
    ]);

    $response = $this->post(route('reservations.store'), [
        'order_line_id' => $line->id,
        'warehouse_id' => $balance->warehouse_id,
        'quantity' => 4,
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();

    $reservation = InventoryReservation::sole();
    expect($reservation->order_line_id)->toBe($line->id)
        ->and($reservation->product_id)->toBe($line->product_id)
        ->and($reservation->warehouse_id)->toBe($balance->warehouse_id)
        ->and($reservation->quantity)->toBe(4)
        ->and($reservation->user_id)->toBe($user->id);
});

test('a reservation touches neither the balance nor the movement journal', function () {
    $this->actingAs(User::factory()->create());

    $line = OrderLine::factory()->create(['quantity_ordered' => 10]);
    $balance = InventoryBalance::factory()->create([
        'product_id' => $line->product_id,
        'quantity' => 10,
    ]);

    $this->post(route('reservations.store'), [
        'order_line_id' => $line->id,
        'warehouse_id' => $balance->warehouse_id,
        'quantity' => 4,
    ])->assertSessionHasNoErrors();

    expect($balance->refresh()->quantity)->toBe(10)
        ->and(InventoryMovement::count())->toBe(0);
});

test('successive reservations accumulate on the same line', function () {
    $this->actingAs(User::factory()->create());

    $line = OrderLine::factory()->create(['quantity_ordered' => 10]);
    $balance = InventoryBalance::factory()->create([
        'product_id' => $line->product_id,
        'quantity' => 10,
    ]);

    foreach ([4, 3] as $quantity) {
        $this->post(route('reservations.store'), [
            'order_line_id' => $line->id,
            'warehouse_id' => $balance->warehouse_id,
            'quantity' => $quantity,
        ])->assertSessionHasNoErrors();
    }

    expect(InventoryReservation::count())->toBe(2)
        ->and((int) InventoryReservation::sum('quantity'))->toBe(7);
});

test('a reservation cannot exceed the available inventory in the warehouse', function () {
    $this->actingAs(User::factory()->create());

    $line = OrderLine::factory()->create(['quantity_ordered' => 10]);
    $balance = InventoryBalance::factory()->create([
        'product_id' => $line->product_id,
        'quantity' => 3,
    ]);

    $response = $this->from(route('orders.show', $line->order_id))
        ->post(route('reservations.store'), [
            'order_line_id' => $line->id,
            'warehouse_id' => $balance->warehouse_id,
            'quantity' => 5,
        ]);

    $response->assertSessionHasErrors('quantity');

    expect(InventoryReservation::count())->toBe(0);
});

test('a reservation cannot exceed the quantity remaining on the line', function () {
    $this->actingAs(User::factory()->create());

    $line = OrderLine::factory()->create(['quantity_ordered' => 5]);
    $balance = InventoryBalance::factory()->create([
        'product_id' => $line->product_id,
        'quantity' => 100,
    ]);

    InventoryReservation::factory()->create([
        'order_line_id' => $line->id,
        'warehouse_id' => $balance->warehouse_id,
        'quantity' => 3,
    ]);

    $response = $this->post(route('reservations.store'), [
        'order_line_id' => $line->id,
        'warehouse_id' => $balance->warehouse_id,
        'quantity' => 3,
    ]);

    $response->assertSessionHasErrors('quantity');

    expect(InventoryReservation::count())->toBe(1);
});

test('availability accounts for reservations made by other orders', function () {
    $this->actingAs(User::factory()->create());

    $line = OrderLine::factory()->create(['quantity_ordered' => 10]);
    $balance = InventoryBalance::factory()->create([
        'product_id' => $line->product_id,
        'quantity' => 10,
    ]);

    // Another order line of the same product already reserved 6 units there.
    $otherLine = OrderLine::factory()->create([
        'product_id' => $line->product_id,
        'quantity_ordered' => 10,
    ]);
    InventoryReservation::factory()->create([
        'order_line_id' => $otherLine->id,
        'warehouse_id' => $balance->warehouse_id,
        'quantity' => 6,
    ]);

    $response = $this->post(route('reservations.store'), [
        'order_line_id' => $line->id,
        'warehouse_id' => $balance->warehouse_id,
        'quantity' => 5,
    ]);

    $response->assertSessionHasErrors('quantity');

    expect(InventoryReservation::count())->toBe(1);
});

test('nothing can be reserved in a warehouse without stock for the product', function () {
    $this->actingAs(User::factory()->create());

    $line = OrderLine::factory()->create(['quantity_ordered' => 10]);
    $warehouse = Warehouse::factory()->create();

    $response = $this->post(route('reservations.store'), [
        'order_line_id' => $line->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 1,
    ]);

    $response->assertSessionHasErrors('quantity');

    expect(InventoryReservation::count())->toBe(0);
});

test('the reservation quantity must be a positive integer', function (int|string $quantity) {
    $this->actingAs(User::factory()->create());

    $line = OrderLine::factory()->create(['quantity_ordered' => 10]);
    $balance = InventoryBalance::factory()->create([
        'product_id' => $line->product_id,
        'quantity' => 10,
    ]);

    $response = $this->post(route('reservations.store'), [
        'order_line_id' => $line->id,
        'warehouse_id' => $balance->warehouse_id,
        'quantity' => $quantity,
    ]);

    $response->assertSessionHasErrors('quantity');

    expect(InventoryReservation::count())->toBe(0);
})->with([
    'zero' => 0,
    'negative' => -3,
    'not a number' => 'abc',
]);

test('the order page shows reserved, remaining and available quantities', function () {
    $this->actingAs(User::factory()->create());

    $line = OrderLine::factory()->create(['quantity_ordered' => 10]);
    $balance = InventoryBalance::factory()->create([
        'product_id' => $line->product_id,
        'quantity' => 10,
    ]);
    $emptyWarehouse = Warehouse::factory()->create();

    InventoryReservation::factory()->create([
        'order_line_id' => $line->id,
        'warehouse_id' => $balance->warehouse_id,
        'quantity' => 4,
    ]);

    $response = $this->get(route('orders.show', $line->order_id));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('orders/Show')
        ->has('warehouses', 2)
        ->where('order.lines.0.quantity_reserved', 4)
        ->where('order.lines.0.quantity_remaining', 6)
        ->where("order.lines.0.availability.{$balance->warehouse_id}", 6)
        ->missing("order.lines.0.availability.{$emptyWarehouse->id}")
    );
});
