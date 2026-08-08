<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 19:30:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SupplyChain;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $reference
 * @property string $state
 * @property string $delivery_state
 * @property mixed $date
 * @property numeric $cost_total
 * @property string $currency_code
 * @property string $supplier_code
 * @property string $supplier_slug
 * @property string $purchase_order_reference
 */
class AgentSupplierPurchaseOrdersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'                     => $this->slug,
            'reference'                => $this->reference,
            'state'                    => $this->state,
            'delivery_state'           => $this->delivery_state,
            'date'                     => $this->date,
            'cost_total'               => $this->cost_total,
            'currency_code'            => $this->currency_code,
            'supplier_code'            => $this->supplier_code,
            'supplier_slug'            => $this->supplier_slug,
            'purchase_order_reference' => $this->purchase_order_reference,
        ];
    }
}
