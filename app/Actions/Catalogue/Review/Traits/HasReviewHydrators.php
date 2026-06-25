<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jun 2026 12:31:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Review\Traits;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateReviewStats;
use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateReviewStats;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateReviewStats;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateReviewStats;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterProductCategoryHydrateReviewStats;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateReviewStats;
use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\Models\Reviews\Review;

trait HasReviewHydrators
{
    protected function reviewHydrators(Review $review): void
    {
        GroupHydrateReviewStats::dispatch($review->group_id)->delay($this->hydratorsDelay);

        if ($review->scope == ReviewScopeEnum::SHOP) {
            ShopHydrateReviewStats::dispatch($review->shop_id)->delay($this->hydratorsDelay);
        }

        if ($review->scope == ReviewScopeEnum::FAMILY) {
            ProductCategoryHydrateReviewStats::dispatch($review->product_category_id)->delay($this->hydratorsDelay);
            MasterProductCategoryHydrateReviewStats::dispatch($review->master_product_category_id)->delay($this->hydratorsDelay);
        }

        if ($review->scope == ReviewScopeEnum::PRODUCT) {
            ProductHydrateReviewStats::dispatch($review->product_id)->delay($this->hydratorsDelay);
            MasterAssetHydrateReviewStats::dispatch($review->master_product_id)->delay($this->hydratorsDelay);
        }
    }
}
