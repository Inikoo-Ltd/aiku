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
use App\Exports\Marketing\ProductsInCollectionExport;
use App\Exports\Marketing\ProductsInProductCategoryExport;
use App\Exports\Marketing\ProductsInShopExport;
use App\Exports\Marketing\SingleProductExport;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;

class SaveProductToStorage extends RetinaAction
{
    public $commandSignature = 'exports:save-products';

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws \Exception
     */
    public function handle(Shop|ProductCategory|Product|Collection $parent): void
    {
        $baseFilename = Str::snake(class_basename($parent));
        if ($parent instanceof ProductCategory) {
            $baseFilename = $parent->type->value;
        }

        $filename = $baseFilename . '_' . $parent->slug . '.csv';
        $path = Str::snake(class_basename($parent)) . '/' . $filename;

        if ($parent instanceof ProductCategory) {
            Excel::store(new ProductsInProductCategoryExport($parent), $path, 'excel-exports');
        } elseif ($parent instanceof Product) {
            Excel::store(new SingleProductExport($parent), $path, 'excel-exports');
        } elseif ($parent instanceof Collection) {
            Excel::store(new ProductsInCollectionExport($parent), $path, 'excel-exports');
        } else {
            Excel::store(new ProductsInShopExport($parent), $path, 'excel-exports');
        }
    }

    public function asCommand()
    {
        // Process all shops
        $shops = Shop::all();
        foreach ($shops as $shop) {
            $this->handle($shop);
        }

        // Process all product categories
        $productCategories = ProductCategory::all();
        foreach ($productCategories as $productCategory) {
            $this->handle($productCategory);
        }

        // Process all products
        $products = Product::all();
        foreach ($products as $product) {
            $this->handle($product);
        }

        // Process all collections
        $collections = Collection::all();
        foreach ($collections as $collection) {
            $this->handle($collection);
        }
    }
}
