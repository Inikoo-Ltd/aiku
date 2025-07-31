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
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Exports\Marketing\ProductsInCollectionExport;
use App\Exports\Marketing\ProductsInProductCategoryExport;
use App\Exports\Marketing\ProductsInShopExport;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;

class SaveDataFeeds extends RetinaAction
{

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws \Exception
     */
    public function handle(Shop|ProductCategory|Collection $parent): void
    {
        $baseFilename = Str::snake(class_basename($parent));
        if ($parent instanceof ProductCategory) {
            $baseFilename = $parent->type->value;
        }

        $filename = $baseFilename.'_'.$parent->slug.'.csv';
        $path     = Str::snake(class_basename($parent)).'/'.$filename;

        if ($parent instanceof ProductCategory) {
            Excel::store(new ProductsInProductCategoryExport($parent), $path, 'data-feeds');
        } elseif ($parent instanceof Collection) {
            Excel::store(new ProductsInCollectionExport($parent), $path, 'data-feeds');
        } else {
            Excel::store(new ProductsInShopExport($parent), $path, 'data-feeds');
        }
    }


    public $commandSignature = 'data_feeds:save';

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function asCommand(Command $command): int
    {
        $shops = Shop::where('type', ShopTypeEnum::DROPSHIPPING)
            ->whereIn('state', [ShopStateEnum::OPEN, ShopStateEnum::CLOSING_DOWN])
            ->get();
        /** @var Shop $shop */
        foreach ($shops as $shop) {
            $this->handle($shop);
            $command->info('Shop: '.$shop->name);
            $productCategories = ProductCategory::where('shop_id', $shop->id)
                ->whereIn('state', [ProductCategoryStateEnum::ACTIVE, ProductCategoryStateEnum::DISCONTINUING])->get();
            /** @var ProductCategory $productCategory */
            foreach ($productCategories as $productCategory) {
                $this->handle($productCategory);
                $command->info('Product Category: '.$productCategory->slug);
            }
            $collections = Collection::where('shop_id', $shop->id)
                ->where('state', CollectionStateEnum::ACTIVE)
                ->get();
            foreach ($collections as $collection) {
                $this->handle($collection);
                $command->info('Collection: '.$collection->slug);
            }
        }


        return 0;
    }
}
