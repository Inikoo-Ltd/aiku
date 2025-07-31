<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 08-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\Product;

use App\Actions\RetinaAction;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Str;

class DownloadProduct extends RetinaAction
{
    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws \Exception
     */
    public function handle(Shop|ProductCategory|Product|Collection $parent, string $type): BinaryFileResponse|Response
    {
        $baseFilename = Str::snake(class_basename($parent));
        if ($parent instanceof ProductCategory) {
            $baseFilename = $parent->type->value;
        }

        $filename = $baseFilename . '_' . $parent->slug . '.csv';
        $path = Str::snake(class_basename($parent)) . '/' . $filename;

        if ($type == 'products_images') {
            $filename .= '_images.zip';
            return response()->streamDownload(function () use ($parent, $filename) {
                ProductZipExport::make()->handle($parent, $filename);
            }, $filename);
        } else {
            return Storage::disk('excel-exports')->download($path);
        }
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
