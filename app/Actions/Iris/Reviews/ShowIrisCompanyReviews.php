<?php

/*
 * @Author: andiferdiawan (https://github.com/andiferdiawan)
 * @Copyright: Copyright (c) 2026, andiferdiawan
 */

namespace App\Actions\Iris\Reviews;

use App\Actions\Catalogue\Review\UI\IndexReviewsInIris;
use App\Actions\IrisAction;
use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Http\Resources\Catalogue\ReviewsInIrisResource;
use App\Models\Reviews\Review;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowIrisCompanyReviews extends IrisAction
{
    public function handle(): array
    {
        $shop    = $this->shop;
        $reviews = IndexReviewsInIris::run(parent: $shop, prefix: 'company-reviews');

        $avgReview      = IndexReviewsInIris::make()->avgReview($shop);
        $totalReviews   = $reviews->total();
        $recommendCount = Review::query()
            ->where('shop_id', $shop->id)
            ->where('state', ReviewStateEnum::PUBLISHED)
            ->where('is_public', true)
            ->where('review_status', ReviewStatusEnum::APPROVED)
            ->where('rating_main', '>=', 4)
            ->count();

        $recommendPercent = $totalReviews > 0 ? (int) round(($recommendCount / $totalReviews) * 100) : 0;

        return [
            'type'              => 'company',
            'shop'              => [
                'name'  => $shop->name,
                'phone' => $shop->phone,
            ],
            'reviews'           => ReviewsInIrisResource::collection($reviews)->response()->getData(true),
            'avg_review'        => $avgReview ? round((float) $avgReview, 1) : 0.0,
            'total_reviews'     => $totalReviews,
            'recommend_percent' => $recommendPercent,
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
