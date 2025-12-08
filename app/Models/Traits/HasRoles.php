<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 30 Sept 2022 10:28:32 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Models\Traits;

use App\Models\HumanResources\JobPosition;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Traits\HasRoles as SpatieHasRoles;

trait HasRoles
{
    use SpatieHasRoles;

    public function authTo(string|array $permission): bool
    {
        if (is_array($permission)) {
            return Cache::remember(
                'auth-user:'.$this->id.';can:'.implode('|', $permission),
                604800,
                function () use ($permission) {
                    return $this->hasAnyPermission($permission);
                }
            );
        }

        return Cache::remember(
            'auth-user:'.$this->id.';can:'.$permission,
            604800,
            function () use ($permission) {
                return $this->hasPermissionTo($permission);
            }
        );
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
