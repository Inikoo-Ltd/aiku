<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $org_stock_code
 * @property mixed $org_stock_name
 * @property mixed $quantity_waiting
 */
class WaitingDeliveryNoteItemsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'org_stock_code'  => $this->org_stock_code,
            'org_stock_name'  => $this->org_stock_name,
            'quantity_waiting' => $this->quantity_required - $this->quantity_picked,
        ];
    }
}
