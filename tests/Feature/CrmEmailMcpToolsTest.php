<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Mcp\Servers\AikuServer;
use App\Mcp\Tools\CustomerEmailPressureTool;
use App\Mcp\Tools\MailshotPerformanceTool;
use App\Actions\SysAdmin\Guest\StoreGuest;
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

test('user without marketing view permission is denied on MailshotPerformanceTool', function () {
    $guest = StoreGuest::make()->action(
        $this->group,
        array_merge(
            Guest::factory()->definition(),
            ['positions' => []]
        )
    );

    $response = AikuServer::actingAs($guest->getUser())->tool(MailshotPerformanceTool::class, [
        'shop' => $this->shop->slug,
    ]);

    $response->assertHasErrors(['Shop not found or permission denied.']);
});

test('user without crm view permission is denied on CustomerEmailPressureTool', function () {
    $guest = StoreGuest::make()->action(
        $this->group,
        array_merge(
            Guest::factory()->definition(),
            ['positions' => []]
        )
    );

    $response = AikuServer::actingAs($guest->getUser())->tool(CustomerEmailPressureTool::class, [
        'shop' => $this->shop->slug,
        'from' => '2026-01-01',
        'to'   => '2026-12-31',
    ]);

    $response->assertHasErrors(['Shop not found or permission denied.']);
});

test('admin user gets mailshot performance with no data', function () {
    $response = AikuServer::actingAs($this->user)->tool(MailshotPerformanceTool::class, [
        'shop' => $this->shop->slug,
    ]);

    $response->assertOk()
        ->assertSee('"mailshots":[]');
});

test('admin user gets customer email pressure with date range', function () {
    $response = AikuServer::actingAs($this->user)->tool(CustomerEmailPressureTool::class, [
        'shop' => $this->shop->slug,
        'from' => '2026-01-01',
        'to'   => '2026-12-31',
    ]);

    $response->assertOk()
        ->assertSee('"total_emails":0')
        ->assertSee('"customers_reached":0');
});
