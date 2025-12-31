<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Apr 2023 15:23:04 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $shop_id
 * @property int $offer_campaign_id
 * @property string $slug
 * @property string $code
 * @property string $data
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 *
 */
class OfferResource extends JsonResource
{
    public function toArray($request): array
    {
        preg_match('/percentage_off:([0-9]*\.?[0-9]+)/', $this->allowance_signature, $matches);

        $percentage_off = $matches[1] ?? null;

        return [
            'shop_id'           => $this->shop_id,
            'offer_campaign_id' => $this->offer_campaign_id,
            'slug'              => $this->slug,
            'code'              => $this->code,
            'name'              => $this->name,
            'data'              => $this->data,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'percentage_off'    => $percentage_off,
        ];
    }
}
