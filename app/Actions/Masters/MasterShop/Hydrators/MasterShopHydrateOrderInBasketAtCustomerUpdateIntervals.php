<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Aug 2025 17:51:38 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateBasket;
use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\Masters\MasterShop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterShopHydrateOrderInBasketAtCustomerUpdateIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;
    use WithIntervalUniqueJob;
    use WithHydrateBasket;

    public string $jobQueue = 'sales';

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

        $masterShop->orderingIntervals()->update(
            $this->getBasketCountStats('updated_at', $masterShop, $intervals, $doPreviousPeriods),
        );


        $masterShop->salesIntervals()->update(
            $this->getBasketNetAmountStats('updated_at', 'grp', $masterShop, $intervals, $doPreviousPeriods),
        );
    }

}
