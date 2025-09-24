<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 21:41:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Seeders;

use App\Actions\HumanResources\JobPosition\StoreJobPositionScopeGroup;
use App\Actions\HumanResources\JobPosition\UpdateJobPositionScopeGroup;
use App\Enums\HumanResources\JobPosition\JobPositionScopeEnum;
use App\Models\HumanResources\JobPosition;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedJobPositionsScopeGroup
{
    use AsAction;

    public function handle(Group $group): void
    {
        $jobPositions = collect(config("blueprint.job_positions.positions"));


        foreach ($jobPositions as $jobPositionData) {

            if ($jobPositionData['scope'] == JobPositionScopeEnum::GROUP) {

                $this->processJobPosition($group, $jobPositionData);

            }
        }
    }


    private function processJobPosition(Group $group, array $jobPositionData): void
    {

        /** @var JobPosition $jobPosition */
        $jobPosition = $group->jobPositions()->where('scope', 'group')->where('code', $jobPositionData['code'])->first();
        if ($jobPosition) {
            UpdateJobPositionScopeGroup::make()->action(
                $jobPosition,
                [
                    'name'       => $jobPositionData['name'],
                    'department' => Arr::get($jobPositionData, 'department'),
                    'team'       => Arr::get($jobPositionData, 'team'),
                    'scope'      => Arr::get($jobPositionData, 'scope')
                ]
            );
        } else {
            $jobPositionCategory = $group->jobPositionCategories()->where('code', $jobPositionData['code'])->first();

            $jobPosition        = StoreJobPositionScopeGroup::make()->action(
                $group,
                [
                    'group_job_position_id' => $jobPositionCategory->id,
                    'code'                 => $jobPositionData['code'],
                    'name'                 => $jobPositionData['name'],
                    'department'           => Arr::get($jobPositionData, 'department'),
                    'team'                 => Arr::get($jobPositionData, 'team'),
                    'scope'                => Arr::get($jobPositionData, 'scope')
                ],
            );
        }


        $roles = [];
        foreach ($jobPositionData['roles'] as $case) {

            if ($role = (new Role())->where('name', $case->value)->first()) {
                $roles[] = $role->id;
            }

        }

        $jobPosition->roles()->sync($roles);
    }


    public string $commandSignature = 'group:seed-job-positions {group?}';

    public function asCommand(Command $command): int
    {
        if ($command->argument('group')) {
            $group = Group::where('slug', $command->argument('group'))->first();
            if (!$group) {
                $command->error("Group not found");

                return 1;
            }
            $this->handle($group);

            return 0;
        } else {
            foreach (Group::all() as $group) {
                $command->info("Seeding job positions for group: $group->name");
                $this->handle($group);
            }
        }


        return 0;
    }
}
