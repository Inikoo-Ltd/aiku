<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Apr 2024 11:19:04 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateSales
{
    use AsAction;
    use WithIntervalsAggregators;

    public string $jobQueue = 'sales';

    private Organisation $organisation;

    public function __construct(Organisation $organisation)
    {
        $this->organisation = $organisation;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->organisation->id))->dontRelease()];
    }

    public function handle(Organisation $organisation, ?array $intervals = null, $doPreviousIntervals = null): void
    {
        if ($organisation->type == OrganisationTypeEnum::AGENT) {
            return;
        }

        $stats = [];

        $queryBase = Invoice::where('in_process', false)->where('organisation_id', $organisation->id)->selectRaw('sum(grp_net_amount) as  sum_aggregate ');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField:'sales_grp_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousIntervals
        );

        $queryBase = Invoice::where('in_process', false)->where('organisation_id', $organisation->id)->selectRaw(' sum(org_net_amount) as  sum_aggregate  ');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField:'sales_org_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousIntervals
        );

        // basket
        $queryBase = Order::where('organisation_id', $organisation->id)->where('state', OrderStateEnum::CREATING)->selectRaw('sum(grp_net_amount) as  sum_aggregate');

        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'baskets_created_grp_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousIntervals
        );

        $queryBase = Order::where('organisation_id', $organisation->id)->where('state', OrderStateEnum::CREATING)->selectRaw('sum(org_net_amount) as  sum_aggregate');

        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'baskets_created_org_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousIntervals
        );

        $organisation->salesIntervals()->update($stats);
    }


}
