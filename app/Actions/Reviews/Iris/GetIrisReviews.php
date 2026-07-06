<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Jul 2026 13:48:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews\Iris;

use App\Http\Resources\Catalogue\ReviewsInIrisResource;
use App\Http\Resources\Inventory\LocationsResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;

class GetIrisReviews
{
    use AsAction;

    public function handle(Webpage $webpage): ?LengthAwarePaginator
    {
        $model = $webpage->model;

        if ($model instanceof Product) {
            GetIrisProductReviews::run($model);
        } elseif ($model instanceof ProductCategory) {
           // GetIrisProductCategoryReviews::run($model);
        } else {
           // GetIrisShopReviews::run($webpage->shop);
        }
        return null;
    }

    public function jsonResponse(LengthAwarePaginator $reviews): AnonymousResourceCollection
    {
        return ReviewsInIrisResource::collection($reviews);
    }
}