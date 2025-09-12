<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Sept 2025 11:11:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateImages;
use App\Actions\Catalogue\ProductCategory\UpdateProductCategoryWebImages;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CloneProductCategoryImagesFromMaster implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(ProductCategory $productCategory): string
    {
        return $productCategory->id;
    }

    public function handle(ProductCategory $productCategory): void
    {
        if (!$productCategory->master_product_category_id) {
            return;
        }

        $images   = [];
        $position = 1;

        $master = $productCategory->masterProductCategory;

        foreach ($master->images as $image) {
            $images[$image->id] = [
                'is_public'       => true,
                'scope'           => 'photo',
                'sub_scope'       => $image->pivot->sub_scope,
                'caption'         => $image->pivot->caption,
                'organisation_id' => $productCategory->organisation_id,
                'group_id'        => $productCategory->group_id,
                'position'        => $position++,
                'created_at'      => now(),
                'updated_at'      => now(),
                'data'            => '{}'

            ];
        }


        $productCategory->images()->sync($images);
        $productCategory->update([
            'image_id'                 => $master->image_id,
        ]);

        ProductCategoryHydrateImages::run($productCategory);
        UpdateProductCategoryWebImages::run($productCategory);
    }


}
