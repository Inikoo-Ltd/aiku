<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Mcp\Servers\AikuServer;
use App\Mcp\Tools\GroupSalesTool;
use App\Mcp\Tools\TradeUnitFamilySalesTool;
use App\Mcp\Tools\TradeUnitSalesTool;
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

    app()->instance('group', $this->group);
    setPermissionsTeamId($this->group->id);
});

test('user without group reports permission is denied', function () {
    $guest = StoreGuest::make()->action(
        $this->group,
        array_merge(
            Guest::factory()->definition(),
            ['positions' => []]
        )
    );

    $response = AikuServer::actingAs($guest->getUser())->tool(GroupSalesTool::class, [
        'from' => '2026-01-01',
        'to'   => '2026-12-31',
    ]);

    $response->assertHasErrors(['Permission denied.']);
});

test('admin gets group wide sales', function () {
    $response = AikuServer::actingAs($this->user)->tool(GroupSalesTool::class, [
        'from' => '2026-01-01',
        'to'   => '2026-12-31',
    ]);

    $response->assertOk()
        ->assertSee('"net_sales"')
        ->assertSee('"customers_invoiced"');
});

test('admin gets trade unit family sales', function () {
    $response = AikuServer::actingAs($this->user)->tool(TradeUnitFamilySalesTool::class, [
        'from' => '2026-01-01',
        'to'   => '2026-12-31',
        'sort' => 'worst',
    ]);

    $response->assertOk()->assertSee('"sort":"worst"');
});

test('admin gets trade unit sales', function () {
    $response = AikuServer::actingAs($this->user)->tool(TradeUnitSalesTool::class, [
        'from' => '2026-01-01',
        'to'   => '2026-12-31',
    ]);

    $response->assertOk()->assertSee('"trade_units"');
});
