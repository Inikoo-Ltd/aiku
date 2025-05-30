<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 30 May 2025 16:12:46 Central Indonesia Time, Sanur, change, China
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use App\Models\Catalogue\Product;
use Illuminate\Http\Resources\Json\JsonResource;

class WebBlockProductResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Product $product */
        $product = $this;


        return [
            'slug'        => $product->slug,
            'code'        => $product->code,
            'name'        => $product->name,
            'description' => $product->description,


        ];
    }
}
