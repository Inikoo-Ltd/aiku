<?php

/*
 * Author Louis Perez
 * Created on 18-09-2026
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

use App\Enums\UI\SysAdmin\ProfileTabsEnum;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->group      = createGroup();
    $this->adminGuest = createAdminGuest($this->group);
    actingAs($this->adminGuest->getUser());
});

test('profile showcase exposes the contact name', function () {
    $user = $this->adminGuest->getUser();

    getJson(route('grp.profile.showcase.show'))
        ->assertOk()
        ->assertJsonPath('data.username', $user->username)
        ->assertJsonPath('data.contact_name', $user->contact_name);
});

test('a nickname saved from the profile card is returned by the showcase', function () {
    patchJson(route('grp.models.profile.update'), ['nickname' => 'Card Nick'])->assertSuccessful();

    getJson(route('grp.profile.showcase.show'))
        ->assertOk()
        ->assertJsonPath('data.nickname', 'Card Nick');

    patchJson(route('grp.models.profile.update'), ['nickname' => 'x'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('nickname');
});

test('profile tabs no longer list clocking', function () {
    expect(ProfileTabsEnum::navigation())
        ->not->toHaveKey('clocking')
        ->toHaveKeys(['dashboard', 'notifications', 'timesheets']);
});
