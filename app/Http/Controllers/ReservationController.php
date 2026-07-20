<?php

namespace App\Http\Controllers;

use App\Actions\ReserveInventory;
use App\Http\Requests\StoreReservationRequest;
use Illuminate\Http\RedirectResponse;

class ReservationController extends Controller
{
    public function store(StoreReservationRequest $request, ReserveInventory $reserveInventory): RedirectResponse
    {
        $validated = $request->validated();

        $reserveInventory->handle(
            orderLineId: (int) $validated['order_line_id'],
            warehouseId: (int) $validated['warehouse_id'],
            quantity: (int) $validated['quantity'],
            userId: $request->user()?->id,
        );

        return back();
    }
}
