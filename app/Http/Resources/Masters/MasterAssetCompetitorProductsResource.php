<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Masters;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\Masters\MasterAssetCompetitorProduct $resource
 */
class MasterAssetCompetitorProductsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                      => $this->id,
            'status'                  => $this->status->value,
            'is_same_item'            => $this->is_same_item,
            'confidence'              => $this->confidence === null ? null : (int) round(100 * $this->confidence),
            'is_auto_confirmed'       => $this->status->value === 'confirmed' && !$this->reviewed_by_user_id,
            'competitor_image_url'    => $this->competitor_image_url,
            'code'                    => $this->code,
            'name'                    => $this->name,
            'competitor_name'         => $this->competitor_name,
            'competitor_product_name' => $this->competitor_product_name,
            'competitor_product_url'  => $this->competitor_product_url,
            'competitor_units'        => (float) $this->competitor_units,
            'minimum_order'           => $this->minimum_order,
            'our_unit_price'          => $this->our_unit_price,
            'competitor_unit_price'   => $this->competitor_unit_price,
            'difference'              => $this->difference,
            'currency_code'           => $this->currency_code,
            'fetched_at'              => $this->fetched_at,
        ];
    }
}
