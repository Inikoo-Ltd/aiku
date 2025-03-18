<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:59:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateSales
{
    use AsAction;
    use WithIntervalsAggregators;

    public string $jobQueue = 'sales';


    private Shop $shop;

    public function __construct(Shop $shop)
    {
        $this->shop = $shop;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->shop->id))->dontRelease()];
    }

    public function handle(Shop $shop, ?array $intervals = null, $doPreviousIntervals = null): void
    {
        $stats     = [];
        $queryBase = Invoice::where('in_process', false)->where('shop_id', $shop->id)->selectRaw('sum(net_amount) as  sum_aggregate  ');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'sales_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousIntervals
        );

        $queryBase = Invoice::where('in_process', false)->where('shop_id', $shop->id)->selectRaw('sum(grp_net_amount) as  sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField:'sales_grp_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousIntervals
        );


        $queryBase = Invoice::where('in_process', false)->where('shop_id', $shop->id)->selectRaw('sum(org_net_amount) as  sum_aggregate');

        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'sales_org_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousIntervals
        );

        // basket
        $queryBase = Order::where('shop_id', $shop->id)->where('state', OrderStateEnum::CREATING)->selectRaw('sum(net_amount) as  sum_aggregate');

        $stats     = $this->getIntervalsData(
            stats: $stats,
            dateField: 'created_at',
            queryBase: $queryBase,
            statField: 'baskets_created_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousIntervals
        );

        $stats     = $this->getIntervalsData(
            stats: $stats,
            dateField: 'updated_at',
            queryBase: $queryBase,
            statField: 'baskets_updated_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousIntervals
        );

        $queryBase = Order::where('shop_id', $shop->id)->where('state', OrderStateEnum::CREATING)->selectRaw('sum(grp_net_amount) as  sum_aggregate');

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

        $queryBase = Order::where('shop_id', $shop->id)->where('state', OrderStateEnum::CREATING)->selectRaw('sum(org_net_amount) as  sum_aggregate');

        $stats     = $this->getIntervalsData(
            stats: $stats,
            dateField: 'created_at',
            queryBase: $queryBase,
            statField: 'baskets_created_org_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousIntervals
        );

        $stats     = $this->getIntervalsData(
            stats: $stats,
            dateField: 'updated_at',
            queryBase: $queryBase,
            statField: 'baskets_updated_org_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousIntervals
        );

        $shop->salesIntervals()->update($stats);
    }



}
