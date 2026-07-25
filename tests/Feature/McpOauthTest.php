<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\UI\Profile\StoreProfileApiToken;
use App\Models\SysAdmin\McpRequest;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

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

    $this->user->update(['can_use_mcp' => true]);
});

test('user without can_use_mcp flag is rejected with 403', function () {
    $this->user->update(['can_use_mcp' => false]);
    $plainTextToken = $this->user->createToken('NoMcpFlag')->plainTextToken;

    postJson('/mcp/aiku', [
        'jsonrpc' => '2.0',
        'id'      => 1,
        'method'  => 'ping',
    ], [
        'Accept'        => 'application/json, text/event-stream',
        'Authorization' => 'Bearer '.$plainTextToken,
    ])->assertForbidden();

    $this->user->update(['can_use_mcp' => true]);
});

test('oauth discovery endpoints are published', function () {
    getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonStructure(['issuer', 'authorization_endpoint', 'token_endpoint', 'registration_endpoint']);

    getJson('/.well-known/oauth-protected-resource')
        ->assertOk()
        ->assertJsonStructure(['resource', 'authorization_servers']);
});

test('mcp clients can dynamically register', function () {
    postJson('/oauth/register', [
        'client_name'   => 'TestAI',
        'redirect_uris' => ['https://example.com/callback'],
    ])
        ->assertCreated()
        ->assertJsonStructure(['client_id']);
});

test('mcp endpoint rejects unauthenticated requests', function () {
    postJson('/mcp/aiku', [
        'jsonrpc' => '2.0',
        'id'      => 1,
        'method'  => 'ping',
    ])->assertUnauthorized();
});

test('web user customer tokens are rejected by mcp endpoint', function () {
    createWebsite($this->shop);
    $customer = createCustomer($this->shop);
    $webUser  = createWebUser($customer);

    $webUserToken = $webUser->createToken('customer-token')->plainTextToken;

    postJson('/mcp/aiku', [
        'jsonrpc' => '2.0',
        'id'      => 1,
        'method'  => 'ping',
    ], [
        'Accept'        => 'application/json, text/event-stream',
        'Authorization' => 'Bearer '.$webUserToken,
    ])->assertUnauthorized();
});

test('mcp endpoint still accepts sanctum tokens', function () {
    $plainTextToken = StoreProfileApiToken::make()->handle($this->user, 'McpTest')['token'];

    postJson('/mcp/aiku', [
        'jsonrpc' => '2.0',
        'id'      => 1,
        'method'  => 'ping',
    ], [
        'Accept'        => 'application/json, text/event-stream',
        'Authorization' => 'Bearer '.$plainTextToken,
    ])->assertOk();
});

test('tool calls are logged with user tool and arguments', function () {
    $plainTextToken = StoreProfileApiToken::make()->handle($this->user, 'McpLogTest')['token'];

    $countBefore = McpRequest::count();

    postJson('/mcp/aiku', [
        'jsonrpc' => '2.0',
        'id'      => 2,
        'method'  => 'tools/call',
        'params'  => [
            'name'      => 'shop-sales',
            'arguments' => ['shop' => $this->shop->slug, 'from' => '2026-01-01', 'to' => '2026-01-31'],
        ],
    ], [
        'Accept'        => 'application/json, text/event-stream',
        'Authorization' => 'Bearer '.$plainTextToken,
    ])->assertOk();

    expect(McpRequest::count())->toBe($countBefore + 1);

    $logged = McpRequest::latest('id')->first();
    expect($logged->user_id)->toBe($this->user->id)
        ->and($logged->tool)->toBe('shop-sales')
        ->and($logged->arguments['shop'])->toBe($this->shop->slug);
});

test('ping requests are not logged', function () {
    $plainTextToken = StoreProfileApiToken::make()->handle($this->user, 'McpPingTest')['token'];

    $countBefore = McpRequest::count();

    postJson('/mcp/aiku', [
        'jsonrpc' => '2.0',
        'id'      => 3,
        'method'  => 'ping',
    ], [
        'Accept'        => 'application/json, text/event-stream',
        'Authorization' => 'Bearer '.$plainTextToken,
    ])->assertOk();

    expect(McpRequest::count())->toBe($countBefore);
});
