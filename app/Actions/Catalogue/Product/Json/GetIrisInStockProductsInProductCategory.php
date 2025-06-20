<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Jun 2025 14:43:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;

class GetIrisInStockProductsInProductCategory extends IrisAction
{
    use WithIrisProductsInWebpage;

    public function asController(ProductCategory $productCategory, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return GetIrisProductsInProductCategory::run(productCategory: $productCategory, stockMode: 'in_stock');
    }

}
