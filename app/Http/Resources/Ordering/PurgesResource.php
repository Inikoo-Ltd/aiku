<?php

/*
 * author Arya Permana - Kirin
 * created on 04-11-2024-13h-58m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Ordering;

use Illuminate\Http\Resources\Json\JsonResource;

class PurgesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                                => $this->id,
            'state'                             => $this->state,
            'type'                              => $this->type,
            'scheduled_at'                      => $this->scheduled_at,
            'start_at'                          => $this->start_at,
            'end_at'                            => $this->end_at,
            'cancelled_at'                      => $this->cancelled_at,
            'inactive_days'                     => $this->inactive_days,
            'estimated_number_orders'           => $this->estimated_number_orders,
            'estimated_number_transactions'     => $this->estimated_number_transactions,
            'estimated_net_amount'                  => $this->estimated_net_amount,
            'currency_code'                     => $this->shop?->currency?->code,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
            'shop_name'         => $this->shop_name,
            'shop_slug'         => $this->shop_slug,
        ];
    }
}
