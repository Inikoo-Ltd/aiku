<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Jul 2025 09:01:57 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateVisitorsIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;
    use WithIntervalUniqueJob;

    public string $jobQueue = 'low-priority';
    public string $commandSignature = 'hydrate:shop-visitor-intervals {shop}';

    public function getJobUniqueId(Shop $shop, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithInterval($shop, $intervals, $doPreviousPeriods);
    }

    public function asCommand(Command $command): void
    {
        $shop = Shop::where('slug', $command->argument('shop'))->first();

        $this->handle($shop);
    }

    public function handle(Shop $shop, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $stats = [];

        $queryBase = Customer::where('state', CustomerStateEnum::ACTIVE)->where('shop_id', $shop->id)->selectRaw('count(*) as sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'visitors_',
            dateField: 'created_at',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $shop->orderingIntervals()->update($stats);
    }
}
