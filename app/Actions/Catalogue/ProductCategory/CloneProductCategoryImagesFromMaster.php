<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Sept 2025 11:11:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\Concerns\CanCloneImages;
use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateImages;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CloneProductCategoryImagesFromMaster implements ShouldBeUnique
{
    use AsAction;
    use CanCloneImages;

    public function getJobUniqueId(ProductCategory $productCategory): string
    {
        return $productCategory->id;
    }

    public function handle(ProductCategory $productCategory): void
    {
        if (!$productCategory->master_product_category_id) {
            return;
        }

        $master = $productCategory->masterProductCategory;

        if (!$master) {
            return;
        }

        $this->cloneImages($master, $productCategory);

        $productCategory->update([
            'image_id' => $master->image_id,
        ]);

        ProductCategoryHydrateImages::run($productCategory);
        UpdateProductCategoryWebImages::run($productCategory);
    }
}
