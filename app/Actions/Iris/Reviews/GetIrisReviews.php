<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Jul 2026 13:48:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Reviews;

use App\Actions\IrisAction;
use App\Http\Resources\Catalogue\ReviewsInIrisResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetIrisReviews extends IrisAction
{
    use AsAction;

    public function handle(Webpage $webpage): ?LengthAwarePaginator
    {
        $model = $webpage->model;

        $reviews = null;

        if ($model instanceof Product) {
            $reviews = GetIrisProductReviews::run($model);
        } elseif ($model instanceof ProductCategory) {
            $reviews = GetIrisProductCategoryReviews::run($model);
        } else {
            $reviews = GetIrisShopReviews::run($webpage->shop);
        }

        return $reviews;
    }

    public function jsonResponse(LengthAwarePaginator $reviews): AnonymousResourceCollection
    {
        return ReviewsInIrisResource::collection($reviews);
    }

    public function asController(Webpage $webpage, ActionRequest $request)
    {
        $this->initialisation($request);

        return $this->handle($webpage);
    }
}
