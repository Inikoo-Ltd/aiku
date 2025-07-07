<?php
/*
 * author Arya Permana - Kirin
 * created on 07-07-2025-11h-27m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 * @property string $state
 * @property string $shop_slug
 * @property string $reference
 * @property string $total_amount
 * @property string $net_amount
 * @property mixed $date
 * @property mixed $type
 * @property mixed $shop_code
 * @property mixed $shop_name
 * @property mixed $customer_name
 * @property mixed $customer_slug
 * @property mixed $currency_code
 * @property mixed $currency_symbol
 * @property mixed $tax_liability_at
 * @property mixed $paid_at
 * @property mixed $pay_status
 * @property mixed $organisation_name
 * @property mixed $organisation_slug
 * @property mixed $in_process
 * @property mixed $organisation_code
 * @property mixed $id
 *
 */
class DropshippingInvoicesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'slug'              => $this->slug,
            'reference'         => $this->reference,
            'total_amount'      => $this->total_amount,
            'net_amount'        => $this->net_amount,
            'state'             => $this->state,
            'date'              => $this->date,
            'type'              => [
                'label' => $this->type->labels()[$this->type->value],
                'icon'  => $this->type->typeIcon()[$this->type->value],
            ],
            'pay_status'        => $this->pay_status->typeIcon()[$this->pay_status->value],
            'tax_liability_at'  => $this->tax_liability_at,
            'paid_at'           => $this->paid_at,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'shop_slug'         => $this->shop_slug,
            'shop_code'         => $this->shop_code,
            'shop_name'         => $this->shop_name,
            'customer_name'     => $this->customer_name,
            'customer_slug'     => $this->customer_slug,
            'currency_code'     => $this->currency_code,
            'currency_symbol'   => $this->currency_symbol,
            'organisation_name' => $this->organisation_name,
            'organisation_code' => $this->organisation_code,
            'organisation_slug' => $this->organisation_slug,
            'in_process'        => $this->in_process,
            'customer_sales_channel_id' => $this->customer_sales_channel_id,
            'customer_sales_channel_reference' => $this->customer_sales_channel_reference,
            'customer_sales_channel_name' => $this->customer_sales_channel_name,
            'customer_sales_channel_slug' => $this->customer_sales_channel_slug,
            'platform_id'       => $this->platform_id,
            'platform_name'       => $this->platform_name,
            'footer'            => $this->footer ?? '',
        ];
    }
}
