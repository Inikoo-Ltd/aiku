<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Jul 2026 13:48:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews\Iris;

use App\Actions\IrisAction;
use App\Actions\Reviews\Iris\Traits\WithGetIrisReviewsTrait;
use App\Http\Resources\Catalogue\ReviewsInIrisResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetIrisReviews extends IrisAction
{
    use AsAction;
    use WithGetIrisReviewsTrait;

    public function handle(Webpage $webpage): array
    {
        $model = $webpage->shop;
        if ($webpage->model) {
            $model = $webpage->model;
        }

        if ($model instanceof Product) {
            $reviews = GetIrisProductReviews::run($model);
        } elseif ($model instanceof ProductCategory) {
            $reviews = GetIrisProductCategoryReviews::run($model);
        } else {
            $reviews = GetIrisShopReviews::run($webpage->shop);
        }

        $avgReview = $this->getBaseQuery($model)->avg('rating_main');

        return [
            'reviews'                           => ReviewsInIrisResource::collection($reviews)->response()->getData(true),
            'review_summary'                    => $avgReview ?? 0,
        ];
    }

    public function jsonResponse(array $reviewData): array
    {
        return $reviewData;
    }

    public function asController(Webpage $webpage, ActionRequest $request)
    {
        $this->initialisation($request);

        return $this->handle($webpage);
    }
}
