<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Aug 2025 15:30:02 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\CRM\Customer;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterShopHydrateRegistrationIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;
    use WithIntervalUniqueJob;

    public string $jobQueue = 'urgent';
    public string $commandSignature = 'hydrate:master-shop-registration-intervals {masterShop}';

    public function getJobUniqueId(int $masterShopID, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithIntervalFromId($masterShopID, $intervals, $doPreviousPeriods);
    }

    public function asCommand(Command $command): void
    {
        $masterShop = MasterShop::where('id', $command->argument('masterShop'))->first();

        if ($masterShop) {
            $this->handle($masterShop->id);
        }
    }

    public function handle(int $masterShopID, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $masterShop = MasterShop::find($masterShopID);
        if (!$masterShop) {
            return;
        }

        $stats = [];

        $queryBase = Customer::where('master_shop_id', $masterShop->id)->selectRaw('count(*) as  sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'registrations_',
            dateField: 'registered_at',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $queryBaseWithOrders = Customer::where('master_shop_id', $masterShop->id)
            ->join('customer_stats', 'customers.id', '=', 'customer_stats.customer_id')
            ->where('customer_stats.number_orders', '>', 0)
            ->selectRaw('count(*) as sum_aggregate');
        $stats = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBaseWithOrders,
            statField: 'registrations_with_orders_',
            dateField: 'customers.registered_at',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $queryBaseWithoutOrders = Customer::where('master_shop_id', $masterShop->id)
            ->join('customer_stats', 'customers.id', '=', 'customer_stats.customer_id')
            ->where('customer_stats.number_orders', '=', 0)
            ->selectRaw('count(*) as sum_aggregate');
        $stats = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBaseWithoutOrders,
            statField: 'registrations_without_orders_',
            dateField: 'customers.registered_at',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $masterShop->orderingIntervals()->update($stats);
    }

}
