<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 11:34:34 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Helpers\ClearCacheByWildcard;
use App\Actions\Traits\WithEnumStats;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateBrands implements ShouldBeUnique
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
            'number_brands' => $shop->brands()->count(),
            'number_current_brands' => $shop->brands()->where('is_for_sale', true)->count()
        ];

        $shopStats = $shop->stats;

        // Capture previous values to detect changes
        $oldNumberBrands = $shopStats->number_brands ?? null;
        $oldNumberCurrentBrands = $shopStats->number_current_brands ?? null;

        $shopStats->update($stats);

        // If any of the tracked values changed, clear the related website cache
        $changed = (
            $oldNumberBrands !== $stats['number_brands'] ||
            $oldNumberCurrentBrands !== $stats['number_current_brands']
        );

        if ($changed && $shop->website) {
            ClearCacheByWildcard::run("irisData:website:{$shop->website->id}:*");
        }




    }
}
