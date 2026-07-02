<?php

/*
 * @Author: andiferdiawan (https://github.com/andiferdiawan)
 * @Copyright: Copyright (c) 2026, andiferdiawan
 */

namespace App\Actions\Iris\Reviews;

use App\Actions\Catalogue\Review\UI\IndexReviewsInIris;
use App\Actions\IrisAction;
use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Http\Resources\Catalogue\IrisAllReviewsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Reviews\Review;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowIrisReviews extends IrisAction
{
    public function handle(string $tab): array
    {
        $shop        = $this->shop;
        $indexer     = IndexReviewsInIris::make();
        $shopProfile = [
            'name'              => $shop->name,
            'email'             => $shop->email,
            'phone'             => $shop->phone,
            'logo'              => $shop->image ? $shop->imageSources(120, 120) : null,
            'formatted_address' => $shop->address?->formatted_address,
            'country'           => $shop->country?->name,
        ];

        $reviewSettings = Arr::get($shop->settings, 'reviews');

        return match ($tab) {
            'product' => $this->productTab($shop, $indexer, $shopProfile, $reviewSettings),
            'family'  => $this->familyTab($shop, $indexer, $shopProfile, $reviewSettings),
            'company' => $this->companyTab($shop, $indexer, $shopProfile, $reviewSettings),
            default   => $this->allTab($shop, $indexer, $shopProfile, $reviewSettings),
        };
    }

    private function allTab($shop, IndexReviewsInIris $indexer, array $shopProfile, mixed $reviewSettings): array
    {
        $reviews      = $indexer->handleAllScopeReviews(shop: $shop, prefix: 'reviews');
        $avgReview    = $indexer->avgByScopeReview($shop, [
            ReviewScopeEnum::SHOP,
            ReviewScopeEnum::ORDER,
            ReviewScopeEnum::PRODUCT,
            ReviewScopeEnum::FAMILY,
        ]);
        $totalReviews = $reviews->total();

        return [
            'type'              => 'all',
            'shop_profile'      => $shopProfile,
            'review_settings'   => $reviewSettings,
            'reviews'           => IrisAllReviewsResource::collection($reviews)->response()->getData(true),
            'avg_review'        => $avgReview ? round((float) $avgReview, 1) : 0.0,
            'total_reviews'     => $totalReviews,
            'recommend_percent' => $this->recommendPercent($shop, $totalReviews, [
                ReviewScopeEnum::SHOP, ReviewScopeEnum::ORDER,
                ReviewScopeEnum::PRODUCT, ReviewScopeEnum::FAMILY,
            ]),
        ];
    }

    private function productTab($shop, IndexReviewsInIris $indexer, array $shopProfile, mixed $reviewSettings): array
    {
        $reviews      = $indexer->handleProductScopeReviews(shop: $shop, prefix: 'reviews');
        $avgReview    = $indexer->avgByScopeReview($shop, [ReviewScopeEnum::PRODUCT]);
        $totalReviews = $reviews->total();

        return [
            'type'              => 'product',
            'shop_profile'      => $shopProfile,
            'review_settings'   => $reviewSettings,
            'reviews'           => IrisAllReviewsResource::collection($reviews)->response()->getData(true),
            'avg_review'        => $avgReview ? round((float) $avgReview, 1) : 0.0,
            'total_reviews'     => $totalReviews,
            'recommend_percent' => $this->recommendPercent($shop, $totalReviews, [ReviewScopeEnum::PRODUCT]),
        ];
    }

    private function familyTab($shop, IndexReviewsInIris $indexer, array $shopProfile, mixed $reviewSettings): array
    {
        $reviews      = $indexer->handleFamilyScopeReviews(shop: $shop, prefix: 'reviews');
        $avgReview    = $indexer->avgByScopeReview($shop, [ReviewScopeEnum::FAMILY]);
        $totalReviews = $reviews->total();

        return [
            'type'              => 'family',
            'shop_profile'      => $shopProfile,
            'review_settings'   => $reviewSettings,
            'reviews'           => IrisAllReviewsResource::collection($reviews)->response()->getData(true),
            'avg_review'        => $avgReview ? round((float) $avgReview, 1) : 0.0,
            'total_reviews'     => $totalReviews,
            'recommend_percent' => $this->recommendPercent($shop, $totalReviews, [ReviewScopeEnum::FAMILY]),
        ];
    }

    private function companyTab($shop, IndexReviewsInIris $indexer, array $shopProfile, mixed $reviewSettings): array
    {
        $reviews      = $indexer->handleCompanyScopeReviews(shop: $shop, prefix: 'reviews');
        $avgReview    = $indexer->avgByScopeReview($shop, [ReviewScopeEnum::SHOP, ReviewScopeEnum::ORDER]);
        $totalReviews = $reviews->total();

        return [
            'type'              => 'company',
            'shop_profile'      => $shopProfile,
            'review_settings'   => $reviewSettings,
            'reviews'           => IrisAllReviewsResource::collection($reviews)->response()->getData(true),
            'avg_review'        => $avgReview ? round((float) $avgReview, 1) : 0.0,
            'total_reviews'     => $totalReviews,
            'recommend_percent' => $this->recommendPercent($shop, $totalReviews, [ReviewScopeEnum::SHOP, ReviewScopeEnum::ORDER]),
        ];
    }

    private function recommendPercent($shop, int $totalReviews, array $scopes): int
    {
        if ($totalReviews === 0) {
            return 0;
        }

        $count = Review::query()
            ->where('shop_id', $shop->id)
            ->whereIn('scope', $scopes)
            ->where('state', ReviewStateEnum::PUBLISHED)
            ->where('is_public', true)
            ->where('review_status', ReviewStatusEnum::APPROVED)
            ->where('rating_main', '>=', 4)
            ->count();

        return (int) round(($count / $totalReviews) * 100);
    }

    public function htmlResponse(array $data): Response
    {
        $tableStructure = IndexReviewsInIris::make()->tableStructure();

        return Inertia::render('AllReviews', $data)
            ->table(fn (InertiaTable $t) => $tableStructure($t->name('all')->pageName('reviewsPage')))
            ->table(fn (InertiaTable $t) => $tableStructure($t->name('company')->pageName('reviewsPage')))
            ->table(fn (InertiaTable $t) => $tableStructure($t->name('family')->pageName('reviewsPage')))
            ->table(fn (InertiaTable $t) => $tableStructure($t->name('product')->pageName('reviewsPage')));
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);

        $tab = in_array($request->query('tab'), ['all', 'product', 'family', 'company'])
            ? $request->query('tab')
            : 'all';

        return $this->handle($tab);
    }
}
