<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Feb 2026 00:07:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Concerns;

use App\Actions\Catalogue\Product\BreakProductInWebpagesCache;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateImages;
use App\Actions\Catalogue\Product\UpdateProductWebImages;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

trait CanCloneImages
{
    protected function cloneImages(TradeUnit|MasterProductCategory|MasterAsset|MasterCollection|Model $source, Product|ProductCategory|Collection|Model $target): void
    {
        $images   = [];
        $position = 1;

        foreach ($source->images as $image) {
            $images[$image->id] = [
                'is_public'       => true,
                'scope'           => 'photo',
                'sub_scope'       => $image->pivot->sub_scope,
                'caption'         => $image->pivot->caption,
                'organisation_id' => $target->organisation_id ?? null,
                'group_id'        => $target->group_id ?? null,
                'position'        => $position++,
                'created_at'      => now(),
                'updated_at'      => now(),
                'data'            => '{}',
            ];
        }

        $target->images()->sync($images);
    }

    protected function syncProductImages(TradeUnit|MasterAsset|Model $source, Product $product): void
    {
        $this->cloneImages($source, $product);

        $product->update([
            'image_id'                 => $source->image_id,
            'front_image_id'           => $source->front_image_id,
            '34_image_id'              => $source->{'34_image_id'},
            'left_image_id'            => $source->left_image_id,
            'right_image_id'           => $source->right_image_id,
            'back_image_id'            => $source->back_image_id,
            'top_image_id'             => $source->top_image_id,
            'bottom_image_id'          => $source->bottom_image_id,
            'size_comparison_image_id' => $source->size_comparison_image_id,
            'art1_image_id'            => $source->art1_image_id,
            'art2_image_id'            => $source->art2_image_id,
            'art3_image_id'            => $source->art3_image_id,
            'art4_image_id'            => $source->art4_image_id,
            'art5_image_id'            => $source->art5_image_id,
            'lifestyle_image_id'       => $source->lifestyle_image_id,
        ]);

        $changed = Arr::except($product->getChanges(), ['updated_at', 'last_fetched_at']);

        if (!empty($changed)) {
            BreakProductInWebpagesCache::dispatch($product)->delay(15);
        }

        ProductHydrateImages::run($product);
        UpdateProductWebImages::run($product);
    }
}
