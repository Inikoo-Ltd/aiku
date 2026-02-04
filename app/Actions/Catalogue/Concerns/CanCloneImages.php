<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Feb 2026 00:07:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Concerns;

use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Database\Eloquent\Model;

trait CanCloneImages
{
    protected function cloneImages(TradeUnit|MasterProductCategory $source, Product|ProductCategory|Model $target): void
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
}
