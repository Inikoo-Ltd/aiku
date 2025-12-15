<?php

namespace App\Http\Resources\Accounting;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class SageInvoiceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'slug'                 => $this->slug,
            'reference'            => $this->reference,
            'date'                 => $this->date?->format('Y-m-d'),
            'type'                 => [
                'code'  => $this->type === InvoiceTypeEnum::REFUND ? 'SC' : 'SI',
                'label' => $this->type === InvoiceTypeEnum::REFUND ? __('Sales Credit') : __('Sales Invoice'),
                'icon'  => $this->type === InvoiceTypeEnum::REFUND ? 'fal fa-undo' : 'fal fa-file-invoice',
            ],
            'customer'             => [
                'name'                 => $this->customer_name,
                'slug'                 => $this->customer_slug,
                'company_name'         => $this->customer_company,
                'accounting_reference' => $this->accounting_reference,
            ],
            'net_amount'           => number_format($this->net_amount, 2, '.', ''),
            'tax_amount'           => number_format($this->tax_amount, 2, '.', ''),
            'total_amount'         => number_format($this->total_amount, 2, '.', ''),
            'currency'             => [
                'code'   => $this->currency_code,
                'symbol' => $this->currency_symbol,
            ],
            'tax_category'         => [
                'name' => $this->tax_category_name ?? __('N/A'),
            ],
            'pay_status'           => $this->pay_status,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
