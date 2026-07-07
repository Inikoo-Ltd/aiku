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
use App\Models\Catalogue\Product;
use App\Models\Reviews\Review;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowIrisProductReview extends IrisAction
{
    private Product $product;

    public function handle(Product $product): array
    {
        $shop           = $product->shop;
        $indexer        = IndexReviewsInIris::make($product);
        $reviewSettings = Arr::get($shop->settings, 'reviews');
        $minRating      = Arr::get($shop->settings, 'reviews.minimum_rating_to_show', 3);

        $setting    = Arr::get($shop->settings, 'reviews.validation_scope.product', []);
        $enabled    = Arr::get($setting, 'enabled', false);
        $usesMaster = $enabled && $product->master_product_id;

        $reviews      = $indexer->handleSpecificProductReviews($product, 'reviews');
        $totalReviews = $reviews->total();

        $avgQuery = Review::query()
            ->where('scope', ReviewScopeEnum::PRODUCT)
            ->where('state', ReviewStateEnum::PUBLISHED)
            ->where('is_public', true)
            ->where('review_status', ReviewStatusEnum::APPROVED)
            ->where('rating_main', '>=', $minRating);

        if ($usesMaster && Arr::get($setting, 'scope') === 'group') {
            $avgQuery->where('master_product_id', $product->master_product_id)
                ->where('group_id', $shop->group_id);
        } elseif ($usesMaster) {
            $avgQuery->where('master_product_id', $product->master_product_id)
                ->where('organisation_id', $shop->organisation_id);
        } else {
            $avgQuery->where('shop_id', $shop->id)
                ->where('product_id', $product->id);
        }

        $avgReview = $avgQuery->avg('rating_main');

        $recommendBase = (clone $avgQuery)->where('rating_main', '>=', 4);

        $recommendedCount = $recommendBase->count();
        $labels = $this->shop->getCustomReviewCategoryLabel();

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
                    [
                        'key' => 'reviews',
                        'label' => data_get($labels, ReviewContextEnum::PRODUCT->value)
                    ],
                ],
            ],
        ];
    }

    public function htmlResponse(array $data): Response
    {
        $indexer    = IndexReviewsInIris::make($this->product);
        $product    = $this->product;
        $shop       = $product->shop;
        $setting    = Arr::get($shop->settings, 'reviews.validation_scope.product', []);
        $usesMaster = Arr::get($setting, 'enabled', false) && $product->master_product_id;

        $extraConditions = $usesMaster
            ? fn ($q) => $q->where('reviews.master_product_id', $product->master_product_id)
            : fn ($q) => $q->where('reviews.product_id', $product->id);

        return Inertia::render('AllReviews', $data)
            ->table(fn (InertiaTable $t) => $indexer->tableStructure(
                shop: $shop,
                scopes: [ReviewScopeEnum::PRODUCT],
                extraConditions: $extraConditions,
                setting: $setting
            )($t->name('reviews')->pageName('reviewsPage')));
    }

    public function asController(Product $product, ActionRequest $request): array
    {
        $this->initialisation($request);
        $this->product = $product;

        return $this->handle($product);
    }
}
