<?php

namespace App\Http\Resources\Accounting;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class MontanaInvoiceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'slug'                 => $this->slug,
            'reference'            => $this->reference,
            'date'                 => $this->date?->format('Y-m-d'),
            'type'                 => [
                'code'  => $this->type === InvoiceTypeEnum::REFUND ? 'R' : 'F',
                'label' => $this->type === InvoiceTypeEnum::REFUND ? __('Refund') : __('Invoice'),
                'icon'  => $this->type === InvoiceTypeEnum::REFUND ? 'fal fa-undo' : 'fal fa-file-invoice',
            ],
            'customer'             => [
                'name'                     => $this->customer_name,
                'slug'                     => $this->customer_slug,
                'company_name'             => $this->customer_company,
                'contact_name'             => $this->customer_contact,
                'identity_document'        => $this->customer_identity_document,
                'identity_document_type'   => $this->customer_identity_document_type,
            ],
            'address'              => [
                'line_1'               => $this->address_line_1,
                'line_2'               => $this->address_line_2,
                'locality'             => $this->locality,
                'administrative_area'  => $this->administrative_area,
                'postal_code'          => $this->postal_code,
                'country_code'         => $this->country_code,
            ],
            'net_amount'           => number_format($this->net_amount, 2, '.', ''),
            'tax_amount'           => number_format($this->tax_amount, 2, '.', ''),
            'total_amount'         => number_format($this->total_amount, 2, '.', ''),
            'currency'             => [
                'code'   => $this->currency_code,
                'symbol' => $this->currency_symbol,
            ],
            'pay_status'           => $this->pay_status,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
