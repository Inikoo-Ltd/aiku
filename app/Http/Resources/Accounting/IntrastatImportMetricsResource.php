<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Dec 2024 00:45:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

class IntrastatImportMetricsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                          => $this->id,
            'date'                        => $this->date?->format('Y-m-d'),
            'tariff_code'                 => $this->tariff_code,
            'country'                     => [
                'id'   => $this->country_id,
                'name' => $this->country_name,
                'code' => $this->country_code,
            ],
            'tax_category'                => [
                'id'   => $this->tax_category_id,
                'name' => $this->tax_category_name ?? __('N/A'),
            ],
            'quantity'                    => number_format($this->quantity, 2),
            'value_org_currency'          => number_format($this->value_org_currency, 2),
            'currency_code'               => $this->currency_code,
            'weight'                      => number_format($this->weight / 1000, 2),
            'supplier_deliveries_count'   => $this->supplier_deliveries_count,
            'parts_count'                 => $this->parts_count,
            'invoices_count'              => $this->invoices_count,
            'supplier_tax_numbers'        => $this->supplier_tax_numbers,
            'valid_tax_numbers_count'     => $this->valid_tax_numbers_count,
            'invalid_tax_numbers_count'   => $this->invalid_tax_numbers_count,
            'mode_of_transport'           => [
                'value' => $this->mode_of_transport?->value,
                'label' => $this->mode_of_transport?->label(),
            ],
            'delivery_terms'              => [
                'value' => $this->delivery_terms?->value,
                'label' => $this->delivery_terms?->label(),
            ],
            'nature_of_transaction'       => [
                'value' => $this->nature_of_transaction?->value,
                'label' => $this->nature_of_transaction?->label(),
            ],
        ];
    }
}
