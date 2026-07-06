<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Jul 2026 13:48:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews\Iris;

use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsAction;

class GetIrisReviews
{
    use AsAction;

    public function handle(Webpage $webpage): array
    {
        $model = $webpage->model;

        if ($model instanceof Product) {
            GetIrisProductReviews::run($model);
        } elseif ($model instanceof ProductCategory) {
            GetIrisProductCategoryReviews::run($model);
        } else {
            GetIrisShopReviews::run($webpage->shop);
        }
    }
}