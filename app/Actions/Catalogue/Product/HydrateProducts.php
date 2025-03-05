<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Jun 2024 09:33:59 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoFavourited;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoFavouritedInCategories;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoReminded;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoRemindedInCategories;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateGrossWeightFromTradeUnits;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateProductVariants;
use App\Models\Catalogue\Product;

class HydrateProducts
{
    public string $commandSignature = 'hydrate:products {organisations?*} {--S|shop= shop slug} {--s|slugs=} ';


    public function handle(Product $product): void
    {
        ProductHydrateProductVariants::run($product);
        ProductHydrateCustomersWhoFavourited::run($product);
        ProductHydrateCustomersWhoFavouritedInCategories::run($product);
        ProductHydrateCustomersWhoReminded::run($product);
        ProductHydrateCustomersWhoRemindedInCategories::run($product);
        ProductHydrateGrossWeightFromTradeUnits::run($product);

    }

}
