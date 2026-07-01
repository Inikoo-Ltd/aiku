<?php

/*
 * @Author: andiferdiawan (https://github.com/andiferdiawan)
 * @Copyright: Copyright (c) 2026, andiferdiawan
 */

namespace App\Actions\Iris\Reviews;

use App\Actions\Catalogue\Review\UI\IndexReviewsInIris;
use App\Actions\IrisAction;
use App\Http\Resources\Catalogue\ReviewsInIrisResource;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowIrisProductReviews extends IrisAction
{
    public function handle(): array
    {
        $shop    = $this->shop;
        $indexer = IndexReviewsInIris::make();
        $reviews = $indexer->handleProductScopeReviews(shop: $shop, prefix: 'product-reviews');

        $avgReview    = $indexer->avgProductScopeReview($shop);
        $totalReviews = $reviews->total();

        return [
            'type'          => 'product',
            'shop'          => [
                'name'  => $shop->name,
                'phone' => $shop->phone,
            ],
            'reviews'       => ReviewsInIrisResource::collection($reviews)->response()->getData(true),
            'avg_review'    => $avgReview ? round((float) $avgReview, 1) : 0.0,
            'total_reviews' => $totalReviews,
        ];
    }

    public function htmlResponse(array $data): Response
    {
        return Inertia::render('AllReviews', $data);
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle();
    }
}
