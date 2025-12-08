<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Catalogue;

use App\Http\Resources\Helpers\CurrencyResource;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Currency;
use Illuminate\Http\Resources\Json\JsonResource;

class OutersResource extends JsonResource
{
    public function toArray($request): array
    {
        $currency = Currency::find($this->currency_id);
        /** @var Product $outer */
        $outer = $this;

        return [
            'id' => $outer->id,
            'asset_id' => $outer->asset_id,
            'slug' => $outer->slug,
            'code' => $outer->code,
            'name' => $outer->name,
            'price' => $outer->price,
            'unit' => $outer->unit,
            'currency' => CurrencyResource::make($currency),
            'agreed_price' => $outer->agreed_price ?? $outer->price,
            'percentage_off' => 0,

        ];
    }
}
