<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 07 May 2024 10:16:58 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\Chat\Agent\RevokeChatAgentAccess;
use App\Actions\SysAdmin\CleanUserCaches;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\JobPosition\JobPositionScopeEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\HumanResources\JobPosition;
use App\Models\Inventory\Warehouse;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\Role;
use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use OwenIt\Auditing\Events\AuditCustom;

class SyncRolesFromJobPositions
{
    use AsAction;

    public function handle(User $user): void
    {
        $rolesBefore = $user->roles()->pluck('name')->sort()->values()->all();
        $roles       = [];

        if ($user->status) {
            foreach ($user->employees()->wherePivot('status', true)->where('employees.state', '!=', EmployeeStateEnum::LEFT)->get() as $employee) {
                foreach ($employee->jobPositions as $jobPosition) {
                    $roles = $this->getRoles($roles, $jobPosition);
                }
            }

            foreach ($user->pseudoJobPositions as $jobPosition) {
                $roles = $this->getRoles($roles, $jobPosition);
            }
        }

        $user->syncRoles($this->withoutRolesCoveredByAdmin($roles));

        foreach (
            $user->roles()->where(function ($query) {
                $query->where('name', 'like', 'accounting-supervisor-%')
                    ->orWhere('name', 'like', 'accounting-clerk-%');
            })->get() as $accountingRole
        ) {
            $organisation = Organisation::find($accountingRole->scope_id);
            foreach ($organisation->warehouses as $warehouse) {
                UserAddRoles::run(
                    $user,
                    [
                        Role::where('name', RolesEnum::getRoleName(RolesEnum::WAREHOUSE_VIEWER->value, $warehouse))->first()
                    ],
                    setUserAuthorisedModels: false
                );
            }

            if (str_starts_with($accountingRole->name, 'accounting-supervisor-')) {
                foreach ($organisation->shops()->where('type', ShopTypeEnum::B2B)->get() as $shop) {
                    UserAddRoles::run(
                        $user,
                        [
                            Role::where('name', RolesEnum::getRoleName(RolesEnum::CUSTOMER_SERVICE_VIEWER->value, $shop))->first()
                        ],
                        setUserAuthorisedModels: false
                    );
                }
            }
        }

        if ($user->roles()->whereIn('name', [RolesEnum::GROUP_ADMIN->value, RolesEnum::HELP_DESK_CLERK->value, RolesEnum::HELP_DESK_SUPERVISOR->value, RolesEnum::QA->value])->exists()) {
            foreach ($user->group->organisations as $organisation) {
                $this->addRole($user, RolesEnum::ORG_ADMIN, $organisation);
            }
        }

        foreach ($user->roles()->where('name', 'like', RolesEnum::ORG_ADMIN->value.'-%')->where('scope_type', 'Organisation')->get() as $orgAdminRole) {
            $this->addAdminRolesInOrganisation($user, Organisation::find($orgAdminRole->scope_id));
        }


        SetUserAuthorisedModels::run($user);
        CleanUserCaches::make()->clearPermissionsCache($user);


        $user->refresh();

        $rolesAfter = $user->roles()->pluck('name')->sort()->values()->all();
        if ($rolesBefore !== $rolesAfter) {
            $user->auditEvent     = 'roles';
            $user->isCustomEvent  = true;
            $user->auditCustomOld = ['removed' => array_values(array_diff($rolesBefore, $rolesAfter))];
            $user->auditCustomNew = ['added' => array_values(array_diff($rolesAfter, $rolesBefore))];
            Event::dispatch(new AuditCustom($user));
        }

        // Losing the customer service position takes chat with it: the conversations this
        // person can no longer work go back to their shop's queue rather than staying in a
        // name nobody can act on, and the agent profile is suspended once nothing is left.
        // withTrashed: a suspended profile has to be found here too, or regaining the
        // position would never bring it back.
        if ($chatAgent = $user->chatAgent()->withTrashed()->first()) {
            RevokeChatAgentAccess::run($chatAgent);
        }
    }


    /**
     * Admins are given everything below them, so positions held alongside are dropped: a customer
     * service position made an admin a chat agent, routed chats and rung for them.
     *
     * @param array<int> $roleIds
     * @return array<int>
     */
    private function withoutRolesCoveredByAdmin(array $roleIds): array
    {
        $roles = Role::whereIn('id', $roleIds)->get();

        if ($roles->contains('name', RolesEnum::GROUP_ADMIN->value)) {
            return $roles->where('scope_type', 'Group')->pluck('id')->all();
        }

        $isOrgAdmin           = fn (Role $role) => $role->scope_type === 'Organisation' && $role->name === RolesEnum::ORG_ADMIN->value.'-'.$role->scope_id;
        $adminOrganisationIds = $roles->filter($isOrgAdmin)->pluck('scope_id')->all();
        if ($adminOrganisationIds === []) {
            return $roleIds;
        }

        $organisationIdsByScope = collect([
            'Shop'       => Shop::class,
            'Warehouse'  => Warehouse::class,
            'Fulfilment' => Fulfilment::class,
            'Production' => Production::class,
        ])->map(fn (string $model, string $scopeType) => $model::whereIn('id', $roles->where('scope_type', $scopeType)->pluck('scope_id'))->pluck('organisation_id', 'id'));

        return $roles->reject(function (Role $role) use ($isOrgAdmin, $adminOrganisationIds, $organisationIdsByScope) {
            $organisationId = $role->scope_type === 'Organisation' ? $role->scope_id : $organisationIdsByScope->get($role->scope_type)?->get($role->scope_id);

            return !$isOrgAdmin($role) && in_array($organisationId, $adminOrganisationIds);
        })->pluck('id')->all();
    }

    private function addAdminRolesInOrganisation(User $user, Organisation $organisation): void
    {
        foreach ($organisation->shops as $shop) {
            if ($shop->type == ShopTypeEnum::FULFILMENT) {
                $this->addRole($user, RolesEnum::FULFILMENT_WAREHOUSE_SUPERVISOR, $shop->fulfilment);
                $this->addRole($user, RolesEnum::FULFILMENT_SHOP_SUPERVISOR, $shop->fulfilment);
            } else {
                $this->addRole($user, RolesEnum::SHOP_ADMIN, $shop);
            }
        }
        foreach ($organisation->warehouses as $warehouse) {
            $this->addRole($user, RolesEnum::WAREHOUSE_ADMIN, $warehouse);
        }
        foreach ($organisation->productions as $production) {
            $this->addRole($user, RolesEnum::MANUFACTURING_ADMIN, $production);
        }
    }

    private function addRole(User $user, RolesEnum $role, Organisation|Shop|Warehouse|Fulfilment|Production $scope): void
    {
        UserAddRoles::run(
            $user,
            [
                Role::where('name', RolesEnum::getRoleName($role->value, $scope))->first()
            ],
            setUserAuthorisedModels: false
        );
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
            if (in_array($role->scope_id, Arr::get($jobPosition->pivot->scopes, $role->scope_type, []))) {
                $roles[] = $role->id;
            }
        }

        return $roles;
    }

    public string $commandSignature = 'user:sync-roles-from-positions {user? : User slug, all users when omitted} {--N|dry_run : Show roles that would be added and removed}';

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $users = User::query();
        if ($command->argument('user')) {
            $users->where('slug', $command->argument('user'));
            if (!$users->exists()) {
                $command->error('User not found');

                return 1;
            }
        }

        $users->each(function (User $user) use ($command) {
            setPermissionsTeamId($user->group_id);
            $before = $user->roles()->pluck('name')->sort()->values();
            if ($command->option('dry_run')) {
                DB::beginTransaction();
                $this->handle($user);
                $after = $user->roles()->pluck('name')->sort()->values();
                DB::rollBack();
            } else {
                $this->handle($user);
                CleanUserCaches::run($user);
                $after = $user->roles()->pluck('name')->sort()->values();
            }

            $added   = $after->diff($before);
            $removed = $before->diff($after);
            if ($added->isNotEmpty() || $removed->isNotEmpty()) {
                $command->line($user->slug.'  +'.$added->implode(',').'  -'.$removed->implode(','));
            }
        });

        return 0;
    }


}
