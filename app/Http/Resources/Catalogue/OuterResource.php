<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Catalogue;

use App\Models\Catalogue\Product;
use Illuminate\Http\Resources\Json\JsonResource;

class OuterResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Product $outer */
        $outer = $this;

        return [
            'slug' => $outer->slug,
            'code' => $outer->code,
            'name' => $outer->name,
            'state' => $outer->state,
            'created_at' => $outer->created_at,
            'updated_at' => $outer->updated_at,
        ];
    }
}
