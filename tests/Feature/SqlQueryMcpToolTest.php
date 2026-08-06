<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Mcp\Servers\AikuServer;
use App\Mcp\Tools\SqlQueryTool;

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

    config(['database.connections.aiku_read_only' => config('database.connections.'.config('database.default'))]);
    config()->set('mcp.sql_read_only_user', 'aiku_read_only_test');
});

test('user without sql access is not offered the tool at all', function () {
    $this->user->update(['can_use_mcp_sql' => false]);
    actingAs($this->user);

    expect((new SqlQueryTool())->shouldRegister(new Laravel\Mcp\Request()))->toBeFalse();

    $this->user->update(['can_use_mcp_sql' => true]);

    expect((new SqlQueryTool())->shouldRegister(new Laravel\Mcp\Request()))->toBeTrue();
});

test('user with sql access can run a select', function () {
    $this->user->update(['can_use_mcp_sql' => true]);

    $response = AikuServer::actingAs($this->user)->tool(SqlQueryTool::class, [
        'sql' => 'select count(*) as total from shops',
    ]);

    $response->assertOk();
});

test('sql access is revoked when mcp access is turned off', function () {
    $this->user->update(['can_use_mcp' => true, 'can_use_mcp_sql' => true]);

    App\Actions\SysAdmin\User\UpdateUser::make()->action($this->user, ['can_use_mcp' => false]);

    expect($this->user->refresh()->can_use_mcp_sql)->toBeFalse();
});

test('sql access cannot be granted without mcp access', function () {
    $this->user->update(['can_use_mcp' => false, 'can_use_mcp_sql' => false]);

    App\Actions\SysAdmin\User\UpdateUser::make()->action($this->user, ['can_use_mcp_sql' => true]);

    expect($this->user->refresh()->can_use_mcp_sql)->toBeFalse();
});

test('non select statements are rejected', function () {
    $this->user->update(['can_use_mcp_sql' => true]);

    $response = AikuServer::actingAs($this->user)->tool(SqlQueryTool::class, [
        'sql' => 'update shops set name = \'x\'',
    ]);

    $response->assertHasErrors(['Only SELECT statements are allowed.']);
});

test('multiple statements are rejected', function () {
    $this->user->update(['can_use_mcp_sql' => true]);

    $response = AikuServer::actingAs($this->user)->tool(SqlQueryTool::class, [
        'sql' => 'select 1; drop table shops',
    ]);

    $response->assertHasErrors(['Only a single statement is allowed.']);
});

test('database parameter selects the nightowl connection', function () {
    $this->user->update(['can_use_mcp_sql' => true]);

    config(['database.connections.nightowl' => config('database.connections.'.config('database.default'))]);

    $response = AikuServer::actingAs($this->user)->tool(SqlQueryTool::class, [
        'sql'      => 'select 1 as one',
        'database' => 'nightowl',
    ]);

    $response->assertOk();
});

test('unknown database is rejected', function () {
    $this->user->update(['can_use_mcp_sql' => true]);

    $response = AikuServer::actingAs($this->user)->tool(SqlQueryTool::class, [
        'sql'      => 'select 1',
        'database' => 'prod',
    ]);

    $response->assertHasErrors();
});
