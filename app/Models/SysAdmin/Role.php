<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:30:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use App\Models\HumanResources\JobPosition;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * App\Models\SysAdmin\Role
 *
 * @property int $id
 * @property int|null $group_id
 * @property string $name
 * @property string $guard_name
 * @property string $scope_type
 * @property int $scope_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Collection<int, JobPosition> $jobPositions
 * @property-read Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read Collection<int, \App\Models\SysAdmin\User> $users
 *
 * @method static Builder<static>|Role newModelQuery()
 * @method static Builder<static>|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role permission($permissions, $without = false)
 * @method static Builder<static>|Role query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role withoutPermission($permissions)
 *
 * @mixin Eloquent
 */
class Role extends SpatieRole
{
    public function jobPositions(): BelongsToMany
    {
        return $this->belongsToMany(JobPosition::class);
    }
}
