<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Apr 2024 15:33:01 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Billables\Rental\RentalStateEnum;
use App\Models\Billables\Rental;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateRentals implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }
    public function handle(Shop $shop): void
    {

        $stats         = [];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'rentals',
                field: 'state',
                enum: RentalStateEnum::class,
                models: Rental::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id);
                }
            )
        );

        $shop->stats()->update($stats);
    }

}
