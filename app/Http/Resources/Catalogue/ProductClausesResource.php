<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 18 Apr 2024 09:27:56 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use App\Http\Resources\Helpers\CurrencyResource;
use App\Models\Fulfilment\RentalAgreementClause;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductClausesResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var RentalAgreementClause $clause */
        $clause = $this;

        return [
            'id' => $clause->id,
            'product_id' => $clause->asset->product->id,
            'asset_id' => $clause->asset_id,
            'slug' => $clause->asset->slug,
            'name' => $clause->asset->name,
            'code' => $clause->asset->code,
            'price' => $clause->asset->product->price,
            'agreed_price' => $clause->agreed_price ?? $clause->asset->product->price,
            'percentage_off' => 0,
            'unit' => $clause->asset->product->unit,
            'currency' => CurrencyResource::make($clause->asset->currency),
        ];
    }
}
