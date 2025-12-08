<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 18 Apr 2024 09:27:56 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use App\Actions\Utils\Abbreviate;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $asset_id
 * @property mixed $slug
 * @property mixed $name
 * @property mixed $code
 * @property mixed $price
 * @property mixed $agreed_price
 * @property mixed $unit
 * @property int $currency_id
 * @property bool $is_auto_assign
 * @property mixed $currency_code
 */
class ServicesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'asset_id' => $this->asset_id,
            'state_icon' => $this->state->stateIcon()[$this->state->value],
            'slug' => $this->slug,
            'name' => $this->name,
            'code' => $this->code,
            'price' => $this->price,
            'agreed_price' => $this->agreed_price ?? $this->price,
            'percentage_off' => 0,
            'unit' => $this->unit,
            'unit_abbreviation' => Abbreviate::run($this->unit),
            'currency_code' => $this->currency_code,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
            'shop_name' => $this->shop_name,
            'shop_slug' => $this->shop_slug,
        ];
    }
}
