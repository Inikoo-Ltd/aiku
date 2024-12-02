<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 07 May 2024 22:41:39 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardAllowanceTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum;
use App\Models\Production\ManufactureTask;
use App\Models\SysAdmin\Group;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateManufactureTasks
{
    use AsAction;
    use WithEnumStats;

    private Group $group;

    public function __construct(Group $group)
    {
        $this->group = $group;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->group->id))->dontRelease()];
    }


    public function handle(Group $group): void
    {
        $stats = [
            'number_manufacture_tasks' => $group->manufactureTasks()->count()
        ];



        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'manufacture_tasks',
                field: 'operative_reward_terms',
                enum: ManufactureTaskOperativeRewardTermsEnum::class,
                models: ManufactureTask::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'manufacture_tasks',
                field: 'operative_reward_allowance_type',
                enum: ManufactureTaskOperativeRewardAllowanceTypeEnum::class,
                models: ManufactureTask::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );


        $group->manufactureStats()->update($stats);
    }
}
