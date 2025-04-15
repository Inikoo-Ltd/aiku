<?php

/*
 * author Arya Permana - Kirin
 * created on 14-03-2025-16h-44m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Group;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateRegistrationIntervals
{
    use AsAction;
    use WithIntervalsAggregators;

    public string $jobQueue = 'sales';

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
        $stats = [];

        $queryBase = Customer::where('group_id', $group->id)->selectRaw('count(*) as  sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'registrations_'
        );

        $group->orderingIntervals()->update($stats);
    }

}
