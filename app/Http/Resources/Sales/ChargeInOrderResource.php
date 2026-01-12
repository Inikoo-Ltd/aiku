<?php

/*
 * author Louis Perez
 * created on 08-01-2026-14h-25m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Http\Resources\Sales;

use App\Models\Billables\Charge;
use Illuminate\Http\Resources\Json\JsonResource;

class ChargeInOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Charge $charge */
        $charge = $this;

        return [
            'type'              => $charge->type,
            'code'              => $charge->code,
            'name'              => $charge->name,
            'description'       => $charge->description,
            'currency_code'     => $charge->currency->code,
            'default_amount'    => data_get($charge->settings, 'amount'),
            'net_amount'        => $charge->net_amount,
            'transaction_state' => $charge->transaction_state,
        ];
    }
}
