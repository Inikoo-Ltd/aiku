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
    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws \Exception
     */
    public function handle(Shop|ProductCategory|Product|Collection $parent, string $type): BinaryFileResponse|Response
    {
        return DownloadProduct::run($parent, $type);
    }
    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */

    public function asController(Shop $shop, ActionRequest $request): BinaryFileResponse|Response
    {

        $type = $request->query('type', 'products_csv');

        if (!in_array($type, ['products_csv', 'products_images'])) {
            abort(404);
        }


        $this->initialisation($request);

        return $this->handle($shop, $type);
    }

    public function inProductCategory(ProductCategory $productCategory, ActionRequest $request): BinaryFileResponse|Response
    {
        $type = $request->query('type', 'products_csv');

        if (!in_array($type, ['products_csv', 'products_images'])) {
            abort(404);
        }

        $this->initialisation($request);

        return $this->handle($productCategory, $type);
    }

    public function inCollection(Collection $collection, ActionRequest $request): BinaryFileResponse|Response
    {
        $type = $request->query('type', 'products_csv');

        if (!in_array($type, ['products_csv', 'products_images'])) {
            abort(404);
        }

        $this->initialisation($request);

        return $this->handle($collection, $type);
    }

    public function inProduct(Product $product, ActionRequest $request): BinaryFileResponse|Response
    {
        $type = $request->query('type', 'products_csv');

        if (!in_array($type, ['products_csv', 'products_images'])) {
            abort(404);
        }


        $this->initialisation($request);

        return $this->handle($product, $type);
    }
}
