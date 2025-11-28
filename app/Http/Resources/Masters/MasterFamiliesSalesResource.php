<?php

/*
 * author Louis Perez
 * created on 28-11-2025-09h-51m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Http\Resources\Masters;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\Accounting\Invoice\InvoicePayStatusEnum;

class MasterFamiliesSalesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'reference'         => $this->reference,
            'slug'              => $this->slug,
            'organisation_slug' => $this->organisation_slug,
            'shop_slug'         => $this->shop_slug,
            'customer_name'     => $this->customer_name,
            'customer_slug'     => $this->customer_slug,
            'date'              => $this->date,
            'pay_status'        => InvoicePayStatusEnum::typeIcon()[$this->pay_status],
            'currency_code'     => $this->currency_code,
            'total_sales'       => (float) $this->total_sales,
        ];
    }
}
