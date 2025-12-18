<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 18 Dec 2025 13:50:00 Makassar Time
 * Description: Resource for ReturnItem model
 */

namespace App\Http\Resources\Dispatching;

use App\Enums\Dispatching\Return\ReturnItemStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnItemResource extends JsonResource
{
    public function toArray($request): array
    {
        $stateValue = $this->state instanceof \App\Enums\Dispatching\Return\ReturnItemStateEnum
            ? $this->state->value
            : $this->state;

        return [
            'id'                    => $this->id,
            'state'                 => $stateValue,
            'state_icon'            => ReturnItemStateEnum::stateIcon()[$stateValue] ?? null,
            'state_label'           => ReturnItemStateEnum::labels()[$stateValue] ?? null,
            'org_stock_code'        => $this->orgStock?->code ?? '-',
            'org_stock_name'        => $this->orgStock?->name ?? 'Unknown Product',
            'quantity_expected'     => $this->quantity_expected ?? 0,
            'quantity_received'     => $this->quantity_received ?? 0,
            'quantity_accepted'     => $this->quantity_accepted ?? 0,
            'quantity_rejected'     => $this->quantity_rejected ?? 0,
            'quantity_restocked'    => $this->quantity_restocked ?? 0,
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
