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
use App\Http\Resources\Catalogue\IrisAllReviewsResource;
use App\Models\Reviews\Review;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowIrisReviews extends IrisAction
{
    public function handle(string $tab): array
    {
        $shop    = $this->shop;
        $indexer = IndexReviewsInIris::make();

        $tabs = [
            'current'    => $tab,
            'navigation' => $this->getTabNavigation(),
        ];

        if ($tab === 'product') {
            $reviews   = $indexer->handleProductScopeReviews(shop: $shop, prefix: 'reviews');
            $avgReview = $indexer->avgProductScopeReview($shop);

            return [
                'tabs'          => $tabs,
                'shop'          => [
                    'name'  => $shop->name,
                    'phone' => $shop->phone,
                ],
                'reviews'       => IrisAllReviewsResource::collection($reviews)->response()->getData(true),
                'avg_review'    => $avgReview ? round((float) $avgReview, 1) : 0.0,
                'total_reviews' => $reviews->total(),
            ];
        }

        $reviews        = IndexReviewsInIris::run(parent: $shop, prefix: 'reviews');
        $avgReview      = $indexer->avgReview($shop);
        $totalReviews   = $reviews->total();
        $recommendCount = Review::query()
            ->where('shop_id', $shop->id)
            ->where('state', ReviewStateEnum::PUBLISHED)
            ->where('is_public', true)
            ->where('review_status', ReviewStatusEnum::APPROVED)
            ->where('rating_main', '>=', 4)
            ->count();

        return [
            'tab'               => 'company',
            'tabs'              => $tabs,
            'shop'              => [
                'name'  => $shop->name,
                'phone' => $shop->phone,
            ],
            'reviews'           => IrisAllReviewsResource::collection($reviews)->response()->getData(true),
            'avg_review'        => $avgReview ? round((float) $avgReview, 1) : 0.0,
            'total_reviews'     => $totalReviews,
            'recommend_percent' => $totalReviews > 0 ? (int) round(($recommendCount / $totalReviews) * 100) : 0,
        ];
    }

    public function getTabNavigation(): array
    {
        return [
            ['key' => 'company', 'label' => __('Company Reviews')],
            ['key' => 'product', 'label' => __('Product Reviews')],
        ];
    }

    public function htmlResponse(array $data): Response
    {
        return Inertia::render('AllReviews', $data)
            ->table(IndexReviewsInIris::make()->tableStructure(prefix: 'reviews'));
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);

        $tab = in_array($request->query('tab'), ['company', 'product']) ? $request->query('tab') : 'company';

        return $this->handle($tab);
    }
}
