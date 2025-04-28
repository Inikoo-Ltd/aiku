<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 17-03-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateOrderIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;
    use WithIntervalUniqueJob;

    public string $jobQueue = 'sales';

    public function getJobUniqueId(Group $group, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithInterval($group, $intervals, $doPreviousPeriods);
    }

    public function handle(Group $group, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $stats = [];

        $queryBase = Order::where('group_id', $group->id)->selectRaw(' count(*) as  sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'orders_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );
        $group->orderingIntervals()->update($stats);
    }

}
