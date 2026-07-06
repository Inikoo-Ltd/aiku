<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Jul 2026 13:52:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews\Iris;

use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Reviews\Review;
use App\Services\QueryBuilder;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisProductReviews
{
    use asObject;

    public function handle(Product $product): array
    {
        $setting=Arr::get($product->shop->settings,'reviews',[]);

        $queryBuilder = QueryBuilder::for(Review::class);
    }

}