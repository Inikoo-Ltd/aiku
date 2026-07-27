<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 30 Sept 2022 10:28:32 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Models\Traits;

use App\Models\HumanResources\JobPosition;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles as SpatieHasRoles;

trait HasRoles
{
    use SpatieHasRoles;


    /**
     * Only positive results are cached. A false is never stored: a check can return
     * false for transient reasons (no team id bound on the request, a permission
     * granted a moment later) and caching that locks the user out of the permission
     * until the tag is flushed, which most permission-granting paths never do.
     */
    public function authTo(string|array $permission): bool
    {
        $key = 'can:'.(is_array($permission) ? implode('|', $permission) : $permission);

        if (Cache::tags('auth-user:'.$this->id)->get($key) === true) {
            return true;
        }

        $can = is_array($permission)
            ? $this->hasAnyPermission($permission)
            : $this->hasPermissionTo($permission);

        if ($can) {
            Cache::tags('auth-user:'.$this->id)->put($key, true, 604800);
        }

        return $can;
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
