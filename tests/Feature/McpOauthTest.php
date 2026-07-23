<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\UI\Profile\StoreProfileApiToken;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

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
