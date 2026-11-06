<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Nov 2026 17:03:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Masters;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $checked_at
 * @property string $price
 * @property string $units
 * @property int|null $delivery_days
 * @property string|null $our_unit_price
 * @property string|null $their_unit_price
 * @property int|null $difference_pct
 * @property string|null $change
 * @property string $master_asset_code
 * @property string $master_asset_name
 * @property string $master_asset_slug
 * @property string $competitor_product_name
 * @property string|null $url
 * @property string|null $checked_by
 * @property string $unit_currency_code
 */
class CompetitorPriceChecksResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                      => $this->id,
            'checked_at'              => $this->checked_at,
            'master_asset_code'       => $this->master_asset_code,
            'master_asset_name'       => $this->master_asset_name,
            'master_asset_slug'       => $this->master_asset_slug,
            'competitor_product_name' => $this->competitor_product_name,
            'url'                     => $this->url,
            'price'                   => (float) $this->price,
            'units'                   => (float) $this->units,
            'their_unit_price'        => $this->their_unit_price === null ? null : (float) $this->their_unit_price,
            'our_unit_price'          => $this->our_unit_price === null ? null : (float) $this->our_unit_price,
            'difference_pct'          => $this->difference_pct,
            'change'                  => $this->change === null ? null : round((float) $this->change, 2),
            'unit_currency_code'      => $this->unit_currency_code,
            'screenshot_thumbnail'    => $this->imageSources(160, 0),
            'screenshot'              => $this->imageSources(1600, 0),
            'delivery_days'           => $this->delivery_days,
            'checked_by'              => $this->checked_by,
        ];
    }
}
