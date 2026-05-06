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
use App\Http\Resources\Catalogue\ProductsResource;
use App\Models\Catalogue\ProductCategory;

class GetProductCategoryRecomendation extends OrgAction
{

    public function handle(ProductCategory $productCategory): array
    {
        $productCategory->refresh();
        return [
            'id' => $productCategory->id,
            'data' => ProductsResource::collection($productCategory->relatedProducts),
            'editable' => false
        ];
    }
}
