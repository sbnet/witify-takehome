<?php

use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('inventory.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can view inventory per warehouse', function () {
    $this->actingAs(User::factory()->create());

    $balance = InventoryBalance::factory()->create(['quantity' => 42]);

    $response = $this->get(route('inventory.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('inventory/Index')
        ->has('warehouses', 1)
        ->has('products', 1)
        ->where("products.0.quantities.{$balance->warehouse_id}", 42)
    );
});

test('users can add stock to a warehouse', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $response = $this->post(route('inventory.adjustments.store'), [
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity_change' => 10,
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();

    $balance = InventoryBalance::sole();
    expect($balance->quantity)->toBe(10);

    $movement = InventoryMovement::sole();
    expect($movement->quantity_change)->toBe(10)
        ->and($movement->user_id)->toBe($user->id);
});

test('users can remove stock from a warehouse', function () {
    $this->actingAs(User::factory()->create());

    $movement = InventoryMovement::factory()->create(['quantity_change' => 10]);
    $balance = InventoryBalance::factory()->create([
        'product_id' => $movement->product_id,
        'warehouse_id' => $movement->warehouse_id,
        'quantity' => 10,
    ]);

    $response = $this->post(route('inventory.adjustments.store'), [
        'product_id' => $movement->product_id,
        'warehouse_id' => $movement->warehouse_id,
        'quantity_change' => -4,
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();

    expect($balance->refresh()->quantity)->toBe(6)
        ->and(InventoryMovement::count())->toBe(2);
});

test('stock cannot be adjusted below zero', function () {
    $this->actingAs(User::factory()->create());

    $movement = InventoryMovement::factory()->create(['quantity_change' => 3]);
    $balance = InventoryBalance::factory()->create([
        'product_id' => $movement->product_id,
        'warehouse_id' => $movement->warehouse_id,
        'quantity' => 3,
    ]);

    $response = $this->from(route('inventory.index'))->post(route('inventory.adjustments.store'), [
        'product_id' => $movement->product_id,
        'warehouse_id' => $movement->warehouse_id,
        'quantity_change' => -4,
    ]);

    $response->assertSessionHasErrors('quantity_change');

    expect($balance->refresh()->quantity)->toBe(3)
        ->and(InventoryMovement::count())->toBe(1);
});

test('an adjustment resynchronizes a drifted balance from the journal', function () {
    $this->actingAs(User::factory()->create());

    $movement = InventoryMovement::factory()->create(['quantity_change' => 20]);

    // A balance that no longer matches its journal (e.g. written by a bug).
    InventoryBalance::factory()->create([
        'product_id' => $movement->product_id,
        'warehouse_id' => $movement->warehouse_id,
        'quantity' => 99,
    ]);

    $this->post(route('inventory.adjustments.store'), [
        'product_id' => $movement->product_id,
        'warehouse_id' => $movement->warehouse_id,
        'quantity_change' => 5,
    ])->assertSessionHasNoErrors();

    expect(InventoryBalance::sole()->quantity)->toBe(25);
});

test('the balance always equals the sum of the movement journal', function () {
    $this->actingAs(User::factory()->create());

    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    foreach ([10, 25, -8] as $quantityChange) {
        $this->post(route('inventory.adjustments.store'), [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity_change' => $quantityChange,
        ])->assertSessionHasNoErrors();
    }

    $balance = InventoryBalance::sole();

    expect($balance->quantity)->toBe(27)
        ->and(InventoryMovement::currentStock($product->id, $warehouse->id))->toBe(27)
        ->and(InventoryMovement::count())->toBe(3);
});

test('the adjustment quantity must not be zero', function (int|string $quantityChange) {
    $this->actingAs(User::factory()->create());

    $balance = InventoryBalance::factory()->create(['quantity' => 10]);

    $response = $this->post(route('inventory.adjustments.store'), [
        'product_id' => $balance->product_id,
        'warehouse_id' => $balance->warehouse_id,
        'quantity_change' => $quantityChange,
    ]);

    $response->assertSessionHasErrors('quantity_change');
})->with([
    'zero' => 0,
    'not a number' => 'abc',
]);
