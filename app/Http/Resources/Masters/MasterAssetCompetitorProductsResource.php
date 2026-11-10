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
            'slug'                    => $this->slug,
            'name'                    => $this->name,
            'master_shop_slug'        => $this->master_shop_slug,
            'master_shop_name'        => $this->master_shop_name,
            'competitor_id'           => $this->competitor_id,
            'competitor_slug'         => $this->competitor_slug,
            'competitor_name'         => $this->competitor_name,
            'competitor_product_name' => $this->competitor_product_name,
            'competitor_product_url'  => $this->competitor_product_url,
            'competitor_units'        => (float) $this->competitor_units,
            'compared_as_units'       => $this->compared_as_units === null ? null : (float) $this->compared_as_units,
            'units_verified'          => (bool) $this->units_verified,
            'check_note'              => $this->check_note,
            'minimum_order'           => $this->minimum_order,
            'our_unit_price'          => $this->our_unit_price === null ? null : (float) $this->our_unit_price,
            'competitor_unit_price'   => $this->competitor_unit_price === null ? null : (float) $this->competitor_unit_price,
            'difference'              => $this->difference === null ? null : (int) $this->difference,
            'currency_code'           => $this->currency_code,
            'fetched_at'              => $this->fetched_at,
        ];
    }
}
