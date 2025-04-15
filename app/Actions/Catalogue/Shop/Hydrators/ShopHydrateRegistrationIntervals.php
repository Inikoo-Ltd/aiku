<?php

/*
 * author Arya Permana - Kirin
 * created on 14-03-2025-16h-40m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateRegistrationIntervals
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

    public function handle(Shop $shop): void
    {

        $stats = [];

        $queryBase = Customer::where('shop_id', $shop->id)->selectRaw('count(*) as  sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'registrations_'
        );

        $shop->orderingIntervals()->update($stats);
    }

}
