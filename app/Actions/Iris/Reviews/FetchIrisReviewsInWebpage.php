<?php

/*
 * Author Louis Perez
 * Created on 30-06-2026-13h-36m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Iris\Reviews;

use App\Actions\IrisAction;
use App\Actions\Reviews\UI\IndexReviewsInIris;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\ReviewsInIrisResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\ActionRequest;

class FetchIrisReviewsInWebpage extends IrisAction
{
    public function handle(Webpage $webpage, ActionRequest $request): array
    {
        $reviews    = [];
        $avgReview  = null;

        if ($webpage->model instanceof Product || ($webpage->model instanceof ProductCategory && $webpage->sub_type == ProductCategoryTypeEnum::FAMILY->value)) {
            $reviews = IndexReviewsInIris::run(parent: $webpage->model, prefix: $webpage->title);
            $avgReview = IndexReviewsInIris::make()->avgReview($webpage->model);
        } else {
            $reviews = IndexReviewsInIris::run(parent: $webpage->shop, prefix: $webpage->title);
            $avgReview = IndexReviewsInIris::make()->avgReview($webpage->shop);
        }
        return [
            'reviews'                           => ReviewsInIrisResource::collection($reviews)->response()->getData(true),
            'review_summary'                    => $avgReview ?? 0,
        ];
    }

    public function jsonResponse(array $data): array
    {
        return $data;
    }

    public function asController(Webpage $webpage, ActionRequest $request)
    {
        $this->initialisation($request);

        return $this->handle($webpage, $request);
    }
}
