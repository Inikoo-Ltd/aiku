<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Aug 2024 11:12:11 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $pivot
 * @property int $id
 * @property mixed $quantity
 * @property mixed $value
 * @property mixed $audited_at
 * @property mixed $commercial_value
 * @property mixed $type
 * @property mixed $picking_priority
 * @property mixed $notes
 * @property mixed $data
 * @property mixed $settings
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $location
 */
class LocationOrgStocksResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        $locationOrgStock = $this->resource;

        $location = $locationOrgStock->location;

        $locationData = [
            'id'                        => $location->id,
            'slug'                      => $location->slug,
            'code'                      => $location->code,
            'stock_value'               => $location->stock_value ?? 0,
            'stock_commercial_value'    => $location->stock_commercial_value,
            'allow_stocks'              => $location->allow_stocks,
            'allow_fulfilment'          => $location->allow_fulfilment,
            'allow_dropshipping'        => $location->allow_dropshipping,
            'has_stock_slots'           => $location->has_stock_slots,
            'has_fulfilment'            => $location->has_fulfilment,
            'has_dropshipping_slots'    => $location->has_dropshipping_slots,
            'organisation_slug'         => $location->organisation->slug,
            'organisation_name'         => $location->organisation->name,
            'warehouse_slug'            => $location->warehouse->slug,
            'max_weight'                => $location->max_weight ?? 0,
            'max_volume'                => $location->max_volume ?? 0,
            'quantity'                  => $locationOrgStock->quantity,
        ];

        return [
            'id'                            => $locationOrgStock->id,
            'code'                          => $locationOrgStock->location->code,
            'quantity'                      => $locationOrgStock->quantity,
            'value'                         => $locationOrgStock->value,
            'audited_at'                    => $locationOrgStock->audited_at,
            'commercial_value'              => $locationOrgStock->commercial_value,
            'type'                          => $locationOrgStock->type,
            'picking_priority'              => $locationOrgStock->picking_priority,
            'notes'                         => $locationOrgStock->notes,
            'data'                          => $locationOrgStock->data,
            'settings'                      => $locationOrgStock->settings,
            'created_at'                    => $locationOrgStock->created_at,
            'updated_at'                    => $locationOrgStock->updated_at,
            'location'                      => $locationData,
            'default_wholesale_picking_location'    => $locationOrgStock->default_wholesale_picking_location,
            'enabled_on_dropshipping'               => (bool) $locationOrgStock->location?->allow_dropshipping,
            'default_dropshipping_picking_location' => $locationOrgStock->default_dropshipping_picking_location,
        ];
    }
}
