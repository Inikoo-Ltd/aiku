<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Aug 2024 22:10:11 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use App\Http\Resources\HasSelfCall;
use App\Models\Inventory\LocationOrgStock;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationOrgStockResource extends JsonResource
{
    use HasSelfCall;

    public static $wrap = null;

    public function toArray($request): array
    {
        /** @var LocationOrgStock $locationOrgStock */
        $locationOrgStock = $this;

        return [
            'id' => $locationOrgStock->id,
            'quantity' => (int) $locationOrgStock->quantity,
            'value' => $locationOrgStock->value,
            'audited_at' => $locationOrgStock->audited_at,
            'commercial_value' => $locationOrgStock->commercial_value,
            'type' => $locationOrgStock->type,
            'picking_priority' => $locationOrgStock->picking_priority,
            'notes' => $locationOrgStock->notes,
            'data' => $locationOrgStock->data,
            'settings' => $locationOrgStock->settings,
            'created_at' => $locationOrgStock->created_at,
            'updated_at' => $locationOrgStock->updated_at,
        ];
    }
}
