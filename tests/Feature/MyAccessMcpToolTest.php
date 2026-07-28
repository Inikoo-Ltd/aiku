<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Mcp\Servers\AikuServer;
use App\Mcp\Tools\MyAccessTool;
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

test('an admin sees the slugs they can query', function () {
    $response = AikuServer::actingAs($this->user)->tool(MyAccessTool::class, []);

    $response->assertOk()
        ->assertSee($this->shop->slug)
        ->assertSee($this->organisation->slug);
});

test('a user with no permissions sees empty lists rather than an error', function () {
    $guest = StoreGuest::make()->action(
        $this->group,
        array_merge(
            Guest::factory()->definition(),
            ['positions' => []]
        )
    );

    $response = AikuServer::actingAs($guest->getUser())->tool(MyAccessTool::class, []);

    $response->assertOk()
        ->assertSee('"shops":[]')
        ->assertSee('"organisations":[]');
});
