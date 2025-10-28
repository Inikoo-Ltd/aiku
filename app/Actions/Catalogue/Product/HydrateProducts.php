<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Jun 2024 09:33:59 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateBarcodeFromTradeUnit;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateBrandsFromTradeUnits;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoFavourited;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoFavouritedInCategories;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoReminded;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoRemindedInCategories;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateForSale;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateGrossWeightFromTradeUnits;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateImages;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateMarketingDimensionFromTradeUnits;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateMarketingIngredientsFromTradeUnits;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateMarketingWeightFromTradeUnits;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateProductVariants;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateTagsFromTradeUnits;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateTradeUnitsFields;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\ModelHydrateSingleTradeUnits;
use App\Models\Catalogue\Product;

class HydrateProducts
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:products {organisations?*} {--S|shop= shop slug} {--s|slug=} ';

    public function __construct()
    {
        $this->model = Product::class;
    }

    public function handle(Product $product): void
    {

        if ($product->trashed()) {
            return;
        }

        ProductHydrateAvailableQuantity::run($product);
        ProductHydrateForSale::run($product);
        ProductHydrateProductVariants::run($product);
        ProductHydrateCustomersWhoFavourited::run($product);
        ProductHydrateCustomersWhoFavouritedInCategories::run($product);
        ProductHydrateCustomersWhoReminded::run($product);
        ProductHydrateCustomersWhoRemindedInCategories::run($product);
        ProductHydrateGrossWeightFromTradeUnits::run($product);
        ProductHydrateMarketingWeightFromTradeUnits::run($product);
        ProductHydrateMarketingDimensionFromTradeUnits::run($product);
        ProductHydrateMarketingIngredientsFromTradeUnits::run($product);
        ProductHydrateBarcodeFromTradeUnit::run($product);
        ProductHydrateImages::run($product);
        ProductHydrateTradeUnitsFields::run($product);
        ModelHydrateSingleTradeUnits::run($product);
        ProductHydrateBrandsFromTradeUnits::run($product);
        ProductHydrateTagsFromTradeUnits::run($product);
    }

}
