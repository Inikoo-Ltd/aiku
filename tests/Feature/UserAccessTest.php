<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Actions\SysAdmin\User\UpdateUser;
use App\Actions\UI\Profile\DeleteProfileApiToken;
use App\Actions\UI\Profile\StoreProfileApiToken;
use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Http\Resources\SysAdmin\User\UserShowcaseResource;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\McpRequest;
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
});

/*
 * Both blocks share one admin user, because createAdminGuest() hands back the existing group-admin
 * rather than a new one. The permission caching block below strips that user's roles and revokes its
 * permissions to prove a revocation cannot linger in the cache, so it runs last and nothing may be
 * appended after it without restoring what it takes away. That is also why these two blocks are here
 * and not appended to SysAdminTest: that file has its own role tests operating on the same
 * group-admin, and neither set of tests would survive the other.
 */

describe('profile api tokens', function () {
    test('user can create an api token', function () {
        $result = StoreProfileApiToken::make()->handle($this->user, 'Claude');

        expect($result['token'])->toBeString()->toContain('|')
            ->and($this->user->tokens()->where('name', 'Claude')->exists())->toBeTrue();
    });

    test('user can revoke own token', function () {
        StoreProfileApiToken::make()->handle($this->user, 'ToRevoke');
        $token = $this->user->tokens()->where('name', 'ToRevoke')->first();

        $deleted = DeleteProfileApiToken::make()->handle($this->user, $token->id);

        expect($deleted)->toBeTrue()
            ->and($this->user->tokens()->whereKey($token->id)->exists())->toBeFalse();
    });

    test('user cannot revoke another users token', function () {
        StoreProfileApiToken::make()->handle($this->user, 'NotYours');
        $token = $this->user->tokens()->where('name', 'NotYours')->first();

        $otherGuest = StoreGuest::make()->action(
            $this->group,
            array_merge(
                Guest::factory()->definition(),
                ['positions' => []]
            )
        );

        $deleted = DeleteProfileApiToken::make()->handle($otherGuest->getUser(), $token->id);

        expect($deleted)->toBeFalse()
            ->and($this->user->tokens()->whereKey($token->id)->exists())->toBeTrue();
    });

    /* The "starts off" half is asserted on a guest made here rather than on the shared admin, whose
       flag any block above could have turned on. */
    test('can_use_mcp can be toggled via UpdateUser', function () {
        $guest = StoreGuest::make()->action(
            $this->group,
            array_merge(
                Guest::factory()->definition(),
                ['positions' => []]
            )
        );
        $user = $guest->getUser();

        expect($user->can_use_mcp)->toBeFalse();

        UpdateUser::make()->action($user, ['can_use_mcp' => true]);

        expect($user->refresh()->can_use_mcp)->toBeTrue();
    });

    test('user showcase reports mcp usage stats', function () {
        $this->user->update(['can_use_mcp' => true]);
        McpRequest::create([
            'group_id'  => $this->group->id,
            'user_id'   => $this->user->id,
            'tool'      => 'shop-sales',
            'arguments' => ['shop' => 'x'],
        ]);

        $showcase = UserShowcaseResource::make($this->user->refresh())->resolve();

        expect($showcase['mcp']['enabled'])->toBeTrue()
            ->and($showcase['mcp']['number_queries'])->toBeGreaterThanOrEqual(1)
            ->and($showcase['mcp']['last_used_at'])->not()->toBeNull();
    });
});

describe('permission caching', function () {
    beforeEach(function () {
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
});
