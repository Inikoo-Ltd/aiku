<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\SysAdmin\Role;
use Illuminate\Support\Facades\Cache;

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

    $this->permission = ShopPermissionsEnum::getPermissionName(ShopPermissionsEnum::ORDERS_VIEW->value, $this->shop);
    Cache::tags('auth-user:'.$this->user->id)->flush();
});

test('a granted permission is allowed and cached', function () {
    expect($this->user->authTo($this->permission))->toBeTrue()
        ->and(Cache::tags('auth-user:'.$this->user->id)->get('can:'.$this->permission))->toBeTrue();
});

test('a denied permission is never cached', function () {
    $missing = 'orders.999999.view';

    expect($this->user->authTo($missing))->toBeFalse()
        ->and(Cache::tags('auth-user:'.$this->user->id)->get('can:'.$missing))->toBeNull();
});

test('a permission granted after a denial takes effect immediately', function () {
    $permission = ShopPermissionsEnum::getPermissionName(ShopPermissionsEnum::WEB_EDIT->value, $this->shop);

    $this->user->revokePermissionTo($permission);
    $this->user->roles()->detach();
    $this->user->unsetRelation('permissions')->unsetRelation('roles');
    Cache::tags('auth-user:'.$this->user->id)->flush();

    expect($this->user->authTo($permission))->toBeFalse();

    $this->user->givePermissionTo($permission);
    $this->user->unsetRelation('permissions')->unsetRelation('roles');

    expect($this->user->authTo($permission))->toBeTrue('a denial must not linger in the cache');
});

test('a check with no team bound binds the user group instead of denying', function () {
    setPermissionsTeamId(null);
    $this->user->unsetRelation('permissions')->unsetRelation('roles');

    expect($this->user->authTo($this->permission))->toBeTrue('an unbound request must not deny a granted permission')
        ->and(getPermissionsTeamId())->toBe($this->user->group_id);
});

test('a check bound to another group rebinds to the user group', function () {
    setPermissionsTeamId($this->group->id + 999);
    $this->user->hasPermissionTo($this->permission);

    expect($this->user->authTo($this->permission))->toBeTrue('a foreign team must not poison the check')
        ->and(getPermissionsTeamId())->toBe($this->user->group_id);
});

test('losing a role drops the cached true', function () {
    expect($this->user->authTo($this->permission))->toBeTrue();

    foreach ($this->user->roles as $role) {
        $this->user->removeRole($role);
    }
    $this->user->revokePermissionTo($this->permission);

    expect(Cache::tags('auth-user:'.$this->user->id)->get('can:'.$this->permission))->toBeNull()
        ->and($this->user->authTo($this->permission))->toBeFalse('a revoked permission must not survive in the cache');
});

test('taking a permission off a role drops the cached true of its holders', function () {
    foreach ($this->user->roles as $role) {
        $this->user->removeRole($role);
    }
    $this->user->revokePermissionTo($this->permission);

    $role = Role::create([
        'name'       => 'auth-to-caching-test.'.$this->shop->id,
        'guard_name' => 'web',
        'scope_type' => 'Shop',
        'scope_id'   => $this->shop->id,
        'group_id'   => $this->group->id,
    ]);
    $role->givePermissionTo($this->permission);
    $this->user->assignRole($role);

    expect($this->user->authTo($this->permission))->toBeTrue();

    $role->syncPermissions([]);
    $this->user->unsetRelation('permissions')->unsetRelation('roles');

    expect(Cache::tags('auth-user:'.$this->user->id)->get('can:'.$this->permission))->toBeNull()
        ->and($this->user->authTo($this->permission))->toBeFalse('a role change must not survive in the holders cache');
});
