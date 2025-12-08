<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:30:19 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use App\Models\Procurement\PurchaseOrder;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $number
 * @property string $provider
 * @property string $state
 * @property string $status
 * @property string $created_at
 * @property string $updated_at
 * @property string $slug
 * @property string $date
 */
class PurchaseOrderResource extends JsonResource
{
    public function toArray($request): array
    {

        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = $this;

        return [
            'id' => $purchaseOrder->id,
            'number' => $purchaseOrder->reference,
            'slug' => $purchaseOrder->slug,
            'date' => $purchaseOrder->date,
            'state' => $purchaseOrder->state->value,
            'state_label' => $purchaseOrder->state->labels()[$purchaseOrder->state->value],
            'state_icon' => $purchaseOrder->state->stateIcon()[$purchaseOrder->state->value],
            'created_at' => $purchaseOrder->created_at,
            'updated_at' => $purchaseOrder->updated_at,
        ];
    }
}
