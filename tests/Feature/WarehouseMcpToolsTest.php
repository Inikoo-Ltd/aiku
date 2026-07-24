<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Mcp\Servers\AikuServer;
use App\Mcp\Tools\StockLevelsTool;
use App\Mcp\Tools\DeliveryNotesSummaryTool;
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
    $this->warehouse = createWarehouse();

    app()->instance('group', $this->group);
    setPermissionsTeamId($this->group->id);
});

test('user without stocks permission is denied', function () {
    $guest = StoreGuest::make()->action(
        $this->group,
        array_merge(
            Guest::factory()->definition(),
            ['positions' => []]
        )
    );

    $response = AikuServer::actingAs($guest->getUser())->tool(StockLevelsTool::class, [
        'warehouse' => $this->warehouse->slug,
        'query'     => 'x',
    ]);

    $response->assertHasErrors(['Warehouse not found or permission denied.']);
});

test('admin user can search stock levels', function () {
    $response = AikuServer::actingAs($this->user)->tool(StockLevelsTool::class, [
        'warehouse' => $this->warehouse->slug,
        'query'     => 'anything',
    ]);

    $response->assertOk();
});

test('admin user gets delivery note summary', function () {
    $response = AikuServer::actingAs($this->user)->tool(DeliveryNotesSummaryTool::class, [
        'warehouse' => $this->warehouse->slug,
        'from'      => '2026-01-01',
        'to'        => '2026-12-31',
    ]);

    $response->assertOk();
});
