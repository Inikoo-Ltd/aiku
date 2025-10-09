<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Apr 2024 11:19:04 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\Accounting\Invoice;
use App\Models\Masters\MasterShop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterShopHydrateSalesIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;
    use WithIntervalUniqueJob;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(int $masterShopID, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithIntervalFromId($masterShopID, $intervals, $doPreviousPeriods);
    }


    public function handle(int $masterShopID, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $masterShop = MasterShop::find($masterShopID);
        if (!$masterShop) {
            return;
        }

        $stats = [];

        $queryBase = Invoice::where('in_process', false)
            ->where('master_shop_id', $masterShop->id)
            ->selectRaw('sum(grp_net_amount) as  sum_aggregate ');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'sales_grp_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );


        $masterShop->salesIntervals()->update($stats);
    }


}
