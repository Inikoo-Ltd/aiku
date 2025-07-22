<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 08-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\Product;

use App\Actions\Catalogue\Asset\ExportProductsInProductCategory;
use App\Actions\Catalogue\Asset\ExportProductsInShop;
use App\Actions\RetinaAction;
use App\Exports\Marketing\ProductsInCollectionExport;
use App\Exports\Marketing\ProductsInProductCategoryExport;
use App\Exports\Marketing\ProductsInShopExport;
use App\Exports\Marketing\SingleProductExport;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadProduct extends RetinaAction
{
    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws \Exception
     */
    public function handle(Shop|ProductCategory|Product|Collection $parent, string $type): BinaryFileResponse|Response
    {
        $filename =  'products' . '_' . now()->format('Ymd');

        if ($type == 'products_images') {
            $filename .= '_images.zip';
            return response()->streamDownload(function () use ($parent) {
                ProductZipExport::make()->handle($parent);
            }, $filename);
        } else {
            $filename .= '.csv';
            if ($parent instanceof ProductCategory) {
                return Excel::download(new ProductsInProductCategoryExport($parent), $filename, null, [
                    'Content-Type' => 'text/csv',
                    'Cache-Control' => 'max-age=0',
                ]);
            } elseif ($parent instanceof Product) {
                return Excel::download(new SingleProductExport($parent), $filename, null, [
                    'Content-Type' => 'text/csv',
                    'Cache-Control' => 'max-age=0',
                ]);
            } elseif ($parent instanceof Collection) {
                return Excel::download(new ProductsInCollectionExport($parent), $filename, null, [
                    'Content-Type' => 'text/csv',
                    'Cache-Control' => 'max-age=0',
                ]);
            } else {
                return Excel::download(new ProductsInShopExport($parent), $filename, null, [
                    'Content-Type' => 'text/csv',
                    'Cache-Control' => 'max-age=0',
                ]);
            }
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
