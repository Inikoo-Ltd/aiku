<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Mcp\Servers\AikuServer;
use App\Mcp\Tools\ShopSalesTool;
use App\Models\Catalogue\ShopTimeSeries;
use App\Models\SysAdmin\Guest;

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

function shopDailyTimeSeries($shop): ShopTimeSeries
{
    return ShopTimeSeries::firstOrCreate([
        'shop_id'   => $shop->id,
        'frequency' => TimeSeriesFrequencyEnum::DAILY,
    ]);
}

test('user without orders permission is denied', function () {
    $guest = StoreGuest::make()->action(
        $this->group,
        array_merge(
            Guest::factory()->definition(),
            ['positions' => []]
        )
    );

    $response = AikuServer::actingAs($guest->getUser())->tool(ShopSalesTool::class, [
        'shop' => $this->shop->slug,
        'from' => '2026-01-01',
        'to'   => '2026-12-31',
    ]);

    $response->assertHasErrors(['Shop not found or permission denied.']);
});

test('admin user gets shop sales from time series', function () {
    $timeSeries = shopDailyTimeSeries($this->shop);
    $timeSeries->records()->updateOrCreate(
        ['period' => '2025-06-15', 'frequency' => TimeSeriesFrequencyEnum::DAILY->singleLetter()],
        [
            'from'               => '2025-06-15 00:00:00',
            'to'                 => '2025-06-15 23:59:59',
            'orders'             => 3,
            'invoices'           => 2,
            'sales_external'     => 150.50,
            'customers_invoiced' => 2,
        ]
    );

    $response = AikuServer::actingAs($this->user)->tool(ShopSalesTool::class, [
        'shop' => $this->shop->slug,
        'from' => '2025-06-01',
        'to'   => '2025-06-30',
    ]);

    $response->assertOk()
        ->assertSee('"number_orders":3')
        ->assertSee('150.5');
});

test('records outside the date range are excluded', function () {
    $timeSeries = shopDailyTimeSeries($this->shop);
    $timeSeries->records()->updateOrCreate(
        ['period' => '2025-03-15', 'frequency' => TimeSeriesFrequencyEnum::DAILY->singleLetter()],
        [
            'from'   => '2025-03-15 00:00:00',
            'to'     => '2025-03-15 23:59:59',
            'orders' => 7,
        ]
    );

    $response = AikuServer::actingAs($this->user)->tool(ShopSalesTool::class, [
        'shop' => $this->shop->slug,
        'from' => '2025-04-01',
        'to'   => '2025-04-30',
    ]);

    $response->assertOk()->assertSee('"number_orders":0');
});
