<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Jul 2025 19:25:17 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Actions\Traits\WithImageStats;
use App\Models\Catalogue\Product;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydrateImages implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithImageStats;

    public function getJobUniqueId(Product $product): string
    {
        return $product->id;
    }

    public function handle(Product $product): void
    {
        // Calculate image statistics using the trait method
        $stats = $this->calculateImageStatsUsingDB(
            model: $product,
            modelType: 'Product',
        );

        // Update product stats
        $product->stats->update($stats);
    }
}
