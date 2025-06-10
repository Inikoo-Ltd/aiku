<?php

/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-13h-11m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateWebpages;
use App\Actions\OrgAction;
use App\Actions\Web\Webpage\Hydrators\WebpageHydrateProducts;
use App\Models\Catalogue\Product;
use App\Models\Web\Webpage;

class DetachProductFromWebpage extends OrgAction
{
    public function handle(Webpage $webpage, Product $product): Product
    {
        $webpage->webpageHasProducts()
            ->where('product_id', $product->id)
            ->delete();

        $webpage->refresh();
        $product->refresh();

        ProductHydrateWebpages::dispatch($product);
        WebpageHydrateProducts::dispatch($webpage);

        return $product;
    }

    public function action(Webpage $webpage, Product $product): Product
    {
        $this->asAction       = true;
        $this->initialisationFromShop($webpage->shop, []);

        return $this->handle($webpage, $product);
    }

    public function asController(Webpage $webpage, Product $product)
    {
        $this->initialisationFromShop($webpage->shop, []);

        $this->handle($webpage, $product);
    }
}
