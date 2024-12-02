<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Catalogue;

use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Catalogue\Product;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    use HasSelfCall;
    public function toArray($request): array
    {
        /** @var Product $product */
        $product = $this;

        return [
            'id'              => $product->id,
            'slug'            => $product->slug,
            'image_id'        => $product->image_id,
            'code'            => $product->code,
            'name'            => $product->name,
            'price'           => $product->price,
            'description'     => $product->description,
            'state'           => $product->state,
            'created_at'      => $product->created_at,
            'updated_at'      => $product->updated_at,
            'images'          => ImageResource::collection($product->images),
            'image_thumbnail' => $product->imageSources(720, 480)
        ];
    }
}
