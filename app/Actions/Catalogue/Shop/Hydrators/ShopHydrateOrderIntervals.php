<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 17-03-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateOrderIntervals
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

        $queryBase = Order::where('shop_id', $shop->id)->where('state', OrderStateEnum::CREATING)->selectRaw(' count(*) as  sum_aggregate');
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


        $shop->orderingIntervals()->update($stats);
    }
}
