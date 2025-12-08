<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 12:17:52 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Production;

use App\Enums\SysAdmin\Authorisation\ProductionPermissionsEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Production\Production;
use App\Models\SysAdmin\Permission;
use App\Models\SysAdmin\Role;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Permission\PermissionRegistrar;

class SeedProductionPermissions
{
    use AsAction;

    public function handle(Production $production): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $productionPermissions = collect(ProductionPermissionsEnum::getAllValues($production));

        $currentPermissions = Permission::where('scope_type', 'Production')->where('scope_id', $production->id)->pluck('name');
        $currentPermissions->diff($productionPermissions)
            ->each(function ($permissionName) {
                Permission::where('name', $permissionName)->first()->delete();
            });

        $productionPermissions->each(function ($permissionName) use ($production) {
            try {
                Permission::create(
                    [
                        'name' => $permissionName,
                        'scope_type' => 'Production',
                        'scope_id' => $production->id,
                    ]
                );
            } catch (Exception) {
            }
        });

        $productionRoles = collect(RolesEnum::getRolesWithScope($production));

        $currentRoles = Role::where('scope_type', 'Production')->where('scope_id', $production->id)->pluck('name');
        $currentRoles->diff($productionRoles)
            ->each(function ($roleName) {
                Role::where(
                    'name',
                    $roleName
                )->first()->delete();
            });

        foreach (RolesEnum::cases() as $case) {
            if ($case->scope() === 'Production') {

                if (! $role = (new Role)->where('name', RolesEnum::getRoleName($case->value, $production))->first()) {

                    $role = Role::create(
                        [
                            'name' => RolesEnum::getRoleName($case->value, $production),
                            'scope_type' => 'Production',
                            'scope_id' => $production->id,
                            'group_id' => $production->group_id,
                        ]
                    );
                }
                $productionPermissions = [];

                foreach ($case->getPermissions() as $permissionName) {
                    if ($permission = (new Permission)->where('name', ProductionPermissionsEnum::getPermissionName($permissionName->value, $production))->first()) {
                        $productionPermissions[] = $permission;
                    }
                }
                $role->syncPermissions($productionPermissions);
            }
        }
    }

    public string $commandSignature = 'production:seed-permissions';

    public function asCommand(Command $command): int
    {
        foreach (Production::all() as $production) {
            $command->info("Seeding permissions for production: $production->name");
            setPermissionsTeamId($production->group_id);
            $this->handle($production);

        }

        return 0;
    }
}
