<?php

namespace App\Actions\Catalogue\Review\Traits;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateReviewStats;
use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateReviewStats;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateReviewStats;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateReviewStats;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterProductCategoryHydrateReviewStats;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateReviewStats;
use App\Models\Reviews\ProductCategoryReview;
use App\Models\Reviews\ProductReview;
use App\Models\Reviews\ShopReview;

trait HasReviewHydrators
{
    protected int $hydratorsDelay = 0;

    protected function reviewHydrators(ProductReview|ProductCategoryReview|ShopReview $review): void
    {
        GroupHydrateReviewStats::dispatch($review->group_id)->delay($this->hydratorsDelay);

        if ($review instanceof ShopReview) {
            ShopHydrateReviewStats::dispatch($review->shop_id)->delay($this->hydratorsDelay);
        }

        if ($review instanceof ProductReview) {
            ProductHydrateReviewStats::dispatch($review->product_id)->delay($this->hydratorsDelay);
            MasterAssetHydrateReviewStats::dispatch($review->master_product_id)->delay($this->hydratorsDelay);
        }

        if ($review instanceof ProductCategoryReview) {
            ProductCategoryHydrateReviewStats::dispatch($review->product_category_id)->delay($this->hydratorsDelay);
            MasterProductCategoryHydrateReviewStats::dispatch($review->master_product_category_id)->delay($this->hydratorsDelay);
        }
    }
}
