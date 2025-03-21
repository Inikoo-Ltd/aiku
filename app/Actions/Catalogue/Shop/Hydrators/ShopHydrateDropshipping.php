<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Mar 2023 04:18:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateDropshipping implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }

    public function handle(Shop $shop): void
    {
        $stats = [
            'number_portfolios'                                  => $shop->portfolios()->count(),
            'number_current_portfolios'                          => $shop->portfolios()->where('status', true)->count(),
            'number_products'                                    => $shop->products()->count(),
            'number_current_products'                            => $shop->products()->where('status', true)->count(),
        ];

        $shop->dropshippingStats()->update($stats);
    }
}
