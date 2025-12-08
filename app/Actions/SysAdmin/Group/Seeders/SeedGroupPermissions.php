<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 21:41:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Seeders;

use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Permission;
use App\Models\SysAdmin\Role;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Permission\PermissionRegistrar;

class SeedGroupPermissions
{
    use AsAction;

    public function handle(Group $group): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $groupPermissions = collect(GroupPermissionsEnum::getAllValues());
        $groupRoles = collect(RolesEnum::getRolesWithScope($group));

        $currentPermissions = Permission::where('scope_type', 'Group')->pluck('name');
        $currentPermissions->diff($groupPermissions)
            ->each(function ($permissionName) {
                Permission::where('name', $permissionName)->first()->delete();
            });

        $currentRoles = Role::where('scope_type', 'Group')->pluck('name');

        $currentRoles->diff($groupRoles)
            ->each(function ($roleName) {

                Role::where('name', $roleName)->first()->delete();
            });

        $groupPermissions->each(function ($permissionName) use ($group) {
            try {
                Permission::create(
                    [
                        'name' => $permissionName,
                        'scope_type' => 'Group',
                        'scope_id' => $group->id,
                    ]
                );
            } catch (Exception) {
            }
        });

        foreach (RolesEnum::cases() as $case) {
            if ($case->scope() === 'Group') {

                if (! $role = (new Role)->where('name', $case->value)->first()) {
                    $role = Role::create(
                        [
                            'name' => $case->value,
                            'group_id' => $group->id,
                            'scope_type' => 'Group',
                            'scope_id' => $group->id,
                        ]
                    );
                }
                $permissions = [];
                foreach ($case->getPermissions() as $permissionName) {
                    if ($permission = (new Permission)->where('name', $permissionName)->first()) {
                        $permissions[] = $permission;
                    }
                }

                $role->syncPermissions($permissions);
            }
        }
    }

    public string $commandSignature = 'group:seed-permissions';

    public function asCommand(Command $command): int
    {
        foreach (Group::all() as $group) {
            $command->info("Seeding permissions for group: $group->name");
            setPermissionsTeamId($group->id);
            $this->handle($group);
        }

        return 0;
    }
}
