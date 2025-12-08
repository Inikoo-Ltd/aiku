<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 18 Apr 2024 09:27:56 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use App\Http\Resources\Helpers\CurrencyResource;
use Illuminate\Http\Resources\Json\JsonResource;

class RentalClausesResource extends JsonResource
{
    // TODO revamp this
    public function toArray($request): array
    {

        return [
            'id' => $this->id,
            'rental_id' => $this->asset->rental->id,
            'asset_id' => $this->asset_id,
            'slug' => $this->asset->slug,
            'name' => $this->asset->name,
            'code' => $this->asset->code,
            'price' => $this->asset->rental->price,
            'agreed_price' => $this->agreed_price ?? $this->asset->rental->price,
            'percentage_off' => 0,
            'unit' => $this->asset->rental->unit,
            'currency' => CurrencyResource::make($this->asset->currency),
        ];
    }
}
