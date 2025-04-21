<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Accounting;

use App\Http\Resources\Helpers\AddressResource;
use App\Models\Accounting\Invoice;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        /** @var Invoice $invoice */
        $invoice = $this;

        return [
            'id'               => $invoice->id,
            'slug'             => $invoice->slug,
            'reference'        => $invoice->reference,
            'total_amount'     => $invoice->total_amount,
            'net_amount'       => $invoice->net_amount,
            'payment_amount'   => $invoice->payment_amount,
            'in_process'       => $invoice->in_process,
            'date'             => $invoice->date,
            'type'             => [
                'label' => $invoice->type->labels()[$invoice->type->value],
                'icon'  => $invoice->type->typeIcon()[$invoice->type->value],
            ],
            'tax_liability_at' => $invoice->tax_liability_at,
            'paid_at'          => $invoice->paid_at,
            'created_at'       => $invoice->created_at,
            'updated_at'       => $invoice->updated_at,
            'currency_code'    => $invoice->currency->code,
            'address'          => AddressResource::make($invoice->address)

        ];
    }
}
