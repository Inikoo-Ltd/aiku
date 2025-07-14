<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Jun 2025 15:37:41 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $org_stock_id
 * @property mixed $id
 * @property mixed $state
 * @property mixed $quantity_required
 * @property mixed $quantity_picked
 * @property mixed $org_stock_code
 * @property mixed $org_stock_name
 * @property mixed $is_handled
 * @property mixed $quantity_packed
 * @property mixed $quantity_not_picked
 * @property mixed $quantity_dispatched
 * @property mixed $org_stock_slug
 */
class DeliveryNoteItemsStateUnassignedResource extends JsonResource
{
    public function toArray($request): array
    {
        $requiredFactionalData = divideWithRemainder(findSmallestFactors($this->quantity_required));


        return [
            'id'                           => $this->id,
            'state'                        => $this->state,
            'state_icon'                   => $this->state->stateIcon()[$this->state->value],
            'quantity_required'            => $this->quantity_required,
            'quantity_required_fractional' => $requiredFactionalData,
            'org_stock_slug'               => $this->org_stock_slug,
            'org_stock_code'               => $this->org_stock_code,
            'org_stock_name'               => $this->org_stock_name,
        ];
    }
}
