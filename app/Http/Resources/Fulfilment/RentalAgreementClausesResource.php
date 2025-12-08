<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Jun 2024 15:38:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $asset_code
 * @property mixed $asset_name
 * @property mixed $asset_type
 * @property mixed $asset_price
 * @property mixed $asset_units
 * @property mixed $asset_unit
 * @property mixed $percentage_off
 */
class RentalAgreementClausesResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {

        return [
            'id' => $this->id,
            'asset_code' => $this->asset_code,
            'asset_name' => $this->asset_name,
            'asset_type' => $this->asset_type,
            'asset_price' => $this->asset_price,
            'asset_units' => $this->asset_units,
            'asset_unit' => $this->asset_unit,
            'percentage_off' => $this->percentage_off,
            'agreed_price' => $this->calculateAgreedPrice($this->asset_price, $this->percentage_off),
        ];
    }

    private function calculateAgreedPrice($price, $percentageOff)
    {

        return $price * $percentageOff / 100;
    }
}
