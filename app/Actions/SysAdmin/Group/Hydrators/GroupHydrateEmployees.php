<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 12 May 2024 15:03:42 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Employee\EmployeeTypeEnum;
use App\Models\SysAdmin\Group;
use App\Models\HumanResources\Employee;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateEmployees
{
    use AsAction;
    use WithEnumStats;

    private Group $group;
    public string $jobQueue = 'low-priority';
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
            'number_employees' => $group->employees()->count()
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'employees',
                field: 'state',
                enum: EmployeeStateEnum::class,
                models: Employee::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'employees',
                field: 'type',
                enum: EmployeeTypeEnum::class,
                models: Employee::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );
        $stats['number_employees_currently_working'] = $stats['number_employees_state_working'] + $stats['number_employees_state_leaving'];


        $group->humanResourcesStats()->update($stats);
    }
}
