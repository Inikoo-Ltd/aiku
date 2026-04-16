<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Ordering;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $org_stock_code
 * @property mixed $org_stock_name
 * @property mixed $quantity_waiting_crm
 * @property mixed $notes
 * @property mixed $order_reference
 */
class WaitingCrmItemsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'org_stock_code'       => $this->org_stock_code,
            'org_stock_name'       => $this->org_stock_name,
            'quantity_waiting_crm' => (float) $this->quantity_waiting_crm,
            'notes'                => $this->notes,
            'order_reference'      => $this->order_reference,
        ];
    }
}
