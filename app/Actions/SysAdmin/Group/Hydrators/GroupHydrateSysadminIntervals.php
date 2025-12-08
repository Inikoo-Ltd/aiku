<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateSysadminIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;

    public function getJobUniqueId(Group $group): string
    {
        return $group->id;
    }

    public function handle(Group $group): void
    {
        $stats = [];
        $queryBase = DB::table('user_requests')->where('group_id', $group->id)->selectRaw('count(*) as  sum_aggregate ');
        $stats = array_merge(
            $stats,
            $this->getIntervalsData(
                stats: $stats,
                queryBase: $queryBase,
                statField: 'user_requests_'
            ),
            $this->getPreviousYearsIntervalStats($queryBase, 'user_requests_'),
            $this->getPreviousQuartersIntervalStats($queryBase, 'user_requests_')
        );
        $group->sysadminIntervals->update($stats);
    }
}
