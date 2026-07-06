<?php

/*
 * @Author: andiferdiawan (https://github.com/andiferdiawan)
 * @Copyright: Copyright (c) 2026, andiferdiawan
 */

namespace App\Actions\Iris\Reviews;

use App\Actions\IrisAction;
use App\Actions\Reviews\UI\IndexReviewsInIris;
use App\Enums\Catalogue\Review\ReviewContextEnum;
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
        $indexer     = IndexReviewsInIris::make($this->shop);
        $shopProfile = [
            'name'              => $shop->name,
            'email'             => $shop->email,
            'phone'             => $shop->phone,
            'logo'              => $shop->image ? $shop->imageSources(120, 120) : null,
            'formatted_address' => $shop->address?->formatted_address,
            'country'           => $shop->country?->name,
        ];

        $reviewSettings = Arr::get($shop->settings, 'reviews');

        $data = match ($tab) {
            'product' => $this->productTab($shop, $indexer, $shopProfile, $reviewSettings),
            'family'  => $this->familyTab($shop, $indexer, $shopProfile, $reviewSettings),
            'company' => $this->companyTab($shop, $indexer, $shopProfile, $reviewSettings),
            default   => $this->allTab($shop, $indexer, $shopProfile, $reviewSettings),
        };

        return array_merge($data, [
            'tabs' => [
                'current'    => $tab,
                'navigation' => $this->getTabNavigation(),
            ],
        ]);
    }

    private function allTab($shop, IndexReviewsInIris $indexer, array $shopProfile, mixed $reviewSettings): array
    {
        $reviews      = $indexer->handleAllScopeReviews(shop: $shop, prefix: 'all');
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
            'recommend_percent' => $this->recommendPercent($shop, [
                ReviewScopeEnum::SHOP, ReviewScopeEnum::ORDER,
                ReviewScopeEnum::PRODUCT, ReviewScopeEnum::FAMILY,
            ]),
        ];
    }

    private function productTab($shop, IndexReviewsInIris $indexer, array $shopProfile, mixed $reviewSettings): array
    {
        $includeOtherShops = $indexer->includesOtherShops($shop);
        $reviews           = $indexer->handleProductScopeReviews(shop: $shop, prefix: 'product');
        $avgReview         = $indexer->avgByScopeReview($shop, [ReviewScopeEnum::PRODUCT], $includeOtherShops);
        $totalReviews      = $reviews->total();

        return [
            'type'              => 'product',
            'shop_profile'      => $shopProfile,
            'review_settings'   => $reviewSettings,
            'reviews'           => IrisAllReviewsResource::collection($reviews)->response()->getData(true),
            'avg_review'        => $avgReview ? round((float) $avgReview, 1) : 0.0,
            'total_reviews'     => $totalReviews,
            'recommend_percent' => $this->recommendPercent($shop, [ReviewScopeEnum::PRODUCT], $includeOtherShops),
        ];
    }

    private function familyTab($shop, IndexReviewsInIris $indexer, array $shopProfile, mixed $reviewSettings): array
    {
        $includeOtherShops = $indexer->includesOtherShops($shop);
        $reviews           = $indexer->handleFamilyScopeReviews(shop: $shop, prefix: 'family');
        $avgReview         = $indexer->avgByScopeReview($shop, [ReviewScopeEnum::FAMILY], $includeOtherShops);
        $totalReviews      = $reviews->total();

        return [
            'type'              => 'family',
            'shop_profile'      => $shopProfile,
            'review_settings'   => $reviewSettings,
            'reviews'           => IrisAllReviewsResource::collection($reviews)->response()->getData(true),
            'avg_review'        => $avgReview ? round((float) $avgReview, 1) : 0.0,
            'total_reviews'     => $totalReviews,
            'recommend_percent' => $this->recommendPercent($shop, [ReviewScopeEnum::FAMILY], $includeOtherShops),
        ];
    }

    private function companyTab($shop, IndexReviewsInIris $indexer, array $shopProfile, mixed $reviewSettings): array
    {
        $reviews      = $indexer->handleCompanyScopeReviews(shop: $shop, prefix: 'company');
        $avgReview    = $indexer->avgByScopeReview($shop, [ReviewScopeEnum::SHOP, ReviewScopeEnum::ORDER]);
        $totalReviews = $reviews->total();

        return [
            'type'              => 'company',
            'shop_profile'      => $shopProfile,
            'review_settings'   => $reviewSettings,
            'reviews'           => IrisAllReviewsResource::collection($reviews)->response()->getData(true),
            'avg_review'        => $avgReview ? round((float) $avgReview, 1) : 0.0,
            'total_reviews'     => $totalReviews,
            'recommend_percent' => $this->recommendPercent($shop, [ReviewScopeEnum::SHOP, ReviewScopeEnum::ORDER]),
        ];
    }

    private function recommendPercent($shop, array $scopes, bool $includeOtherShops = false): int
    {
        $baseQuery = Review::query()
            ->when(
                !$includeOtherShops,
                fn ($query) => $query->where('shop_id', $shop->id),
                fn ($query) => $query->where('organisation_id', $shop->organisation_id)
            )
            ->whereIn('scope', $scopes)
            ->where('state', ReviewStateEnum::PUBLISHED)
            ->where('is_public', true)
            ->where('review_status', ReviewStatusEnum::APPROVED);

        $totalReviews = (clone $baseQuery)->count();

        if ($totalReviews === 0) {
            return 0;
        }

        $recommendedCount = (clone $baseQuery)
            ->where('rating_main', '>=', 4)
            ->count();

        return (int) round(($recommendedCount / $totalReviews) * 100);
    }

    private function getTabNavigation(): array
    {
        $labels = $this->shop->getCustomReviewCategoryLabel();
        return [
            [
                'key' => 'all',
                'label' => __('All Reviews')
            ],
            [
                'key' => 'company',
                'label' => data_get($labels, ReviewContextEnum::ORDER->value)
            ],
            [
                'key' => 'family',
                'label' => data_get($labels, ReviewContextEnum::FAMILY->value)
            ],
            [
                'key' => 'product',
                'label' => data_get($labels, ReviewContextEnum::PRODUCT->value)
            ],
        ];
    }

    public function htmlResponse(array $data): Response
    {
        $indexer           = IndexReviewsInIris::make($this->shop);
        $shop              = $this->shop;
        $includeOtherShops = $indexer->includesOtherShops($shop);

        return Inertia::render('AllReviews', $data)
            ->table(fn (InertiaTable $t) => $indexer->tableStructure(shop: $shop, scopes: [
                ReviewScopeEnum::SHOP, ReviewScopeEnum::ORDER, ReviewScopeEnum::PRODUCT, ReviewScopeEnum::FAMILY,
            ])($t->name('all')->pageName('reviewsPage')))
            ->table(fn (InertiaTable $t) => $indexer->tableStructure(shop: $shop, scopes: [
                ReviewScopeEnum::SHOP, ReviewScopeEnum::ORDER,
            ])($t->name('company')->pageName('reviewsPage')))
            ->table(fn (InertiaTable $t) => $indexer->tableStructure(shop: $shop, scopes: [
                ReviewScopeEnum::FAMILY,
            ], includeOtherShops: $includeOtherShops)($t->name('family')->pageName('reviewsPage')))
            ->table(fn (InertiaTable $t) => $indexer->tableStructure(shop: $shop, scopes: [
                ReviewScopeEnum::PRODUCT,
            ], includeOtherShops: $includeOtherShops)($t->name('product')->pageName('reviewsPage')));
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
