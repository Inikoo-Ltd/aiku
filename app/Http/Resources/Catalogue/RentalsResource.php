<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 18 Apr 2024 09:27:56 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use App\Http\Resources\Helpers\CurrencyResource;
use App\Models\Helpers\Currency;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $asset_id
 * @property mixed $slug
 * @property mixed $name
 * @property mixed $code
 * @property mixed $price
 * @property mixed $agreed_price
 * @property mixed $unit
 * @property mixed $currency_id
 */
class RentalsResource extends JsonResource
{
    public function toArray($request): array
    {
        $currency = Currency::find($this->currency_id);

        return [
            'id' => $this->id,
            'asset_id' => $this->asset_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'code' => $this->code,
            'price' => $this->price,
            'agreed_price' => $this->agreed_price ?? $this->price,
            'percentage_off' => 0,
            'unit' => $this->unit,
            'currency' => CurrencyResource::make($currency),
        ];
    }
}
