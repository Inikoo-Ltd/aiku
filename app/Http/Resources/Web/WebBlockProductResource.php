<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 30 May 2025 16:12:46 Central Indonesia Time, Sanur, change, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\Catalogue\TagResource;
use App\Http\Resources\HasSelfCall;
use App\Models\Catalogue\Product;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Helpers\ImageResource;
use Illuminate\Support\Arr;

class WebBlockProductResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Product $product */
        $product = $this->resource;


        return [
            'slug'        => $product->slug,
            'code'        => $product->code,
            'name'        => $product->name,
            'description' => $product->description,
            'stock'       => $product->available_quantity,
            'contents'    => ModelHasContentsResource::collection($product->contents)->toArray($request),
            'id'              => $product->id,
            'slug'            => $product->slug,
            'image_id'        => $product->image_id,
            'code'            => $product->code,
            'name'            => $product->name,
            'price'           => $product->price,
            'currency_code'   => $product->currency->code,
            'description'     => $product->description,
            'state'           => $product->state,
            'rrp'             => $product->rrp,
            'price'           => $product->price,
            'status'           => $product->status,
            'state'           => $product->state,
            'description'     => $product->description,
            'units'           => $product->units,
            'unit'            => $product->unit,
            'created_at'      => $product->created_at,
            'updated_at'      => $product->updated_at,
            'images'          => ImageResource::collection($product->images)->toArray($request),
            'tags' => TagResource::collection($product->tradeUnitTagsViaTradeUnits())->toArray($request),
        ];
    }
}
