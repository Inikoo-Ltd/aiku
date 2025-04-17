<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 20-03-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
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
 * @property mixed $original_invoice_id
 * @property mixed $in_process
 *
 */
class RefundsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'                => $this->slug,
            'reference'           => $this->reference,
            'total_amount'        => $this->total_amount,
            'net_amount'          => $this->net_amount,
            'state'               => $this->state,
            'date'                => $this->date,
            'state_icon'          => $this->in_process
                ? [
                    'tooltip' => __('In process'),
                    'icon'    => 'fal fa-seedling',
                    'class'   => 'text-lime-500',
                    'color'   => '#7CCE00',
                ]
                : [
                    'tooltip' => __('Refunded'),
                    'icon'    => 'fal fa-check',
                ],
            'pay_status'          => $this->pay_status->typeIcon()[$this->pay_status->value],
            'tax_liability_at'    => $this->tax_liability_at,
            'paid_at'             => $this->paid_at,
            'shop_id'             => $this->shop_id,
            'shop_slug'           => $this->shop_slug,
            'shop_code'           => $this->shop_code,
            'shop_name'           => $this->shop_name,
            'customer_name'       => $this->customer_name,
            'customer_slug'       => $this->customer_slug,
            'currency_code'       => $this->currency_code,
            'currency_symbol'     => $this->currency_symbol,
            'organisation_name'   => $this->organisation_name,
            'organisation_code'   => $this->organisation_code,
            'organisation_slug'   => $this->organisation_slug,
            'original_invoice_id' => $this->original_invoice_id,
            'in_process'          => $this->in_process,
        ];
    }
}
