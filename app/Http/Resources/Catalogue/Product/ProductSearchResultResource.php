<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 May 2026 11:18:32 Nepal Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue\Product;

use App\Models\Catalogue\Product;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use JsonSerializable;

class ProductSearchResultResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var Product $product */
        $product = $this;

        return [
            'id'    => $product->id,
            'code'  => $product->code,
            'name'  => $product->name,
            'image' => Arr::get($product->web_images, 'main.thumbnail'),

        ];
    }
}
