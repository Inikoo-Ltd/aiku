<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:59:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateSalesIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;
    use WithIntervalUniqueJob;

    public string $jobQueue = 'urgent';
    public string $commandSignature = 'hydrate:shop-sales-intervals {shop}';

    public function getJobUniqueId(Shop $shop, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithInterval($shop, $intervals, $doPreviousPeriods);
    }

    public function asCommand(Command $command): void
    {
        $shop = Shop::where('slug', $command->argument('shop'))->first();
        $intervalsExceptHistorical = DateIntervalEnum::allExceptHistorical();
        $this->handle($shop,$intervalsExceptHistorical,[]);
    }

    public function handle(Shop $shop, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $stats     = [];
        $queryBase = Invoice::where('in_process', false)->where('shop_id', $shop->id)->selectRaw('sum(net_amount) as  sum_aggregate  ');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'sales_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );


        $queryBase = Invoice::where('in_process', false)->where('shop_id', $shop->id)->selectRaw('sum(grp_net_amount) as  sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'sales_grp_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );


        $queryBase = Invoice::where('in_process', false)->where('shop_id', $shop->id)->selectRaw('sum(org_net_amount) as  sum_aggregate');

        $stats = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'sales_org_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        // basket
        $queryBase = Order::where('shop_id', $shop->id)->where('state', OrderStateEnum::CREATING)->selectRaw('sum(net_amount) as  sum_aggregate');

        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'baskets_created_',
            dateField: 'created_at',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'baskets_updated_',
            dateField: 'updated_at',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $queryBase = Order::where('shop_id', $shop->id)->where('state', OrderStateEnum::CREATING)->selectRaw('sum(grp_net_amount) as  sum_aggregate');

        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'baskets_created_grp_currency_',
            dateField: 'created_at',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'baskets_updated_grp_currency_',
            dateField: 'updated_at',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $queryBase = Order::where('shop_id', $shop->id)->where('state', OrderStateEnum::CREATING)->selectRaw('sum(org_net_amount) as  sum_aggregate');

        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'baskets_created_org_currency_',
            dateField: 'created_at',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'baskets_updated_org_currency_',
            dateField: 'updated_at',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $shop->salesIntervals()->update($stats);
    }



}
