<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Apr 2025 01:36:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateBasket;
use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateOrderInBasketAtCustomerUpdateIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;
    use WithIntervalUniqueJob;
    use WithHydrateBasket;

    public string $jobQueue = 'sales';

    public function getJobUniqueId(Shop $shop, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithInterval($shop, $intervals, $doPreviousPeriods);
    }

    public function handle(Shop $shop, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $shop->orderingIntervals()->update(
            $this->getBasketCountStats('updated_at', $shop, $intervals, $doPreviousPeriods),
        );


        $shop->salesIntervals()->update(
            $this->getBasketNetAmountStats('updated_at', 'shop', $shop, $intervals, $doPreviousPeriods),
        );

        $shop->salesIntervals()->update(
            $this->getBasketNetAmountStats('updated_at', 'org', $shop, $intervals, $doPreviousPeriods),
        );

        $shop->salesIntervals()->update(
            $this->getBasketNetAmountStats('updated_at', 'grp', $shop, $intervals, $doPreviousPeriods),
        );
    }

}
