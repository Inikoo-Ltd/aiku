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
 * @property mixed $revenue_amount
 * @property mixed $currency_code
 */
class WaitingCrmItemsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'org_stock_code'       => $this->org_stock_code,
            'org_stock_name'       => $this->org_stock_name,
            'org_stock_slug'       => $this->org_stock_slug,
            'quantity_waiting_crm' => (float) $this->quantity_waiting_crm,
            'net_amount'           => (float) $this->net_amount,
            'currency_code'        => $this->currency_code,
            'notes'                => $this->notes,
            'order_id'              => $this->order_id,
            'order_slug'           => $this->order_slug,
            'order_reference'      => $this->order_reference,
            'shop_slug'            => $this->shop_slug,
            'shop_type'            => $this->shop_type,
            'shop_engine'          => $this->shop_engine,
            'organisation_slug'    => $this->organisation_slug,
        ];
    }
}
