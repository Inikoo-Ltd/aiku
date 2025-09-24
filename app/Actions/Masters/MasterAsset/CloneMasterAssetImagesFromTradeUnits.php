<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Sept 2025 12:19:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Models\Masters\MasterAsset;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CloneMasterAssetImagesFromTradeUnits implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(MasterAsset $masterAsset): string
    {
        return $masterAsset->id;
    }

    public function handle(MasterAsset $masterAsset): void
    {
        if (!$masterAsset->is_single_trade_unit) {
            return;
        }

        $images   = [];
        $position = 1;

        $tradeUnit = $masterAsset->tradeUnits->first();

        foreach ($tradeUnit->images as $image) {
            $images[$image->id] = [
                'is_public'       => true,
                'scope'           => 'photo',
                'sub_scope'       => $image->pivot->sub_scope,
                'caption'         => $image->pivot->caption,
                'group_id'        => $masterAsset->group_id,
                'position'        => $position++,
                'created_at'      => now(),
                'updated_at'      => now(),
                'data'            => '{}'

            ];
        }


        $masterAsset->images()->sync($images);
        $masterAsset->update([
            'image_id'                 => $tradeUnit->image_id,
            'front_image_id'           => $tradeUnit->front_image_id,
            '34_image_id'              => $tradeUnit->{'34_image_id'},
            'left_image_id'            => $tradeUnit->left_image_id,
            'right_image_id'           => $tradeUnit->right_image_id,
            'back_image_id'            => $tradeUnit->back_image_id,
            'top_image_id'             => $tradeUnit->top_image_id,
            'bottom_image_id'          => $tradeUnit->bottom_image_id,
            'size_comparison_image_id' => $tradeUnit->size_comparison_image_id,
            'lifestyle_image_id'       => $tradeUnit->lifestyle_image_id,
            'art1_image_id'            => $tradeUnit->art1_image_id,
            'art2_image_id'            => $tradeUnit->art2_image_id,
            'art3_image_id'            => $tradeUnit->art3_image_id,
            'art4_image_id'            => $tradeUnit->art4_image_id,
            'art5_image_id'            => $tradeUnit->art5_image_id,
        ]);

    }


}
