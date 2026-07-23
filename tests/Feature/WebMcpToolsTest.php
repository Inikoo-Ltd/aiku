<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Mcp\Servers\AikuServer;
use App\Mcp\Tools\WebsiteOverviewTool;
use App\Mcp\Tools\WebTrafficTool;
use App\Models\Analytics\WebUserRequest;
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

    app()->instance('group', $this->group);
    setPermissionsTeamId($this->group->id);
});

test('user without web permission is denied on website overview', function () {
    $guest = StoreGuest::make()->action(
        $this->group,
        array_merge(
            Guest::factory()->definition(),
            ['positions' => []]
        )
    );

    $response = AikuServer::actingAs($guest->getUser())->tool(WebsiteOverviewTool::class, [
        'shop' => $this->shop->slug,
    ]);

    $response->assertHasErrors(['Shop not found or permission denied.']);
});

test('admin user gets website overview', function () {
    $website = createWebsite($this->shop);

    $response = AikuServer::actingAs($this->user)->tool(WebsiteOverviewTool::class, [
        'shop' => $this->shop->slug,
    ]);

    $response->assertOk()
        ->assertSee('"website_name":"'.$website->name.'"');
});

test('admin user gets web traffic data', function () {
    $website = createWebsite($this->shop);
    $webUser = createWebUser(createCustomer($this->shop));

    WebUserRequest::create([
        'group_id'    => $this->group->id,
        'website_id'  => $website->id,
        'web_user_id' => $webUser->id,
        'date'        => '2026-06-15',
        'route_name'   => 'test',
        'route_params' => '{}',
        'location'     => '{}',
        'device'      => 'desktop',
        'os'          => 'Linux',
        'browser'     => 'Chrome',
        'ip_address'  => '127.0.0.1',
    ]);

    $response = AikuServer::actingAs($this->user)->tool(WebTrafficTool::class, [
        'shop' => $this->shop->slug,
        'from' => '2026-01-01',
        'to'   => '2026-12-31',
    ]);

    $response->assertOk()
        ->assertSee('"website_name":"'.$website->name.'"');
});
