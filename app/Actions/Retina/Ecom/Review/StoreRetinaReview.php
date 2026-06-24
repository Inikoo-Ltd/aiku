<?php

namespace App\Actions\Retina\Ecom\Review;

use App\Actions\Catalogue\Review\StoreReview;
use App\Actions\RetinaAction;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Reviews\ProductCategoryReview;
use App\Models\Reviews\ProductReview;
use App\Models\Reviews\ShopReview;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaReview extends RetinaAction
{
    public function handle(Product|ProductCategory|Shop $reviewable, array $modelData): ProductReview|ProductCategoryReview|ShopReview
    {
        return StoreReview::run($reviewable, $modelData);
    }

    public function asController(ActionRequest $request): ProductReview|ProductCategoryReview|ShopReview
    {
        $this->initialisation($request);

        return $this->handle(
            $this->resolveReviewable(
                (string) data_get($this->validatedData, 'reviewable_type'),
                (int) data_get($this->validatedData, 'reviewable_id')
            ),
            $this->validatedData
        );
    }

    private function resolveReviewable(string $reviewableType, int $reviewableId): Product|ProductCategory|Shop
    {
        return match ($reviewableType) {
            'Product', 'product_reviews'                         => Product::query()->findOrFail($reviewableId),
            'ProductCategory', 'product_category_reviews'        => ProductCategory::query()->findOrFail($reviewableId),
            default                                              => Shop::query()->findOrFail($reviewableId),
        };
    }
}
