<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 30 Sept 2022 10:28:32 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Models\Traits;

use App\Models\Catalogue\Shop;
use App\Models\HumanResources\JobPosition;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles as SpatieHasRoles;

trait HasRoles
{
    use SpatieHasRoles {
        assignRole as spatieAssignRole;
        removeRole as spatieRemoveRole;
        givePermissionTo as spatieGivePermissionTo;
        revokePermissionTo as spatieRevokePermissionTo;
    }


    /**
     * Only positive results are cached. A false is never stored: a check can return
     * false for transient reasons (no team id bound on the request, a permission
     * granted a moment later) and caching that locks the user out of the permission
     * until the tag is flushed, which most permission-granting paths never do.
     */
    public function authTo(string|array $permission): bool
    {
        $this->bindPermissionsTeam();

        $key = 'can:'.(is_array($permission) ? implode('|', $permission) : $permission);

        if (Cache::tags('auth-user:'.$this->id)->get($key) === true) {
            return true;
        }

        $can = is_array($permission)
            ? $this->hasAnyPermission($permission)
            : $this->hasPermissionTo($permission);

        $can = $can || $this->authToThroughMasters((array) $permission);

        if ($can) {
            Cache::tags('auth-user:'.$this->id)->put($key, true, 3600);
        }

        return $can;
    }

    /**
     * Masters staff look after every shop hanging from a master shop: they view and edit
     * its catalogue as the master shop's own permissions allow, and view (never move)
     * the stock of any organisation.
     */
    protected function authToThroughMasters(array $permissions): bool
    {
        if (!$this instanceof User) {
            return false;
        }

        foreach ($permissions as $permission) {
            if (preg_match('/^products\.(\d+)(\.view|\.edit)?$/', $permission, $matches)) {
                $mastersPermission = ($matches[2] ?? null) === '.view' ? 'masters.view' : 'masters.edit';

                if ($this->hasPermissionTo($mastersPermission)
                    && Shop::where('id', $matches[1])->where('group_id', $this->group_id)->whereNotNull('master_shop_id')->exists()) {
                    return true;
                }
            } elseif (preg_match('/^inventory\.\d+\.view$/', $permission) && $this->hasPermissionTo('masters.view')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The permissions team is always the owner's group, but it is null at the start of every
     * request (spatie resets it on OperationTerminated) and keeps the previous job's group in
     * queue workers. Binding it here, and dropping the state a wrong team already poisoned,
     * keeps authTo() correct on the route groups without bind_group and on queued/console
     * entry points, where it would otherwise deny everything or read another group.
     */
    protected function bindPermissionsTeam(): void
    {
        if (!$this->group_id) {
            return;
        }

        $registrar = app(PermissionRegistrar::class);

        if ($registrar->getPermissionsTeamId() == $this->group_id) {
            return;
        }

        $registrar->setPermissionsTeamId($this->group_id);
        $registrar->forgetCachedPermissions();
        $this->unsetRelation('roles')->unsetRelation('permissions');
    }

    /**
     * Spatie only forgets its cache for role and permission models, never for the user whose
     * roles changed, so every mutation of this user goes through here to drop the authTo()
     * cache. syncRoles() and syncPermissions() end in assignRole()/givePermissionTo(), so
     * these four cover them all.
     */
    private function flushAuthCache(): void
    {
        Cache::tags('auth-user:'.$this->id)->flush();
        $this->forgetWildcardPermissionIndex();
    }

    public function assignRole(...$roles)
    {
        $result = $this->spatieAssignRole(...$roles);
        $this->flushAuthCache();

        return $result;
    }

    public function removeRole(...$role)
    {
        $result = $this->spatieRemoveRole(...$role);
        $this->flushAuthCache();

        return $result;
    }

    public function givePermissionTo(...$permissions)
    {
        $result = $this->spatieGivePermissionTo(...$permissions);
        $this->flushAuthCache();

        return $result;
    }

    public function revokePermissionTo($permission)
    {
        $result = $this->spatieRevokePermissionTo($permission);
        $this->flushAuthCache();

        return $result;
    }


    public function assignJoBPositionRoles(JobPosition $jobPosition): void
    {
        foreach ($jobPosition->roles as $roleID) {
            $this->assignRole($roleID);
            $this->roles()->updateExistingPivot($roleID, ['locked' => true]);
        }
    }

    public function removeJoBPositionRoles(JobPosition $jobPosition): void
    {
        foreach ($jobPosition->roles as $roleID) {
            $currentRole = $this->roles()->wherePivot('role_id', $roleID)->first();

            if ($currentRole && $currentRole->pivot->direct_role) {
                $this->roles()->updateExistingPivot($roleID, ['locked' => false]);
            } else {
                $this->removeRole($roleID);
            }
        }
    }


}
