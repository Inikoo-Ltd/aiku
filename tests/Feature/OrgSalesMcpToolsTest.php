<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Mcp\Servers\AikuServer;
use App\Mcp\Tools\OrgFamilySalesTool;
use App\Mcp\Tools\OrgStockSalesTool;
use App\Models\SysAdmin\Guest;

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

test('user without accounting permission is denied on org family sales', function () {
    $guest = StoreGuest::make()->action(
        $this->group,
        array_merge(
            Guest::factory()->definition(),
            ['positions' => []]
        )
    );

    $response = AikuServer::actingAs($guest->getUser())->tool(OrgFamilySalesTool::class, [
        'organisation' => $this->organisation->slug,
        'from'         => '2026-01-01',
        'to'           => '2026-12-31',
    ]);

    $response->assertHasErrors(['Organisation not found or permission denied.']);
});

test('admin gets org family sales', function () {
    $response = AikuServer::actingAs($this->user)->tool(OrgFamilySalesTool::class, [
        'organisation' => $this->organisation->slug,
        'from'         => '2026-01-01',
        'to'           => '2026-12-31',
        'sort'         => 'worst',
    ]);

    $response->assertOk()
        ->assertSee('"sort":"worst"')
        ->assertSee('"families"');
});

test('admin gets org stock sales with stock on hand', function () {
    $response = AikuServer::actingAs($this->user)->tool(OrgStockSalesTool::class, [
        'organisation' => $this->organisation->slug,
        'from'         => '2026-01-01',
        'to'           => '2026-12-31',
    ]);

    $response->assertOk()
        ->assertSee('"sort":"best"')
        ->assertSee('"stocks"');
});

test('invalid sort fails validation', function () {
    $response = AikuServer::actingAs($this->user)->tool(OrgStockSalesTool::class, [
        'organisation' => $this->organisation->slug,
        'from'         => '2026-01-01',
        'to'           => '2026-12-31',
        'sort'         => 'sideways',
    ]);

    $response->assertHasErrors();
});
