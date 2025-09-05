<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Sept 2025 18:34:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Actions\Traits\Hydrators\WithWeightFromTradeUnits;
use App\Models\Catalogue\Product;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductHydrateImagesFromTradeUnits implements ShouldBeUnique
{
    use AsAction;
    use WithWeightFromTradeUnits;

    public function getJobUniqueId(Product $product): string
    {
        return $product->id;
    }

    public function handle(Product $product): void
    {
        $images   = [];
        $position = 1;
        $image_id = null;
        foreach ($product->tradeUnits as $tradeUnit) {
            foreach ($tradeUnit->images as $image) {
                if ($image_id === null) {
                    $image_id = $image->id;
                }

                $images[$image->id] = [
                    'is_public'       => true,
                    'scope'           => 'photo',
                    'organisation_id' => $product->organisation_id,
                    'group_id'        => $product->group_id,
                    'position'        => $position++,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                    'data'            => '{}'

                ];
            }
        }
        $product->images()->sync($images);
        $product->update(['image_id' => $image_id]);
    }


}
