<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Ordering\Order\StoreOrder;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Mcp\Servers\AikuServer;
use App\Mcp\Tools\TopProductsTool;
use App\Mcp\Tools\OrderStatusTool;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Guest;
use App\Actions\SysAdmin\Guest\StoreGuest;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    $this->group = $this->organisation->group;
    $this->customer = createCustomer($this->shop);

    app()->instance('group', $this->group);
    setPermissionsTeamId($this->group->id);
});

test('user without products permission is denied', function () {
    $guest = StoreGuest::make()->action(
        $this->group,
        array_merge(
            Guest::factory()->definition(),
            ['positions' => []]
        )
    );

    $response = AikuServer::actingAs($guest->getUser())->tool(TopProductsTool::class, [
        'shop' => $this->shop->slug,
        'from' => '2026-01-01',
        'to'   => '2026-12-31',
    ]);

    $response->assertHasErrors(['Shop not found or permission denied.']);
});

test('admin gets top products', function () {
    $response = AikuServer::actingAs($this->user)->tool(TopProductsTool::class, [
        'shop'  => $this->shop->slug,
        'from'  => '2026-01-01',
        'to'    => '2026-12-31',
        'limit' => 10,
    ]);

    $response->assertOk();
});

test('order status by reference', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', $billingAddress);
    data_set($modelData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action($this->customer, $modelData);
    $order->update([
        'state'      => OrderStateEnum::SUBMITTED,
        'net_amount' => 150.50,
        'date'       => '2026-06-15',
    ]);

    $response = AikuServer::actingAs($this->user)->tool(OrderStatusTool::class, [
        'shop'      => $this->shop->slug,
        'reference' => $order->reference,
    ]);

    $response->assertOk()
        ->assertSee($order->reference);
});

test('order status for unknown reference errors', function () {
    $response = AikuServer::actingAs($this->user)->tool(OrderStatusTool::class, [
        'shop'      => $this->shop->slug,
        'reference' => 'nope-999',
    ]);

    $response->assertHasErrors(['Order not found.']);
});
