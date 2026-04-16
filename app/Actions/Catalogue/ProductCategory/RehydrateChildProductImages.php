<?php

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\Product\UI\HydrateProductImagesFromTradeUnits;
use App\Actions\OrgAction;
use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\ActionRequest;

class RehydrateChildProductImages extends OrgAction
{
    public function handle(ProductCategory $productCategory)
    {
        foreach ($productCategory->getProducts() as $product) {
            HydrateProductImagesFromTradeUnits::run($product);
        }
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request)
    {
        $this->initialisationFromShop($productCategory->shop, $request);

        $this->handle($productCategory);
    }
}
