<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Fri, 05 Dec 2025 14:26:14 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

class IntrastatExportMetricsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                        => $this->id,
            'date'                      => $this->date?->format('Y-m-d'),
            'tariff_code'               => $this->tariff_code,
            'country'                   => [
                'id'   => $this->country_id,
                'name' => $this->country_name,
                'code' => $this->country_code,
            ],
            'tax_category'              => [
                'id'   => $this->tax_category_id,
                'name' => $this->tax_category_name ?? __('N/A'),
            ],
            'delivery_note_type'        => $this->delivery_note_type,
            'quantity'                  => number_format($this->quantity, 2),
            'value_org_currency'        => number_format($this->value_org_currency, 2),
            'currency_code'             => $this->currency_code,
            'weight'                    => number_format($this->weight / 1000, 2),
            'delivery_notes_count'      => $this->delivery_notes_count,
            'products_count'            => $this->products_count,
            'invoices_count'            => $this->invoices_count,
            'partner_tax_numbers'       => $this->partner_tax_numbers,
            'valid_tax_numbers_count'   => $this->valid_tax_numbers_count,
            'invalid_tax_numbers_count' => $this->invalid_tax_numbers_count,
            'mode_of_transport'         => [
                'value' => $this->mode_of_transport?->value,
                'label' => $this->mode_of_transport?->label(),
            ],
            'delivery_terms'            => [
                'value' => $this->delivery_terms?->value,
                'label' => $this->delivery_terms?->label(),
            ],
            'nature_of_transaction'     => [
                'value' => $this->nature_of_transaction?->value,
                'label' => $this->nature_of_transaction?->label(),
            ],
        ];
    }
}
