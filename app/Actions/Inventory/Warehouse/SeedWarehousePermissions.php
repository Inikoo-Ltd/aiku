<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 06 Dec 2023 12:29:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Warehouse;

use App\Enums\SysAdmin\Authorisation\FulfilmentPermissionsEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Permission;
use App\Models\SysAdmin\Role;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Permission\PermissionRegistrar;

class SeedWarehousePermissions
{
    use AsAction;

    public function handle(Warehouse $warehouse): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();


        $warehousePermissions = collect(WarehousePermissionsEnum::getAllValues($warehouse));


        $currentPermissions = Permission::where('scope_type', 'Warehouse')->where('scope_id', $warehouse->id)->pluck('name');
        $currentPermissions->diff($warehousePermissions)
            ->each(function ($permissionName) {
                Permission::where('name', $permissionName)->first()->delete();
            });

        $warehousePermissions->each(function ($permissionName) use ($warehouse) {
            try {
                Permission::create(
                    [
                        'name'       => $permissionName,
                        'scope_type' => 'Warehouse',
                        'scope_id'   => $warehouse->id,
                    ]
                );
            } catch (Exception) {
                //
            }
        });

        $warehouseRoles = collect(RolesEnum::getRolesWithScope($warehouse));


        $currentRoles = Role::where('scope_type', 'Warehouse')->where('scope_id', $warehouse->id)->pluck('name');
        $currentRoles->diff($warehouseRoles)
            ->each(function ($roleName) {
                Role::where(
                    'name',
                    $roleName
                )->first()->delete();
            });


        foreach (RolesEnum::cases() as $case) {
            if ($case->scope() === 'Warehouse') {
                $this->processWarehouseScope($warehouse, $case);
            } elseif ($case->scope() === 'Fulfilment') {
                $this->processFulfilmentScope($warehouse, $case);
            }
        }
    }

    public function processFulfilmentScope(Warehouse $warehouse, RolesEnum $case): void
    {
        if ($case == RolesEnum::FULFILMENT_SHOP_SUPERVISOR) {
            foreach ($warehouse->fulfilments as $fulfilment) {
                /** @var Role $role */
                $role = (new Role())->where('name', RolesEnum::getRoleName($case->value, $warehouse))->first();
                foreach ($case->getPermissions() as $permissionName) {
                    if (class_basename($permissionName) == 'FulfilmentPermissionsEnum' && $permission = (new Permission())->where('name', FulfilmentPermissionsEnum::getPermissionName($permissionName->value, $fulfilment))->first()) {
                        $role->givePermissionTo($permission);
                    }
                }
            }
        }
    }

    public function processWarehouseScope(Warehouse $warehouse, RolesEnum $case): void
    {
        if (!$role = (new Role())->where('name', RolesEnum::getRoleName($case->value, $warehouse))->first()) {
            $role = Role::create(
                [
                    'name'       => RolesEnum::getRoleName($case->value, $warehouse),
                    'scope_type' => 'Warehouse',
                    'scope_id'   => $warehouse->id,
                    'group_id'   => $warehouse->group_id,
                ]
            );
        }
        $warehousePermissions = [];

        foreach ($case->getPermissions() as $permissionName) {
            if (class_basename($permissionName) == 'WarehousePermissionsEnum') {
                if ($permission = (new Permission())->where('name', WarehousePermissionsEnum::getPermissionName($permissionName->value, $warehouse))->first()) {
                    $warehousePermissions[] = $permission;
                }
            } else {
                foreach ($warehouse->fulfilments as $fulfilment) {
                    if ($permission = (new Permission())->where('name', FulfilmentPermissionsEnum::getPermissionName($permissionName->value, $fulfilment))->first()) {
                        $warehousePermissions[] = $permission;
                    }
                }
            }
        }
        $role->syncPermissions($warehousePermissions);
    }

    public string $commandSignature = 'warehouse:seed-permissions';

    public function asCommand(Command $command): int
    {
        foreach (Warehouse::all() as $warehouse) {
            $command->info("Seeding permissions for warehouse: $warehouse->name");
            setPermissionsTeamId($warehouse->group_id);
            $this->handle($warehouse);
        }

        return 0;
    }

}
