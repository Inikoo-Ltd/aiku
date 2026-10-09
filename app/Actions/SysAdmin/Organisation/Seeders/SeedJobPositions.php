<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 21:46:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Seeders;

use App\Actions\HumanResources\JobPosition\StoreJobPosition;
use App\Actions\HumanResources\JobPosition\UpdateJobPosition;
use App\Actions\SysAdmin\User\SyncRolesFromJobPositions;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Catalogue\Shop;
use App\Models\HumanResources\JobPosition;
use App\Models\SysAdmin\JobPositionCategory;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\Role;
use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedJobPositions extends Seeder
{
    use AsAction;

    public function handle(Organisation $organisation): void
    {
        $jobPositions          = collect(config("blueprint.job_positions.positions"));
        $changedJobPositionIds = [];


        foreach ($jobPositions as $jobPositionData) {
            if (in_array($organisation->type, $jobPositionData['organisation_types'])) {
                $process = true;

                if (Arr::has($jobPositionData, 'has_shop_type')) {
                    foreach ($jobPositionData['has_shop_type'] as $shopType) {
                        if ($organisation->shops()->where('type', $shopType)->count() == 0) {
                            $process = false;
                        }
                    }
                }

                if ($process) {
                    $changedJobPositionIds[] = $this->processJobPosition($organisation, $jobPositionData);
                }
            }
        }

        $this->syncHolders($organisation, array_values(array_filter($changedJobPositionIds)));
    }


    private function processJobPosition(Organisation $organisation, array $jobPositionData): ?int
    {
        /** @var JobPosition $jobPosition */
        $jobPosition = $organisation->jobPositions()->where('code', $jobPositionData['code'])->first();
        if ($jobPosition) {
            UpdateJobPosition::make()->action(
                $jobPosition,
                [
                    'name' => $jobPositionData['name'],
                    'department' => Arr::get($jobPositionData, 'department'),
                    'team' => Arr::get($jobPositionData, 'team'),
                    'scope' => Arr::get($jobPositionData, 'scope')
                ]
            );
        } else {
            /** @var JobPositionCategory $jobPositionCategory */
            $jobPositionCategory = $organisation->group->jobPositionCategories()->where('code', $jobPositionData['code'])->first();
            $jobPosition         = StoreJobPosition::make()->action(
                $organisation,
                [
                    'group_job_position_id' => $jobPositionCategory->id,
                    'code'                  => $jobPositionData['code'],
                    'name'                  => $jobPositionData['name'],
                    'department'            => Arr::get($jobPositionData, 'department'),
                    'team'                  => Arr::get($jobPositionData, 'team'),
                    'scope'                 => Arr::get($jobPositionData, 'scope')
                ],
            );
        }


        $roles = [];
        foreach ($jobPositionData['roles'] as $case) {
            switch ($case->scope()) {
                case 'Group':
                    if ($role = new Role()->where('name', $case->value)->first()) {
                        $roles[] = $role->id;
                    }
                    break;
                case 'Organisation':
                    $roleName = RolesEnum::getRoleName($case->value, $organisation);
                    if ($role = new Role()->where('name', $roleName)->first()) {
                        $roles[] = $role->id;
                    }
                    break;
                case 'Warehouse':
                    foreach ($organisation->warehouses as $warehouse) {
                        $roleName = RolesEnum::getRoleName($case->value, $warehouse);
                        if ($role = new Role()->where('name', $roleName)->first()) {
                            $roles[] = $role->id;
                        }
                    }
                    break;
                case 'Shop':
                    foreach ($organisation->shops()->where('type', '!=', ShopTypeEnum::FULFILMENT)->get() as $shop) {
                        $roleName = RolesEnum::getRoleName($case->value, $shop);


                        if ($role = new Role()->where('name', $roleName)->first()) {
                            $roles[] = $role->id;
                        }
                    }
                    break;
                case 'Production':
                    foreach ($organisation->productions()->get() as $production) {
                        $roleName = RolesEnum::getRoleName($case->value, $production);
                        if ($role = new Role()->where('name', $roleName)->first()) {
                            $roles[] = $role->id;
                        }
                    }
                    break;
                case 'Fulfilment':
                    /** @var Shop $shop */
                    foreach ($organisation->shops()->where('type', ShopTypeEnum::FULFILMENT)->get() as $shop) {
                        $roleName = RolesEnum::getRoleName($case->value, $shop->fulfilment);
                        if ($role = new Role()->where('name', $roleName)->first()) {
                            $roles[] = $role->id;
                        }
                    }
                    break;

                default:
            }
        }

        $changes = $jobPosition->roles()->sync($roles);

        return $changes['attached'] || $changes['detached'] ? $jobPosition->id : null;
    }

    /**
     * @param array<int> $jobPositionIds
     */
    private function syncHolders(Organisation $organisation, array $jobPositionIds): void
    {
        if ($jobPositionIds === []) {
            return;
        }

        setPermissionsTeamId($organisation->group_id);
        User::whereHas('employees.jobPositions', fn ($query) => $query->whereIn('job_positions.id', $jobPositionIds))
            ->orWhereHas('pseudoJobPositions', fn ($query) => $query->whereIn('job_positions.id', $jobPositionIds))
            ->each(fn (User $user) => SyncRolesFromJobPositions::run($user));
    }


    public string $commandSignature = 'org:seed-job-positions {organisation?}';

    public function asCommand(Command $command): int
    {
        if ($command->argument('organisation')) {
            $organisation = Organisation::where('slug', $command->argument('organisation'))->first();
            if (!$organisation) {
                $command->error("Organisation not found");

                return 1;
            }
            $this->handle($organisation);

            return 0;
        } else {
            foreach (Organisation::all() as $organisation) {
                $command->info("Seeding job positions for organisation: $organisation->name");
                $this->handle($organisation);
            }
        }


        return 0;
    }
}
