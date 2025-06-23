<?php

/*
 * author Arya Permana - Kirin
 * created on 23-06-2025-13h-44m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Api;

use App\Http\Resources\Helpers\AddressResource;
use App\Models\Accounting\Invoice;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceApiResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        /** @var Invoice $invoice */
        $invoice = $this;

        return [
            'slug'                => $invoice->slug,
            'reference'           => $invoice->reference,
            'total_amount'        => $invoice->total_amount,
            'net_amount'          => $invoice->net_amount,
            'date'                => $invoice->date,
            'type'                => [
                'label' => $invoice->type->labels()[$invoice->type->value],
                'icon'  => $invoice->type->typeIcon()[$invoice->type->value],
            ],
            'tax_liability_at' => $invoice->tax_liability_at,
            'paid_at'          => $invoice->paid_at,
            'in_process'        => $invoice->in_process,
            'created_at'       => $invoice->created_at,
            'updated_at'       => $invoice->updated_at,
            'currency_code'    => $invoice->currency->code,
            'currency'         => $invoice->currency,
            'address'          => AddressResource::make($invoice->address)

        ];
    }
}
