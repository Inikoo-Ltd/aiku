<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 21:46:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Seeders;

use App\Enums\SysAdmin\Authorisation\OrganisationPermissionsEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\Permission;
use App\Models\SysAdmin\Role;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Permission\PermissionRegistrar;

class SeedOrganisationPermissions
{
    use AsAction;

    public function handle(Organisation $organisation): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $organisationPermissions = collect(OrganisationPermissionsEnum::getAllValues($organisation));

        $currentPermissions = Permission::where('scope_type', 'Organisation')->where('scope_id', $organisation->id)->pluck('name');
        $currentPermissions->diff($organisationPermissions)
            ->each(function ($permissionName) {
                Permission::where('name', $permissionName)->first()->delete();
            });

        $organisationPermissions->each(function ($permissionName) use ($organisation) {
            try {
                Permission::create(
                    [
                        'name' => $permissionName,
                        'scope_type' => 'Organisation',
                        'scope_id' => $organisation->id,
                    ]
                );
            } catch (Exception) {
            }
        });

        $organisationRoles = collect(RolesEnum::getRolesWithScope($organisation));

        $currentRoles = Role::where('scope_type', 'Organisation')->where('scope_id', $organisation->id)->pluck('name');
        $currentRoles->diff($organisationRoles)
            ->each(function ($roleName) {
                Role::where(
                    'name',
                    $roleName
                )->first()->delete();
            });

        foreach (RolesEnum::cases() as $case) {
            if ($case->scope() === 'Organisation' and in_array($organisation->type, $case->scopeTypes())) {

                if (! $role = (new Role)->where('name', RolesEnum::getRoleName($case->value, $organisation))->first()) {
                    $role = Role::create(
                        [
                            'name' => RolesEnum::getRoleName($case->value, $organisation),
                            'scope_type' => 'Organisation',
                            'scope_id' => $organisation->id,
                            'group_id' => $organisation->group_id,
                        ]
                    );
                }
                $organisationPermissions = [];

                foreach ($case->getPermissions() as $permissionName) {
                    if ($permission = (new Permission)->where('name', OrganisationPermissionsEnum::getPermissionName($permissionName->value, $organisation))->first()) {
                        $organisationPermissions[] = $permission;
                    }
                }
                $role->syncPermissions($organisationPermissions);
            }
        }
    }

    public string $commandSignature = 'org:seed-permissions';

    public function asCommand(Command $command): int
    {
        foreach (Organisation::all() as $organisation) {
            $command->info("Seeding permissions for org: $organisation->name");
            setPermissionsTeamId($organisation->group_id);
            $this->handle($organisation);
        }

        return 0;
    }
}
