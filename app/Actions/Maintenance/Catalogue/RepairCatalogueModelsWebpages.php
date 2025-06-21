<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 May 2025 15:29:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use App\Models\Catalogue\Collection as MyCollection;
use Illuminate\Database\Eloquent\Collection;

class RepairCatalogueModelsWebpages
{
    use WithActionUpdate;


    public string $commandSignature = 'cat:repair_webs';

    public function asCommand(): void
    {
        Product::orderBy('id')->chunk(1000, function (Collection $products) {
            foreach ($products as $product) {
                $webpage = Webpage::where('model_type', 'Product')
                    ->where('model_id', $product->id)
                    ->first();
                if ($webpage) {
                    $product->update([
                        'webpage_id' => $webpage->id,
                        'url'        => $webpage->url,
                    ]);
                } else {
                    $product->update([
                        'webpage_id' => null,
                        'url'        => null,
                    ]);
                }
            }
        });

        ProductCategory::orderBy('id')->chunk(100, function (Collection $productsCategories) {
            foreach ($productsCategories as $productsCategory) {
                $webpage = Webpage::where('model_type', 'ProductCategory')
                    ->where('model_id', $productsCategory->id)
                    ->first();
                if ($webpage) {
                    $productsCategory->update([
                        'webpage_id' => $webpage->id,
                        'url'        => $webpage->url,
                    ]);
                } else {
                    $productsCategory->update([
                        'webpage_id' => null,
                        'url'        => null,
                    ]);
                }
            }
        });

        MyCollection::orderBy('id')->chunk(100, function (Collection $collections) {
            foreach ($collections as $collection) {
                $webpage = Webpage::where('model_type', 'Collection')
                    ->where('model_id', $collection->id)
                    ->first();
                if ($webpage) {
                    $collection->update([
                        'webpage_id' => $webpage->id,
                        'url'        => $webpage->url,
                    ]);
                } else {
                    $collection->update([
                        'webpage_id' => null,
                        'url'        => null,
                    ]);
                }
            }
        });
    }

}
