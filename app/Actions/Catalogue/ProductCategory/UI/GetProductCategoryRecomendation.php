<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\OrgAction;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Facades\DB;

class GetProductCategoryRecomendation extends OrgAction
{
    public function handle(ProductCategory $productCategory): \Illuminate\Support\Collection
     {
        return DB::table('product_category_has_related_products')
            ->leftJoin('products', 'product_id', 'products.id')
            ->where('product_category_id', $productCategory->id)
            ->orderBy('position')->get();
    }
}
