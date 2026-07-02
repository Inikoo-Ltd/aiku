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
use App\Http\Resources\Catalogue\IrisContactNameReviewsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\ProductCategory;
use App\Models\Reviews\Review;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowIrisFamilyReview extends IrisAction
{
    private ProductCategory $family;

    public function handle(ProductCategory $family, string $tab): array
    {
        $shop           = $family->shop;
        $indexer        = IndexReviewsInIris::make();
        $reviewSettings = Arr::get($shop->settings, 'reviews');

        $data = match ($tab) {
            'product' => $this->productTab($family, $indexer),
            default   => $this->familyTab($family, $indexer),
        };

        return array_merge($data, [
            'family'       => [
                'id'   => $family->id,
                'name' => $family->name,
                'slug' => $family->slug,
                'code' => $family->code,
            ],
            'shop_profile' => [
                'name'              => $shop->name,
                'email'             => $shop->email,
                'phone'             => $shop->phone,
                'logo'              => $shop->image ? $shop->imageSources(120, 120) : null,
                'formatted_address' => $shop->address?->formatted_address,
                'country'           => $shop->country?->name,
            ],
            'review_settings' => $reviewSettings,
            'tabs'            => [
                'current'    => $tab,
                'navigation' => [
                    ['key' => 'family',  'label' => __('Family Reviews')],
                    ['key' => 'product', 'label' => __('Product Reviews')],
                ],
            ],
        ]);
    }

    private function familyTab(ProductCategory $family, IndexReviewsInIris $indexer): array
    {
        $shop         = $family->shop;
        $minRating    = Arr::get($shop->settings, 'reviews.minimum_rating_to_show', 3);
        $reviews      = $indexer->handleSpecificFamilyReviews($family, 'family');
        $avgReview    = Review::query()
            ->where('shop_id', $shop->id)
            ->where('scope', ReviewScopeEnum::FAMILY)
            ->where('product_category_id', $family->id)
            ->where('rating_main', '>=', $minRating)
            ->where('state', ReviewStateEnum::PUBLISHED)
            ->where('is_public', true)
            ->where('review_status', ReviewStatusEnum::APPROVED)
            ->avg('rating_main');
        $totalReviews = $reviews->total();

        return [
            'type'              => 'family',
            'reviews'           => IrisContactNameReviewsResource::collection($reviews)->response()->getData(true),
            'avg_review'        => $avgReview ? round((float) $avgReview, 1) : 0.0,
            'total_reviews'     => $totalReviews,
            'recommend_percent' => $this->recommendPercent($totalReviews, function ($q) use ($shop, $family, $minRating) {
                $q->where('shop_id', $shop->id)
                    ->where('scope', ReviewScopeEnum::FAMILY)
                    ->where('product_category_id', $family->id)
                    ->where('rating_main', '>=', $minRating);
            }),
        ];
    }

    private function productTab(ProductCategory $family, IndexReviewsInIris $indexer): array
    {
        $shop         = $family->shop;
        $minRating    = Arr::get($shop->settings, 'reviews.minimum_rating_to_show', 3);
        $reviews      = $indexer->handleProductsInFamilyReviews($family, 'product');
        $avgReview    = Review::query()
            ->where('reviews.shop_id', $shop->id)
            ->where('reviews.scope', ReviewScopeEnum::PRODUCT)
            ->where('reviews.rating_main', '>=', $minRating)
            ->where('reviews.state', ReviewStateEnum::PUBLISHED)
            ->where('reviews.is_public', true)
            ->where('reviews.review_status', ReviewStatusEnum::APPROVED)
            ->join('products', 'products.id', '=', 'reviews.product_id')
            ->where('products.family_id', $family->id)
            ->avg('reviews.rating_main');
        $totalReviews = $reviews->total();

        return [
            'type'              => 'product',
            'reviews'           => IrisContactNameReviewsResource::collection($reviews)->response()->getData(true),
            'avg_review'        => $avgReview ? round((float) $avgReview, 1) : 0.0,
            'total_reviews'     => $totalReviews,
            'recommend_percent' => $this->recommendPercent($totalReviews, function ($q) use ($shop, $family, $minRating) {
                $q->where('reviews.shop_id', $shop->id)
                    ->where('reviews.scope', ReviewScopeEnum::PRODUCT)
                    ->where('reviews.rating_main', '>=', $minRating)
                    ->join('products', 'products.id', '=', 'reviews.product_id')
                    ->where('products.family_id', $family->id);
            }),
        ];
    }

    private function recommendPercent(int $totalReviews, callable $baseQuery): int
    {
        if ($totalReviews === 0) {
            return 0;
        }

        $count = Review::query()
            ->where('reviews.state', ReviewStateEnum::PUBLISHED)
            ->where('reviews.is_public', true)
            ->where('reviews.review_status', ReviewStatusEnum::APPROVED)
            ->where('reviews.rating_main', '>=', 4)
            ->tap($baseQuery)
            ->count();

        return (int) round(($count / $totalReviews) * 100);
    }

    public function htmlResponse(array $data): Response
    {
        $indexer = IndexReviewsInIris::make();
        $family  = $this->family;

        return Inertia::render('AllReviews', $data)
            ->table(fn (InertiaTable $t) => $indexer->tableStructure(
                shop: $family->shop,
                scopes: [ReviewScopeEnum::FAMILY],
                extraConditions: fn ($q) => $q->where('reviews.product_category_id', $family->id)
            )($t->name('family')->pageName('familyPage')))
            ->table(fn (InertiaTable $t) => $indexer->tableStructure(
                shop: $family->shop,
                scopes: [ReviewScopeEnum::PRODUCT],
                extraConditions: fn ($q) => $q
                    ->join('products as p_count', 'p_count.id', '=', 'reviews.product_id')
                    ->where('p_count.family_id', $family->id)
            )($t->name('product')->pageName('productPage')));
    }

    public function asController(ProductCategory $family, ActionRequest $request): array
    {
        $this->initialisation($request);
        $this->family = $family;

        $tab = in_array($request->query('tab'), ['family', 'product'])
            ? $request->query('tab')
            : 'family';

        return $this->handle($family, $tab);
    }
}
