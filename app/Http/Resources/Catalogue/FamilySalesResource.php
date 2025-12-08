<?php

/*
 * Author: Steven Wicca <stewicalf@gmail.com>
 * Updated: Mon, 10 Nov 2025 13:55:08 Western Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;

class FamilySalesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'slug' => $this->slug,
            'organisation_slug' => $this->organisation_slug,
            'shop_slug' => $this->shop_slug,
            'customer_name' => $this->customer_name,
            'customer_slug' => $this->customer_slug,
            'date' => $this->date,
            'pay_status' => $this->pay_status->typeIcon()[$this->pay_status->value],
            'currency_code' => $this->currency_code,
            'total_sales' => (float) $this->total_sales,
        ];
    }
}
