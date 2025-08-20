<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 15 Aug 2025 08:06:50 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Catalogue;

use App\Actions\IrisAction;
use App\Actions\Retina\Dropshipping\Product\DownloadProduct;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadIrisProduct extends IrisAction
{
    public function handle(Shop|ProductCategory|Product|Collection $parent): BinaryFileResponse|Response
    {
        return DownloadProduct::run($parent, 'products_csv');
    }

    public function asController(ActionRequest $request): BinaryFileResponse|Response
    {
        $this->initialisation($request);

        return $this->handle($this->shop);
    }

    public function inShop(Shop $shop, ActionRequest $request): BinaryFileResponse|Response
    {
        $this->initialisation($request);

        return $this->handle($shop);
    }

    public function inProductCategory(ProductCategory $productCategory, ActionRequest $request): BinaryFileResponse|Response
    {
        $this->initialisation($request);

        return $this->handle($productCategory);
    }

    public function inCollection(Collection $collection, ActionRequest $request): BinaryFileResponse|Response
    {
        $this->initialisation($request);

        return $this->handle($collection);
    }

    public function inProduct(Product $product, ActionRequest $request): BinaryFileResponse|Response
    {
        $this->initialisation($request);

        return $this->handle($product);
    }
}
