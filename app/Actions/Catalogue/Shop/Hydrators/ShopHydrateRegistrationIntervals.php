<?php

/*
 * author Arya Permana - Kirin
 * created on 14-03-2025-16h-40m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateRegistrationIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;
    use WithIntervalUniqueJob;

    public string $jobQueue = 'urgent';
    public string $commandSignature = 'hydrate:shop-registration-intervals {shop}';

    public function getJobUniqueId(int $shopId, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithIntervalFromId($shopId, $intervals, $doPreviousPeriods);
    }

    public function asCommand(Command $command): void
    {
        $shop = Shop::where('slug', $command->argument('shop'))->first();

        if ($shop) {
            $this->handle($shop->id);
        }
    }

    public function handle(int $shopId, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $shop = Shop::find($shopId);
        if (!$shop) {
            return;
        }

        $stats = [];

        $queryBase = Customer::where('shop_id', $shop->id)->selectRaw('count(*) as  sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'registrations_',
            dateField: 'registered_at',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $queryBaseWithOrders = Customer::where('shop_id', $shop->id)
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

        $queryBaseWithoutOrders = Customer::where('shop_id', $shop->id)
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

        $shop->orderingIntervals()->update($stats);
    }

}
