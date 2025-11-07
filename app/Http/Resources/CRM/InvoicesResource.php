<?php

namespace App\Http\Resources\CRM;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $reference
 * @property mixed $customer_name
 * @property mixed $date
 * @property mixed $pay_status
 * @property mixed $net_amount
 * @property mixed $total_amount
 * @property mixed $currency_code
 * @property mixed $currency_symbol
 */
class InvoicesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'reference'         => $this->reference,
            'customer_name'     => $this->customer_name,
            'date'              => $this->date,
            'pay_status'        => $this->pay_status->typeIcon()[$this->pay_status->value],
            'net_amount'        => $this->net_amount,
            'total_amount'      => $this->total_amount,
            'currency_code'     => $this->currency_code,
            'currency_symbol'   => $this->currency_symbol,
        ];
    }
}
