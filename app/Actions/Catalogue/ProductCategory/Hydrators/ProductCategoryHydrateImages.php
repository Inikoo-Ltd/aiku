<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Jul 2025 19:25:57 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Actions\Traits\WithImageStats;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductCategoryHydrateImages implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithImageStats;

    public function getJobUniqueId(ProductCategory $productCategory): string
    {
        return $productCategory->id;
    }

    public function handle(ProductCategory $productCategory): void
    {
        $stats = $this->calculateImageStatsUsingDB(
            model: $productCategory,
            modelType: 'ProductCategory',
        );

        $productCategory->stats->update($stats);
    }
}
