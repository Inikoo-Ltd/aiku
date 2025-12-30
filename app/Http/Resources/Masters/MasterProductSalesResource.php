<?php

/*
 * author Louis Perez
 * created on 24-11-2025-17h-21m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Http\Resources\Masters;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\Accounting\Invoice\InvoicePayStatusEnum;

/**
 * @property mixed $id
 * @property mixed $reference
 * @property mixed $slug
 * @property mixed $organisation_slug
 * @property mixed $shop_slug
 * @property mixed $customer_name
 * @property mixed $customer_slug
 * @property mixed $date
 * @property mixed $pay_status
 * @property mixed $currency_code
 * @property mixed $total_sales
 * @property mixed $product_asset
 * @property mixed $product_code
 */
class MasterProductSalesResource extends JsonResource
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
            'product_asset'     => $this->product_asset,
            'product_code'      => $this->product_code,
        ];
    }
}
