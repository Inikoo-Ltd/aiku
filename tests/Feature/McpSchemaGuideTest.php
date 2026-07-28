<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Mcp\Resources\AikuDataGuideResource;
use App\Mcp\Servers\AikuServer;
use App\Mcp\Tools\DescribeTablesTool;

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

    $this->user->update(['can_use_mcp' => true, 'can_use_mcp_sql' => true]);

    config()->set('mcp.sql_read_only_user', 'aiku_read_only_test');
});

test('sql tools refuse to run without a dedicated read only database user', function () {
    config()->set('mcp.sql_read_only_user', null);

    $response = AikuServer::actingAs($this->user)->tool(DescribeTablesTool::class, [
        'search' => 'invoices',
    ]);

    $response->assertHasErrors(['SQL access is disabled: this environment has no dedicated read-only database user configured.']);
});

test('user without sql access is denied schema introspection', function () {
    $this->user->update(['can_use_mcp_sql' => false]);

    $response = AikuServer::actingAs($this->user)->tool(DescribeTablesTool::class, [
        'search' => 'invoices',
    ]);

    $response->assertHasErrors(['SQL access is not enabled for this user.']);
});

test('search finds tables by partial name', function () {
    $response = AikuServer::actingAs($this->user)->tool(DescribeTablesTool::class, [
        'search' => 'shop_time_series',
    ]);

    $response->assertOk()
        ->assertSee('shop_time_series')
        ->assertSee('shop_time_series_records');
});

test('describe returns columns and foreign keys', function () {
    $response = AikuServer::actingAs($this->user)->tool(DescribeTablesTool::class, [
        'tables' => ['invoices'],
    ]);

    $response->assertOk()
        ->assertSee('total_amount')
        ->assertSee('deleted_at')
        ->assertSee('foreign_keys');
});

test('describe reports unknown tables instead of failing', function () {
    $response = AikuServer::actingAs($this->user)->tool(DescribeTablesTool::class, [
        'tables' => ['no_such_table_here'],
    ]);

    $response->assertOk()->assertSee('not_found');
});

test('data guide resource explains the time series rule', function () {
    $response = AikuServer::resource(AikuDataGuideResource::class);

    $response->assertOk()
        ->assertSee('sales_external')
        ->assertSee('sales_intervals')
        ->assertSee('deleted_at');
});
