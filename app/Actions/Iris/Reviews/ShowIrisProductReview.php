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
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Reviews\Review;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Catalogue\IrisAllReviewsResource;

class ShowIrisProductReview extends IrisAction
{
    private Product $product;

    public function handle(Product $product): array
    {
        $shop           = $product->shop;
        $indexer        = IndexReviewsInIris::make();
        $reviewSettings = Arr::get($shop->settings, 'reviews');
        $minRating      = Arr::get($shop->settings, 'reviews.minimum_rating_to_show', 3);

        $includeOtherShops = $indexer->includesOtherShops($shop) && $product->master_product_id;

        $reviews      = $indexer->handleSpecificProductReviews($product, 'reviews');
        $totalReviews = $reviews->total();

        if ($includeOtherShops) {
            $avgReview = Review::query()
                ->where('scope', ReviewScopeEnum::PRODUCT)
                ->where('master_product_id', $product->master_product_id)
                ->where('reviews.organisation_id', $shop->organisation_id)
                ->where('state', ReviewStateEnum::PUBLISHED)
                ->where('is_public', true)
                ->where('review_status', ReviewStatusEnum::APPROVED)
                ->where('rating_main', '>=', $minRating)
                ->avg('rating_main');
        } else {
            $avgReview = $indexer->avgByScopeReview($shop, [ReviewScopeEnum::PRODUCT]);
        }

        $recommendBase = Review::query()
            ->where('scope', ReviewScopeEnum::PRODUCT)
            ->where('state', ReviewStateEnum::PUBLISHED)
            ->where('is_public', true)
            ->where('review_status', ReviewStatusEnum::APPROVED)
            ->where('rating_main', '>=', $minRating);

        if ($includeOtherShops) {
            $recommendBase
                ->where('master_product_id', $product->master_product_id)
                ->where('reviews.organisation_id', $shop->organisation_id);
        } else {
            $recommendBase
                ->where('shop_id', $shop->id)
                ->where('product_id', $product->id);
        }

        $recommendedCount = $recommendBase->where('rating_main', '>=', 4)->count();

        return [
            'type'              => 'product',
            'product'           => [
                'id'   => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'code' => $product->code,
            ],
            'heading' => $product->name . " Reviews",
            'shop_profile'      => [
                'name'              => $shop->name,
                'email'             => $shop->email,
                'phone'             => $shop->phone,
                'logo'              => $shop->image ? $shop->imageSources(120, 120) : null,
                'formatted_address' => $shop->address?->formatted_address,
                'country'           => $shop->country?->name,
            ],
            'review_settings'   => $reviewSettings,
            'reviews'           => IrisAllReviewsResource::collection($reviews)->response()->getData(true),
            'avg_review'        => $avgReview ? round((float) $avgReview, 1) : 0.0,
            'total_reviews'     => $totalReviews,
            'recommend_percent' => $totalReviews > 0
                ? (int) round(($recommendedCount / $totalReviews) * 100)
                : 0,
            'tabs'              => [
                'current'    => 'reviews',
                'navigation' => [
                    ['key' => 'reviews', 'label' => __('Product Reviews')],
                ],
            ],
        ];
    }

    public function htmlResponse(array $data): Response
    {
        $indexer           = IndexReviewsInIris::make();
        $product           = $this->product;
        $includeOtherShops = $indexer->includesOtherShops($product->shop) && $product->master_product_id;

        $extraConditions = $includeOtherShops
            ? fn ($q) => $q
                ->where('reviews.master_product_id', $product->master_product_id)
            : fn ($q) => $q
                ->where('reviews.product_id', $product->id);

        return Inertia::render('AllReviews', $data)
            ->table(fn (InertiaTable $t) => $indexer->tableStructure(
                shop: $product->shop,
                scopes: [ReviewScopeEnum::PRODUCT],
                extraConditions: $extraConditions,
                includeOtherShops: $includeOtherShops
            )($t->name('reviews')->pageName('reviewsPage')));
    }

    public function asController(Product $product, ActionRequest $request): array
    {
        $this->initialisation($request);
        $this->product = $product;

        return $this->handle($product);
    }
}
