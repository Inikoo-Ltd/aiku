<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Apr 2023 16:05:15 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace Tests\Feature;

use App\Actions\Auth\GroupUser\DeleteGroupUser;
use App\Actions\Auth\GroupUser\StoreGroupUser;
use App\Actions\Auth\GroupUser\UpdateGroupUserStatus;
use App\Actions\Auth\Guest\StoreGuest;
use App\Actions\Auth\Guest\UpdateGuest;
use App\Actions\Auth\User\StoreUser;
use App\Actions\Auth\User\UpdateUser;
use App\Actions\Auth\User\UpdateUserStatus;
use App\Actions\Auth\User\UserAddRoles;
use App\Actions\Auth\User\UserRemoveRoles;
use App\Actions\Auth\User\UserSyncRoles;
use App\Actions\Tenancy\Group\StoreGroup;
use App\Actions\Tenancy\Tenant\StoreTenant;
use App\Enums\Auth\User\UserAuthTypeEnum;
use App\Models\Auth\GroupUser;
use App\Models\Auth\Guest;
use App\Models\Auth\User;
use App\Models\Tenancy\Group;
use App\Models\Tenancy\Tenant;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

beforeAll(function () {
    loadDB('test_base_database.dump');
});


beforeEach(function () {
    $tenant = Tenant::first();
    if (!$tenant) {
        $group  = StoreGroup::make()->asAction(
            array_merge(
                Group::factory()->definition(),
                [
                    'code' => 'ACME'
                ]
            )
        );
        $tenant = StoreTenant::make()->action(
            $group,
            array_merge(
                Tenant::factory()->definition(),
                [
                    'code' => 'AGB'
                ]
            )
        );
        StoreTenant::make()->action(
            $group,
            array_merge(
                Tenant::factory()->definition(),
                [
                    'code' => 'AUS'
                ]
            )
        );
    }
    $tenant->makeCurrent();
});

test('create guest', function () {
    $guestData = Guest::factory()->definition();
    Arr::set($guestData, 'phone', '+6281212121212');
    $guest = StoreGuest::make()->action(
        $guestData
    );

    $this->assertModelExists($guest);

    return $guest;
});

test('update guest', function ($guest) {
    $guest = UpdateGuest::make()->action($guest, ['contact_name' => 'Pika']);
    expect($guest->contact_name)->toBe('Pika');

    $guest = UpdateGuest::make()->action($guest, ['contact_name' => 'Aiku']);
    expect($guest->contact_name)->toBe('Aiku');
})->depends('create guest');

test('create user for guest', function ($guest) {
    Hash::shouldReceive('make')->andReturn('1234567');

    $userData  = User::factory()->definition();
    $groupUser = StoreGroupUser::make()->action($userData);
    expect($groupUser)->toBeInstanceOf(GroupUser::class)
        ->and($groupUser->auth_type)->toBe(UserAuthTypeEnum::DEFAULT);

    /** @noinspection PhpUnhandledExceptionInspection */
    $user = StoreUser::make()->action($guest, $groupUser);
    expect($user)->toBeInstanceOf(User::class)
        ->and($user->password)->toBe('1234567')
        ->and($user->groupUser->password)->toBe('1234567')
        ->and($user->parent)->toBeInstanceOf(Guest::class);

    return $user;
})->depends('create guest');

test('create user for guest with create username', function () {
    $userData = User::factory()->definition();
    Arr::set($userData, 'username', 'create');

    expect(function () use ($userData) {
        StoreGroupUser::make()->action($userData);
    })->toThrow(ValidationException::class);
})->depends('create guest');

test('create user for guest with export username', function () {
    $userData = User::factory()->definition();
    Arr::set($userData, 'username', 'export');

    expect(function () use ($userData) {
        StoreGroupUser::make()->action($userData);
    })->toThrow(ValidationException::class);
})->depends('create guest');

test('update user password', function ($user) {
    Hash::shouldReceive('make')
        ->andReturn('hello1234');


    $user = UpdateUser::make()->action($user, [
        'password' => 'secret'
    ]);

    /** @var GroupUser $groupUser */
    $groupUser = $user->groupUser()->first();

    expect($user->password)->toBe('hello1234')
        ->and($groupUser->password)->toBe('hello1234');


    return $user;
})->depends('create user for guest');

test('update user username', function ($user) {
    expect($user->username)->toBe('hello');
    $user = UpdateUser::make()->action($user, [
        'username' => 'aiku'
    ]);

    expect($user->username)->toBe('aiku')
        ->and($user->groupUser->username)->toBe('aiku');

    return $user;
})->depends('create user for guest');

test('attaching existing group user to another tenant guest', function ($user) {
    $groupUser = $user->groupUser;

    $tenant = Tenant::where('slug', 'aus')->first();
    $tenant->makeCurrent();

    $guestData = Guest::factory()->definition();
    Arr::set($guestData, 'phone', '+62081353890000');
    $guest = StoreGuest::make()->action(
        $guestData
    );

    /** @noinspection PhpUnhandledExceptionInspection */
    $user2 = StoreUser::make()->action($guest, $groupUser);
    $this->assertModelExists($user2);
    $groupUser->fresh();

    expect($groupUser->tenants->count())->toBe(2);

    return $user;
})->depends('update user username');

test('make sure a group user can be only attaches once in each tenant', function () {
    $tenant = Tenant::where('slug', 'aus')->first();
    $tenant->makeCurrent();

    $guestData = Guest::factory()->definition();
    Arr::set($guestData, 'phone', '+62081353890001');
    $guest = StoreGuest::make()->action(
        $guestData
    );

    $groupUser = GroupUser::where('username', 'hello')->first();

    expect(function () use ($guest, $groupUser) {
        /** @noinspection PhpUnhandledExceptionInspection */
        StoreUser::make()->action($guest, $groupUser);
    })->toThrow(ValidationException::class);
})->depends('update user password');


test('add user roles', function ($user) {
    expect($user->hasRole(['super-admin', 'system-admin']))->toBeFalse();

    $user = UserAddRoles::make()->action($user, ['super-admin', 'system-admin']);

    expect($user->hasRole(['super-admin', 'system-admin']))->toBeTrue();
})->depends('create user for guest');

test('remove user roles', function ($user) {
    $user = UserRemoveRoles::make()->action($user, ['super-admin', 'system-admin']);

    expect($user->hasRole(['super-admin', 'system-admin']))->toBeFalse();
})->depends('create user for guest');

test('sync user roles', function ($user) {
    $user = UserSyncRoles::make()->action($user, ['super-admin', 'system-admin']);

    expect($user->hasRole(['super-admin', 'system-admin']))->toBeTrue();
})->depends('create user for guest');

test('user change status if group user status is false  ', function ($user) {
    expect($user->status)->toBeTrue();

    $user = UpdateUserStatus::make()->action($user, [
        'status' => false
    ]);

    expect($user->status)->toBeFalse();
})->depends('create user for guest');

test('group status change status', function ($user) {
    $groupUser = $user->groupUser;
    expect($groupUser->status)->toBeTrue();
    $groupUser = UpdateGroupUserStatus::make()->action($groupUser, false);
    expect($groupUser->status)->toBeFalse();
})->depends('update user password');

test('delete group user', function ($user) {
    $groupUser = $user->groupUser;
    expect($groupUser)->toBeInstanceOf(GroupUser::class);
    $groupUser = DeleteGroupUser::make()->action($groupUser);
    expect($groupUser->deleted_at)->toBeInstanceOf(Carbon::class);
})->depends('update user password');
