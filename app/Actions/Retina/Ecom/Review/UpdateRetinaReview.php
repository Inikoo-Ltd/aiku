<?php

namespace App\Actions\Retina\Ecom\Review;

use App\Actions\Catalogue\Review\UpdateReview;
use App\Actions\RetinaAction;
use App\Models\Reviews\ProductCategoryReview;
use App\Models\Reviews\ProductReview;
use App\Models\Reviews\ShopReview;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaReview extends RetinaAction
{
    public function handle(ProductReview|ProductCategoryReview|ShopReview $review, array $modelData): ProductReview|ProductCategoryReview|ShopReview
    {
        return UpdateReview::run($review, $modelData);
    }

    public function asController(int $review, ActionRequest $request): ProductReview|ProductCategoryReview|ShopReview
    {
        $this->initialisation($request);

        $resolvedReview = $this->resolveReview(
            (string) data_get($this->validatedData, 'reviewable_type'),
            $review
        );

        return $this->handle($resolvedReview, $this->validatedData);
    }

    private function resolveReview(string $reviewableType, int $reviewId): ProductReview|ProductCategoryReview|ShopReview
    {
        return match ($reviewableType) {
            'Product', 'product_reviews'                         => ProductReview::query()->findOrFail($reviewId),
            'ProductCategory', 'product_category_reviews'        => ProductCategoryReview::query()->findOrFail($reviewId),
            default                                              => ShopReview::query()->findOrFail($reviewId),
        };
    }
}
