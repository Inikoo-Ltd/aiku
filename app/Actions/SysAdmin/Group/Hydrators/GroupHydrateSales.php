<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Apr 2024 11:19:04 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Accounting\Invoice;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateSales
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

    public function handle(Group $group, ?array $intervals = null, $doPreviousIntervals = null): void
    {
        $stats = [];

        $queryBase = Invoice::where('in_process', false)->where('group_id', $group->id)->selectRaw('sum(grp_net_amount) as sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'sales_grp_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousIntervals
        );

        // basket
        $queryBase = Order::where('group_id', $group->id)->where('state', OrderStateEnum::CREATING)->selectRaw('sum(grp_net_amount) as  sum_aggregate');

        $stats     = $this->getIntervalsData(
            stats: $stats,
            dateField: 'created_at',
            queryBase: $queryBase,
            statField: 'baskets_created_grp_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousIntervals
        );

        $stats     = $this->getIntervalsData(
            stats: $stats,
            dateField: 'updated_at',
            queryBase: $queryBase,
            statField: 'baskets_updated_grp_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousIntervals
        );


        $group->salesIntervals()->update($stats);
    }


}
