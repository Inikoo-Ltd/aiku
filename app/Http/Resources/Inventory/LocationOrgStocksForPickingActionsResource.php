<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Aug 2024 11:12:11 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property mixed $quantity
 * @property mixed $location_code
 * @property mixed $location_slug
 * @property mixed $type
 * @property mixed $location_id
 * @property mixed $picked
 * @property mixed $pickings_data
 */
class LocationOrgStocksForPickingActionsResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        $quantityPicked = 0;
        $pickingId      = null;
        if ($this->pickings_data) {
            $pickingsData   = preg_split('/;/', $this->pickings_data);
            $quantityPicked = $pickingsData[0] ?? 0;
            $pickingsIds    = preg_split('/,/', $pickingsData[1] ?? []);
            if (!empty($pickingsIds)) {
                $pickingId = $pickingsIds[0];
            }
        }

        return [
            'id'              => $this->id,
            'location_id'     => $this->location_id,
            'location_code'   => $this->location_code,
            'location_slug'   => $this->location_slug,
            'quantity'        => (int) $this->quantity,
            'type'            => $this->type,
            'quantity_picked' => (int) $quantityPicked,
            'picking_id'      => $pickingId,
        ];
    }
}
