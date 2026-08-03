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

test('tools accept a code as well as a slug', function () {
    $response = AikuServer::actingAs($this->user)->tool(App\Mcp\Tools\ShopSalesTool::class, [
        'shop' => strtoupper($this->shop->code),
        'from' => '2026-01-01',
        'to'   => '2026-01-31',
    ]);

    $response->assertOk()->assertSee($this->shop->name);
});

test('sql users are offered only the database tools', function () {
    $this->user->update(['can_use_mcp_sql' => true]);

    actingAs($this->user);
    $mcpRequest = new Laravel\Mcp\Request();

    expect((new App\Mcp\Tools\ShopSalesTool())->shouldRegister($mcpRequest))->toBeFalse()
        ->and((new App\Mcp\Tools\SqlQueryTool())->shouldRegister($mcpRequest))->toBeTrue();

    $this->user->update(['can_use_mcp_sql' => false]);

    expect((new App\Mcp\Tools\ShopSalesTool())->shouldRegister($mcpRequest))->toBeTrue()
        ->and((new App\Mcp\Tools\SqlQueryTool())->shouldRegister($mcpRequest))->toBeFalse();
});
