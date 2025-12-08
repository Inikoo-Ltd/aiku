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
 */
class OffersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'shop_slug' => $this->shop_slug,
            'offer_campaign_slug' => $this->offer_campaign_slug,
            'slug' => $this->slug,
            'code' => $this->code,
            'name' => $this->name,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
            'shop_name' => $this->shop_name,
        ];
    }
}
