<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->group      = createGroup();
    $this->adminGuest = createAdminGuest($this->group);
});

test('guest can fetch passkey login options', function () {
    $response = getJson(route('grp.passkeys.authentication_options'));

    $response->assertOk();
    expect(json_decode($response->getContent(), true))->toHaveKey('challenge');
});

test('passkey login rejects an invalid authentication response', function () {
    $response = postJson(route('grp.passkeys.login'), [
        'start_authentication_response' => json_encode(['foo' => 'bar']),
    ]);

    $response->assertSessionHasErrors();
});

test('passkey registration options require authentication', function () {
    $response = getJson(route('grp.profile.passkey.options'));

    $response->assertUnauthorized();
});

test('authenticated user can fetch passkey registration options', function () {
    actingAs($this->adminGuest->getUser());

    $response = getJson(route('grp.profile.passkey.options'));

    $response->assertOk();
    expect(json_decode($response->getContent(), true))->toHaveKey('challenge');
});

test('registering a passkey rejects an invalid credential response', function () {
    actingAs($this->adminGuest->getUser());

    $options = getJson(route('grp.profile.passkey.options'))->getContent();

    $response = postJson(route('grp.profile.passkey.store'), [
        'passkey' => json_encode(['foo' => 'bar']),
        'options' => $options,
    ]);

    $response->assertSessionHasErrors('passkey');
});

test('user can delete their own passkey', function () {
    $user = $this->adminGuest->getUser();
    actingAs($user);

    $passkey = $user->passkeys()->create([
        'name'          => 'test',
        'credential_id' => 'test-credential-id',
        'data'          => '{}',
    ]);

    deleteJson(route('grp.profile.passkey.delete', $passkey->id))
        ->assertSessionDoesntHaveErrors();

    expect($user->passkeys()->count())->toBe(0);
});
