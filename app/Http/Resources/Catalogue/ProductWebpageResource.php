<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jul 2024 15:08:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Catalogue\Product;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductWebpageResource extends JsonResource
{
    use HasSelfCall;
    public function toArray($request): array
    {
        /** @var Product $product */
        $product = $this;

        return [
            'slug'        => $product->slug,
            'image_id'    => $product->image_id,
            'code'        => $product->code,
            'name'        => $product->name,
            'stock'       => $product->available_quantity,
            'price'       => $product->price,
            'description' => $product->description,
            'state'       => $product->state,
            'created_at'  => $product->created_at,
            'updated_at'  => $product->updated_at,
            'currency'     => $product->currency,
            'units'         => $product->units,
            'unit'       => $product->unit,
            'status'     => $product->status,
            'images'      => ImageResource::collection($product->images)
        ];
    }
}
