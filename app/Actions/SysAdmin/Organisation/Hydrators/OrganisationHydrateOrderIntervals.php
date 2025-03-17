<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 17-03-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateOrderIntervals
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

    public function handle(Organisation $organisation): void
    {

        $stats = [];

        $queryBase = Order::where('organisation_id', $organisation->id)->where('state', OrderStateEnum::CREATING)->selectRaw(' count(*) as  sum_aggregate');
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


        $organisation->orderingIntervals()->update($stats);
    }

}
