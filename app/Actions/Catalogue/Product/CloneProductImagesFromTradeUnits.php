<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Sept 2025 11:11:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateImages;
use App\Models\Catalogue\Product;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CloneProductImagesFromTradeUnits implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Product $product): string
    {
        return $product->id;
    }

    public function handle(Product $product): void
    {
        if (!$product->is_single_trade_unit) {
            return;
        }

        $images   = [];
        $position = 1;

        $tradeUnit = $product->tradeUnits->first();

        foreach ($tradeUnit->images as $image) {
            $images[$image->id] = [
                'is_public'       => true,
                'scope'           => 'photo',
                'sub_scope'       => $image->pivot->sub_scope,
                'caption'         => $image->pivot->caption,
                'organisation_id' => $product->organisation_id,
                'group_id'        => $product->group_id,
                'position'        => $position++,
                'created_at'      => now(),
                'updated_at'      => now(),
                'data'            => '{}'

            ];
        }


        $product->images()->sync($images);
        $product->update([
            'image_id'                 => $tradeUnit->image_id,
            'front_image_id'           => $tradeUnit->front_image_id,
            '34_image_id'              => $tradeUnit->{'34_image_id'},
            'left_image_id'            => $tradeUnit->left_image_id,
            'right_image_id'           => $tradeUnit->right_image_id,
            'back_image_id'            => $tradeUnit->back_image_id,
            'top_image_id'             => $tradeUnit->top_image_id,
            'bottom_image_id'          => $tradeUnit->bottom_image_id,
            'size_comparison_image_id' => $tradeUnit->size_comparison_image_id,
            'art1_image_id'            => $tradeUnit->art1_image_id,
            'art2_image_id'            => $tradeUnit->art2_image_id,
            'art3_image_id'            => $tradeUnit->art3_image_id,
            'art4_image_id'            => $tradeUnit->art4_image_id,
            'art5_image_id'            => $tradeUnit->art5_image_id,
            'lifestyle_image_id'       => $tradeUnit->lifestyle_image_id,
        ]);

        ProductHydrateImages::dispatch($product);
        UpdateProductWebImages::run($product);
    }


}
