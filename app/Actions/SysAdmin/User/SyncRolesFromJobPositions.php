<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 07 May 2024 10:16:58 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\SysAdmin\CleanUserCaches;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\HumanResources\JobPosition\JobPositionScopeEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\HumanResources\JobPosition;
use App\Models\SysAdmin\Role;
use App\Models\SysAdmin\User;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncRolesFromJobPositions
{
    use AsAction;

    public function handle(User $user): void
    {
        $roles = [];

        if ($user->status) {
            foreach ($user->employees as $employee) {
                foreach ($employee->jobPositions as $jobPosition) {
                    $roles = $this->getRoles($roles, $jobPosition);
                }
            }

            foreach ($user->pseudoJobPositions as $jobPosition) {
                $roles = $this->getRoles($roles, $jobPosition);
            }
        }

        $user->syncRoles($roles);

        if ($user->roles()->where('name', RolesEnum::GROUP_ADMIN->value)->exists()) {
            foreach ($user->group->organisations as $organisation) {
                UserAddRoles::run(
                    $user,
                    [
                        Role::where('name', RolesEnum::getRoleName(RolesEnum::ORG_ADMIN->value, $organisation))->first()
                    ],
                    setUserAuthorisedModels: false
                );
            }
            foreach ($user->group->shops as $shop) {
                if ($shop->type == ShopTypeEnum::FULFILMENT) {
                    UserAddRoles::run(
                        $user,
                        [
                            Role::where('name', RolesEnum::getRoleName(RolesEnum::FULFILMENT_WAREHOUSE_SUPERVISOR->value, $shop->fulfilment))->first()
                        ],
                        setUserAuthorisedModels: false
                    );
                    UserAddRoles::run(
                        $user,
                        [
                            Role::where('name', RolesEnum::getRoleName(RolesEnum::FULFILMENT_SHOP_SUPERVISOR->value, $shop->fulfilment))->first()
                        ],
                        setUserAuthorisedModels: false
                    );
                } else {
                    UserAddRoles::run(
                        $user,
                        [
                            Role::where('name', RolesEnum::getRoleName(RolesEnum::SHOP_ADMIN->value, $shop))->first()
                        ],
                        setUserAuthorisedModels: false
                    );
                }
            }
            foreach ($user->group->warehouses as $warehouse) {
                UserAddRoles::run(
                    $user,
                    [
                        Role::where('name', RolesEnum::getRoleName(RolesEnum::WAREHOUSE_ADMIN->value, $warehouse))->first()
                    ],
                    setUserAuthorisedModels: false
                );
            }
            foreach ($user->group->productions as $production) {
                UserAddRoles::run(
                    $user,
                    [
                        Role::where('name', RolesEnum::getRoleName(RolesEnum::MANUFACTURING_ADMIN->value, $production))->first()
                    ],
                    setUserAuthorisedModels: false
                );
            }
        }


        SetUserAuthorisedModels::run($user);
        CleanUserCaches::make()->clearPermissionsCache($user);


        $user->refresh();
    }


    private function getRoles($roles, JobPosition $jobPosition): array
    {
        $jobPosition->refresh();
        if ($jobPosition->scope == JobPositionScopeEnum::ORGANISATION || $jobPosition->scope == JobPositionScopeEnum::GROUP) {
            $roles = array_merge($roles, $jobPosition->roles()->pluck('id')->all());
        } else {
            $roles = array_merge(
                $roles,
                $this->getRolesOrganisationScopes($jobPosition)
            );
        }

        return $roles;
    }

    private function getRolesOrganisationScopes(JobPosition $jobPosition): array
    {
        $roles = [];
        $jobPosition->refresh();
        foreach ($jobPosition->roles as $role) {
            /** @noinspection PhpUndefinedFieldInspection */
            if (in_array($role->scope_id, $jobPosition->pivot->scopes[$role->scope_type])) {
                $roles[] = $role->id;
            }
        }

        return $roles;
    }

    public string $commandSignature = 'user:sync-roles-from-positions {user : User slug}';

    public function asCommand(Command $command): int
    {
        try {
            /** @var User $user */
            $user = User::where('slug', $command->argument('user'))->firstOrFail();
        } catch (Exception) {
            $command->error('User not found');

            return 1;
        }
        setPermissionsTeamId($user->group->id);
        $this->handle($user);

        return 0;
    }


}
