<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Ordering\Order\DeleteOrder;
use App\Actions\Ordering\Order\StoreOrder;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    $this->customer = createCustomer($this->shop);

    actingAs($this->user);
});

test('deleting an order leaves the addresses the customer still holds', function () {
    $order = StoreOrder::make()->action($this->customer, []);

    $sharedAddressIds = DB::table('model_has_fixed_addresses')
        ->where('model_type', 'Order')->where('model_id', $order->id)
        ->pluck('address_id')
        ->filter(fn ($addressId) => DB::table('model_has_fixed_addresses')
            ->where('address_id', $addressId)
            ->where(fn ($query) => $query->where('model_type', '!=', 'Order')->orWhere('model_id', '!=', $order->id))
            ->exists())
        ->all();

    $ownAddressIds = collect([$order->billing_address_id, $order->delivery_address_id])
        ->filter()
        ->reject(fn ($addressId) => in_array($addressId, $sharedAddressIds))
        ->all();

    DeleteOrder::make()->handle($order);

    expect(Order::find($order->id))->toBeNull();

    /** Anything another model still holds must survive; this is what the FK was catching. */
    foreach ($sharedAddressIds as $addressId) {
        expect(Address::find($addressId))->not->toBeNull("shared address $addressId was deleted with the order");
    }

    /** Whatever was only the order's is collected, not left behind. */
    foreach ($ownAddressIds as $addressId) {
        expect(Address::find($addressId))->toBeNull("address $addressId was only the order's and should be gone");
    }
});

test('an address held by another model is not collected', function () {
    $order   = StoreOrder::make()->action($this->customer, []);
    $address = Address::find($order->delivery_address_id);

    expect($address)->not->toBeNull();

    DB::table('model_has_fixed_addresses')->insert([
        'group_id'   => $this->customer->group_id,
        'address_id' => $address->id,
        'model_type' => 'Customer',
        'model_id'   => $this->customer->id,
        'scope'      => 'delivery',
    ]);

    DeleteOrder::make()->handle($order);

    expect(Address::find($address->id))->not->toBeNull()
        ->and(DB::table('model_has_fixed_addresses')->where('address_id', $address->id)->count())->toBe(1);
});
