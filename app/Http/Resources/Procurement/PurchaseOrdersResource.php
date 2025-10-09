<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:30:19 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $number
 * @property string $slug
 * @property string $date
 * @property mixed $parent_name
 * @property mixed $reference
 * @property mixed $parent_type
 * @property mixed $state
 * @property mixed $org_currency_code
 * @property mixed $parent_slug
 * @property mixed $org_total_cost
 */
class PurchaseOrdersResource extends JsonResource
{
    public function toArray($request): array
    {

        return [
            'reference'         => $this->reference,
            'state'             => $this->state,
            'state_icon'        => $this->state->stateIcon()[$this->state->value],
            'parent_type'       => $this->parent_type,
            'parent_name'       => $this->parent_name,
            'parent_slug'       => $this->parent->slug,
            'slug'              => $this->slug,
            'number_current_purchase_order_transactions'   => $this->number_current_purchase_order_transactions,
            'date'              => $this->date,
            'org_currency_code'     => $this->org_currency_code,
            'org_total_cost'    => $this->org_total_cost,

        ];
    }

}
