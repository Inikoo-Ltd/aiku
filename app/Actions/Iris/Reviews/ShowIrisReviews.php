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
        $allSetting   = $this->broadestScopeSetting($shop);
        $reviews      = $indexer->handleAllScopeReviews(shop: $shop, prefix: 'all', setting: $allSetting);
        $avgReview    = $indexer->avgByScopeReview($shop, [
            ReviewScopeEnum::SHOP,
            ReviewScopeEnum::ORDER,
            ReviewScopeEnum::PRODUCT,
            ReviewScopeEnum::FAMILY,
        ], $allSetting);
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
            ], $allSetting),
        ];
    }

    private function broadestScopeSetting($shop): array
    {
        $settings = [
            Arr::get($shop->settings, 'reviews.validation_scope.shop', []),
            Arr::get($shop->settings, 'reviews.validation_scope.family', []),
            Arr::get($shop->settings, 'reviews.validation_scope.product', []),
        ];

        foreach ($settings as $setting) {
            if (Arr::get($setting, 'enabled') && Arr::get($setting, 'scope') === 'group') {
                return ['enabled' => true, 'scope' => 'group'];
            }
        }

        foreach ($settings as $setting) {
            if (Arr::get($setting, 'enabled')) {
                return ['enabled' => true, 'scope' => 'organisation'];
            }
        }

        return [];
    }

    private function productTab($shop, IndexReviewsInIris $indexer, array $shopProfile, mixed $reviewSettings): array
    {
        $setting      = Arr::get($shop->settings, 'reviews.validation_scope.product', []);
        $reviews      = $indexer->handleProductScopeReviews(shop: $shop, prefix: 'product');
        $avgReview    = $indexer->avgByScopeReview($shop, [ReviewScopeEnum::PRODUCT], $setting);
        $totalReviews = $reviews->total();

        return [
            'type'              => 'product',
            'shop_profile'      => $shopProfile,
            'review_settings'   => $reviewSettings,
            'reviews'           => IrisAllReviewsResource::collection($reviews)->response()->getData(true),
            'avg_review'        => $avgReview ? round((float) $avgReview, 1) : 0.0,
            'total_reviews'     => $totalReviews,
            'recommend_percent' => $this->recommendPercent($shop, [ReviewScopeEnum::PRODUCT], $setting),
        ];
    }

    private function familyTab($shop, IndexReviewsInIris $indexer, array $shopProfile, mixed $reviewSettings): array
    {
        $setting      = Arr::get($shop->settings, 'reviews.validation_scope.family', []);
        $reviews      = $indexer->handleFamilyScopeReviews(shop: $shop, prefix: 'family');
        $avgReview    = $indexer->avgByScopeReview($shop, [ReviewScopeEnum::FAMILY], $setting);
        $totalReviews = $reviews->total();

        return [
            'type'              => 'family',
            'shop_profile'      => $shopProfile,
            'review_settings'   => $reviewSettings,
            'reviews'           => IrisAllReviewsResource::collection($reviews)->response()->getData(true),
            'avg_review'        => $avgReview ? round((float) $avgReview, 1) : 0.0,
            'total_reviews'     => $totalReviews,
            'recommend_percent' => $this->recommendPercent($shop, [ReviewScopeEnum::FAMILY], $setting),
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

    private function recommendPercent($shop, array $scopes, array $setting = []): int
    {
        $enabled   = Arr::get($setting, 'enabled', false);
        $baseQuery = Review::query();

        if ($enabled && Arr::get($setting, 'scope') === 'group') {
            $baseQuery->where('group_id', $shop->group_id);
        } elseif ($enabled) {
            $baseQuery->where('organisation_id', $shop->organisation_id);
        } else {
            $baseQuery->where('shop_id', $shop->id);
        }

        $baseQuery->whereIn('scope', $scopes)
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

    public function htmlResponse(array $data): \Symfony\Component\HttpFoundation\Response
    {
        $indexer        = IndexReviewsInIris::make($this->shop);
        $shop           = $this->shop;
        $productSetting = Arr::get($shop->settings, 'reviews.validation_scope.product', []);
        $familySetting  = Arr::get($shop->settings, 'reviews.validation_scope.family', []);

        $allSetting = $this->broadestScopeSetting($shop);

        $response = Inertia::render('AllReviews', $data)
            ->table(fn (InertiaTable $t) => $indexer->tableStructure(shop: $shop, scopes: [
                ReviewScopeEnum::SHOP, ReviewScopeEnum::ORDER, ReviewScopeEnum::PRODUCT, ReviewScopeEnum::FAMILY,
            ], setting: $allSetting)($t->name('all')->pageName('reviewsPage')))
            ->table(fn (InertiaTable $t) => $indexer->tableStructure(shop: $shop, scopes: [
                ReviewScopeEnum::SHOP, ReviewScopeEnum::ORDER,
            ])($t->name('company')->pageName('reviewsPage')))
            ->table(fn (InertiaTable $t) => $indexer->tableStructure(shop: $shop, scopes: [
                ReviewScopeEnum::FAMILY,
            ], setting: $familySetting)($t->name('family')->pageName('reviewsPage')))
            ->table(fn (InertiaTable $t) => $indexer->tableStructure(shop: $shop, scopes: [
                ReviewScopeEnum::PRODUCT,
            ], setting: $productSetting)($t->name('product')->pageName('reviewsPage')))
            ->toResponse(request());

        $response->headers->set('Cache-Control', 'public, s-maxage=300, max-age=0');

        return $response;
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
