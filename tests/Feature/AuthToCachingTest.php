<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
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

test('a denial with no team bound is not cached either', function () {
    setPermissionsTeamId(null);

    expect($this->user->authTo($this->permission))->toBeFalse()
        ->and(Cache::tags('auth-user:'.$this->user->id)->get('can:'.$this->permission))
        ->toBeNull('an unbound request must not leave a denial behind for bound ones');
});
