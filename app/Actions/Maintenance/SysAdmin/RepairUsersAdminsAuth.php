<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 21 Feb 2025 13:55:15 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\SysAdmin;

use App\Actions\SysAdmin\User\UserAddRoles;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\SysAdmin\Role;
use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;

class RepairUsersAdminsAuth
{
    use WithActionUpdate;



    protected function handle(User $user, ?Command $command = null): User
    {

        $group = $user->group;
        setPermissionsTeamId($group->id);

        $isGroupAdmin = $user->roles->where('name', 'group-admin')->isNotEmpty();


        if ($command and $isGroupAdmin) {
            $command->line($user->username.' is Group Admin');
        }

        if ($isGroupAdmin) {
            foreach ($group->organisations as $organisation) {
                UserAddRoles::run($user, [
                    Role::where(
                        'name',
                        RolesEnum::getRoleName('org-admin', $organisation)
                    )->where('scope_id', $organisation->id)->first()
                ]);
            }
        }


        foreach ($group->organisations as $organisation) {
            $isOrganisationAdmin = $user->roles->where('name', "org-admin-$organisation->id")->isNotEmpty();
            if ($isOrganisationAdmin) {

                foreach ($organisation->warehouses as $warehouse) {
                    UserAddRoles::run($user, [
                        Role::where('name', RolesEnum::getRoleName(RolesEnum::WAREHOUSE_ADMIN->value, $warehouse))->first()
                    ]);
                }

                foreach ($organisation->productions as $production) {
                    UserAddRoles::run($user, [
                        Role::where('name', RolesEnum::getRoleName(RolesEnum::MANUFACTURING_ADMIN->value, $production))->first()
                    ]);
                }

                foreach ($organisation->shops as $shop) {
                    if ($shop->type == ShopTypeEnum::FULFILMENT) {
                        $fulfilment = $shop->fulfilment;
                        UserAddRoles::run($user, [
                            Role::where('name', RolesEnum::getRoleName(RolesEnum::FULFILMENT_SHOP_SUPERVISOR->value, $fulfilment))->first()
                        ]);
                        UserAddRoles::run($user, [
                            Role::where('name', RolesEnum::getRoleName(RolesEnum::FULFILMENT_WAREHOUSE_SUPERVISOR->value, $fulfilment))->first()
                        ]);
                    } else {
                        UserAddRoles::run($user, [
                            Role::where('name', RolesEnum::getRoleName(RolesEnum::SHOP_ADMIN->value, $shop))->first()
                        ]);
                    }
                }

            }
        }

        return $user;
    }

    public string $commandSignature = 'users:repair_admins_auth';

    public function asCommand(Command $command): void
    {
        Nightwatch::dontSample();
        $users = User::all();

        foreach ($users as $user) {
            $this->handle($user, $command);
        }
    }

}
