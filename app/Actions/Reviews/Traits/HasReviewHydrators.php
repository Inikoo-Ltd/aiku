<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jun 2026 12:31:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews\Traits;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateReviewStats;
use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateReviewStats;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateReviewStats;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateReviewStats;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterProductCategoryHydrateReviewStats;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateReviewStats;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateReviewStats;
use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\Models\Reviews\Review;

trait HasReviewHydrators
{
    protected function reviewHydrators(Review $review): void
    {
        GroupHydrateReviewStats::dispatch($review->group_id)->delay(5);

        ShopHydrateReviewStats::dispatch($review->shop_id)->delay(5);

        OrderHydrateReviewStats::dispatch($review->order_id)->delay(5);


        if ($review->scope == ReviewScopeEnum::FAMILY) {
            ProductCategoryHydrateReviewStats::dispatch($review->product_category_id)->delay(10);
            MasterProductCategoryHydrateReviewStats::dispatch($review->master_product_category_id)->delay(10);
        }

        if ($review->scope == ReviewScopeEnum::PRODUCT) {
            ProductHydrateReviewStats::dispatch($review->product_id)->delay(10);
            MasterAssetHydrateReviewStats::dispatch($review->master_product_id)->delay(10);
        }
    }
}
