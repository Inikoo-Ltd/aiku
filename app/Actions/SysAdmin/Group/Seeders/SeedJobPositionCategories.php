<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 21:41:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Seeders;

use App\Actions\SysAdmin\JobPositionCategory\StoreJobPositionCategory;
use App\Actions\SysAdmin\JobPositionCategory\UpdateJobPositionCategory;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\JobPositionCategory;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedJobPositionCategories extends Seeder
{
    use AsAction;

    public function handle(Group $group): void
    {
        $jobPositionCategories = collect(config("blueprint.job_positions.positions"));


        foreach ($jobPositionCategories as $jobPositionCategoryData) {
            /** @var JobPositionCategory $jobPositionCategory */
            $jobPositionCategory = $group->jobPositionCategories()->where('code', $jobPositionCategoryData['code'])->first();
            if ($jobPositionCategory) {
                UpdateJobPositionCategory::make()->action(
                    $jobPositionCategory,
                    [
                        'name'       => $jobPositionCategoryData['name'],
                        'department' => Arr::get($jobPositionCategoryData, 'department'),
                        'team'       => Arr::get($jobPositionCategoryData, 'team'),
                        'scope'      => Arr::get($jobPositionCategoryData, 'scope')
                    ]
                );
            } else {

                StoreJobPositionCategory::make()->action(
                    $group,
                    [
                        'code'       => $jobPositionCategoryData['code'],
                        'name'       => $jobPositionCategoryData['name'],
                        'department' => Arr::get($jobPositionCategoryData, 'department'),
                        'team'       => Arr::get($jobPositionCategoryData, 'team'),
                        'scope'      => Arr::get($jobPositionCategoryData, 'scope')
                    ],
                );
            }
        }
    }


    public string $commandSignature = 'group:seed-job-position-categories {group?}';

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
                $command->info("Seeding job positions categories for: $group->name");
                $this->handle($group);
            }
        }


        return 0;
    }
}
