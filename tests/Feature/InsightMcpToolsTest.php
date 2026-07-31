<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Mcp\Servers\AikuServer;
use App\Mcp\Tools\CustomerConversionTool;
use App\Mcp\Tools\MarginTrendTool;
use App\Mcp\Tools\OrderFunnelTool;
use App\Mcp\Tools\RefundsByProductTool;
use App\Mcp\Tools\SlowStockTool;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Guest;

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

test('guest without permissions is denied on every insight tool', function (string $tool) {
    $guest = StoreGuest::make()->action(
        $this->group,
        array_merge(
            Guest::factory()->definition(),
            ['positions' => []]
        )
    );

    $response = AikuServer::actingAs($guest->getUser())->tool($tool, [
        'shop' => $this->shop->slug,
        'from' => '2026-01-01',
        'to'   => '2026-12-31',
    ]);

    $response->assertHasErrors(['You do not have access to any shop.']);
})->with([
    SlowStockTool::class,
    OrderFunnelTool::class,
    CustomerConversionTool::class,
    RefundsByProductTool::class,
    MarginTrendTool::class,
]);

test('admin can run every insight tool', function (string $tool) {
    $response = AikuServer::actingAs($this->user)->tool($tool, [
        'shop' => $this->shop->slug,
        'from' => '2026-01-01',
        'to'   => '2026-12-31',
    ]);

    $response->assertOk();
})->with([
    SlowStockTool::class,
    OrderFunnelTool::class,
    CustomerConversionTool::class,
    RefundsByProductTool::class,
    MarginTrendTool::class,
]);

test('order funnel counts an abandoned basket', function () {
    $billingAddress  = new Address(Address::factory()->definition());
    $deliveryAddress = new Address(Address::factory()->definition());

    $modelData = Order::factory()->definition();
    data_set($modelData, 'billing_address', $billingAddress);
    data_set($modelData, 'delivery_address', $deliveryAddress);

    $order = StoreOrder::make()->action($this->customer, $modelData);
    $order->update([
        'state'      => OrderStateEnum::CREATING,
        'net_amount' => 99.50,
        'date'       => '2026-06-15',
    ]);

    $response = AikuServer::actingAs($this->user)->tool(OrderFunnelTool::class, [
        'shop' => $this->shop->slug,
        'from' => '2026-06-01',
        'to'   => '2026-06-30',
    ]);

    $response->assertOk()
        ->assertSee('"abandoned_baskets":1')
        ->assertSee('99.5');
});
