<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jan 2024 11:25:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Fulfilment;

use App\Enums\SysAdmin\Authorisation\FulfilmentPermissionsEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Permission;
use App\Models\SysAdmin\Role;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Permission\PermissionRegistrar;

class SeedFulfilmentPermissions
{
    use AsAction;

    public function handle(Fulfilment $fulfilment): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();


        $fulfilmentPermissions = collect(FulfilmentPermissionsEnum::getAllValues($fulfilment));


        $currentPermissions = Permission::where('scope_type', 'Fulfilment')->where('scope_id', $fulfilment->id)->pluck('name');
        $currentPermissions->diff($fulfilmentPermissions)
            ->each(function ($permissionName) use ($fulfilment) {
                Permission::where('name', $permissionName)->delete();
            });


        $fulfilmentPermissions->each(function ($permissionName) use ($fulfilment) {
            try {
                Permission::create(
                    [
                        'name'       => $permissionName,
                        'scope_type' => 'Fulfilment',
                        'scope_id'   => $fulfilment->id,
                    ]
                );
            } catch (Exception) {
            }
        });

        $fulfilmentRoles = collect(RolesEnum::getRolesWithScope($fulfilment));


        $currentRoles = Role::where('scope_type', 'Fulfilment')->where('scope_id', $fulfilment->id)->pluck('name');
        $currentRoles->diff($fulfilmentRoles)
            ->each(function ($roleName) use ($fulfilment) {
                Role::where('name', $roleName)->delete();
            });


        foreach (RolesEnum::cases() as $case) {
            if ($case->scope() === 'Fulfilment') {
                if (!$role = (new Role())->where('name', RolesEnum::getRoleName($case->value, $fulfilment))->first()) {
                    $role = Role::create(
                        [
                            'name'       => RolesEnum::getRoleName($case->value, $fulfilment),
                            'scope_type' => 'Fulfilment',
                            'scope_id'   => $fulfilment->id,
                            'group_id'   => $fulfilment->group_id,
                        ]
                    );
                }
                $fulfilmentPermissions = [];

                foreach ($case->getPermissions() as $permissionName) {
                    if (class_basename($permissionName) == 'FulfilmentPermissionsEnum') {
                        if ($permission = (new Permission())->where('name', FulfilmentPermissionsEnum::getPermissionName($permissionName->value, $fulfilment))->first()) {
                            $fulfilmentPermissions[] = $permission;
                        }
                    } else {
                        foreach ($fulfilment->warehouses as $warehouse) {
                            if ($permission = (new Permission())->where('name', WarehousePermissionsEnum::getPermissionName($permissionName->value, $warehouse))->first()) {
                                $fulfilmentPermissions[] = $permission;
                            }
                        }
                    }
                }
                $role->syncPermissions($fulfilmentPermissions);
            } elseif ($case->scope() === 'Warehouse') {
                if ($case == RolesEnum::FULFILMENT_WAREHOUSE_SUPERVISOR) {
                    foreach ($fulfilment->warehouses as $warehouse) {
                        $role = (new Role())->where('name', RolesEnum::getRoleName($case->value, $warehouse))->first();
                        foreach ($case->getPermissions() as $permissionName) {
                            if (class_basename($permissionName) == 'WarehousePermissionsEnum') {
                                if ($permission = (new Permission())->where('name', WarehousePermissionsEnum::getPermissionName($permissionName->value, $warehouse))->first()) {
                                    $role->givePermissionTo($permission);
                                }
                            }
                        }
                    }
                }
            }
        }
    }


    public string $commandSignature = 'fulfilment:seed-permissions';

    public function asCommand(Command $command): int
    {
        foreach (Fulfilment::all() as $fulfilment) {
            $command->info("Seeding permissions for fulfilment: $fulfilment->shop->name");
            setPermissionsTeamId($fulfilment->group_id);
            $this->handle($fulfilment);
        }

        return 0;
    }

}
