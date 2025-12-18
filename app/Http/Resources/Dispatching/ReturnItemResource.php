<?php

/*
 * Author: Oggie Sutrisna
 * Created: Wed, 18 Dec 2025 13:50:00 Makassar Time
 * Description: Resource for ReturnItem model
 */

namespace App\Http\Resources\Dispatching;

use App\Enums\Dispatching\Return\ReturnItemStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'state'                 => $this->state,
            'state_icon'            => ReturnItemStateEnum::stateIcon()[$this->state->value] ?? null,
            'state_label'           => $this->state->labels()[$this->state->value] ?? null,
            'quantity_expected'     => $this->quantity_expected,
            'quantity_received'     => $this->quantity_received,
            'quantity_accepted'     => $this->quantity_accepted,
            'quantity_rejected'     => $this->quantity_rejected,
            'quantity_restocked'    => $this->quantity_restocked,
            'notes'                 => $this->notes,
            'condition'             => $this->condition,
            'rejection_reason'      => $this->rejection_reason,
            'refund_amount'         => $this->refund_amount,
            'date'                  => $this->date,
            'received_at'           => $this->received_at,
            'inspecting_at'         => $this->inspecting_at,
            'accepted_at'           => $this->accepted_at,
            'rejected_at'           => $this->rejected_at,
            'restocked_at'          => $this->restocked_at,
            'org_stock'             => $this->whenLoaded('orgStock', function () {
                return [
                    'id'   => $this->orgStock->id,
                    'code' => $this->orgStock->code,
                    'name' => $this->orgStock->name,
                ];
            }),
        ];
    }
}
