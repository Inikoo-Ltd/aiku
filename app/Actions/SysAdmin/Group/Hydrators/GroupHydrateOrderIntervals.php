<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 17-03-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateOrderIntervals
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

        $queryBase = Order::where('group_id', $group->id)->where('state', OrderStateEnum::CREATING)->selectRaw(' count(*) as  sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            dateField: 'created_at',
            queryBase: $queryBase,
            statField: 'baskets_created_'
        );
        $stats     = $this->getIntervalsData(
            stats: $stats,
            dateField: 'updated_at',
            queryBase: $queryBase,
            statField: 'baskets_updated_'
        );


        $group->orderingIntervals()->update($stats);
    }

}
