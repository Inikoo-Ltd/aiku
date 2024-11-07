<?php
/*
 * author Arya Permana - Kirin
 * created on 07-11-2024-13h-44m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Ordering;

use Illuminate\Http\Resources\Json\JsonResource;

class PurgedOrdersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                        => $this->id,
            'status'                    => $this->status,
            'purged_at'                 => $this->purged_at,
            'order_last_updated_at'     => $this->order_last_updated_at,
            'amount'                    => $this->amount,
            'number_transactions'       => $this->number_transactions,
            'note'                      => $this->note,
            'order_reference'           => $this->order_reference,
            'order_id'                  => $this->order_id,
            'order_slug'                => $this->order_slug
        ];
    }
}
