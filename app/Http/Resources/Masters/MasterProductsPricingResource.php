<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 Jul 2026 14:05:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Masters;

use Illuminate\Http\Resources\Json\JsonResource;

class MasterProductsPricingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'slug'          => $this->slug,
            'code'          => $this->code,
            'name'          => $this->name,
            'units'         => (float) $this->units,
            'unit'          => $this->unit,
            'image_thumbnail'   => $this->web_images,
            'trade_units_label' => $this->trade_units_label,
            'price'         => $this->price,
            'rrp'           => $this->rrp,
            'currency_code' => $this->currency_code,
            'units_review'  => $this->units_review,
            'master_prices' => $this->master_prices ?? [],
            'master_rrps'   => $this->master_rrps ?? [],
            'used_in'         => (int) $this->used_in,
            'favourites'      => (int) $this->favourites,
            'price_rebels'    => (int) $this->price_rebels,
            'stock_min'         => $this->stock_min !== null ? (int) $this->stock_min : null,
            'stock_max'         => $this->stock_max !== null ? (int) $this->stock_max : null,
            'orgs_out_of_stock' => (int) $this->orgs_out_of_stock,
            'orgs_with_stock'   => (int) $this->orgs_with_stock,
            'stock_by_org'      => $this->stock_by_org ? json_decode($this->stock_by_org, true) : [],
            'sales'           => $this->sales,
            'sold'            => (int) $this->sold,
            'customers'       => (int) $this->customers,
            'sales_ly'        => $this->sales_ly,
        ];
    }
}
