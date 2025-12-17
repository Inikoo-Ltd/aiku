<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Fri, 05 Dec 2025 14:26:14 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

class IntrastatMetricsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'date'                 => $this->date?->format('Y-m-d'),
            'tariff_code'          => $this->tariff_code,
            'country'              => [
                'id'   => $this->country_id,
                'name' => $this->country_name,
                'code' => $this->country_code,
            ],
            'tax_category'         => [
                'id'   => $this->tax_category_id,
                'name' => $this->tax_category_name ?? __('N/A'),
            ],
            'quantity'             => number_format($this->quantity, 2),
            'value_org_currency'   => number_format($this->value_org_currency, 2),
            'currency_code'        => $this->currency_code,
            'weight'               => number_format($this->weight / 1000, 2), // Convert grams to kg
            'delivery_notes_count' => $this->delivery_notes_count,
            'products_count'       => $this->products_count,
        ];
    }
}
