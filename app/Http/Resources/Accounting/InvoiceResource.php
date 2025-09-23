<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Aug 2024 14:47:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Accounting;

use App\Http\Resources\Helpers\AddressResource;
use App\Models\Accounting\Invoice;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        /** @var Invoice $invoice */
        $invoice = $this;

        return [
            'slug'              => $invoice->slug,
            'reference'         => $invoice->reference,
            'total_amount'      => $invoice->total_amount,
            'net_amount'        => $invoice->net_amount,
            'date'              => $invoice->date,
            'type'              => [
                'label' => $invoice->type->labels()[$invoice->type->value],
                'icon'  => $invoice->type->typeIcon()[$invoice->type->value],
            ],
            'tax_liability_at'  => $invoice->tax_liability_at,
            'paid_at'           => $invoice->paid_at,
            'in_process'        => $invoice->in_process,
            'created_at'        => $invoice->created_at,
            'updated_at'        => $invoice->updated_at,
            'currency_code'     => $invoice->currency->code,
            'currency'          => $invoice->currency,
            'address'           => AddressResource::make($invoice->address),
            'tax_number'        => $invoice->tax_number,
            'tax_number_valid'  => $invoice->tax_number_valid,
            'tax_number_status' => $invoice->tax_number_status,

        ];
    }
}
