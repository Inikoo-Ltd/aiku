<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $shop_id
 * @property mixed $shop_code
 * @property mixed $shop_name
 * @property mixed $shop_slug
 * @property mixed $number_payments
 * @property mixed $amount_successfully_paid
 * @property mixed $shop_currency_code
 * @property mixed $payment_account_code
 * @property mixed $payment_account_name
 * @property mixed $payment_account_slug
 * @property mixed $activated_at
 * @property mixed $state
 * @property mixed $show_in_checkout
 */
class PaymentAccountShopsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                       => $this->id,
            'shop_id'                  => $this->shop_id,
            'shop_code'                => $this->shop_code,
            'shop_name'                => $this->shop_name,
            'shop_slug'                => $this->shop_slug,
            'payment_account_code'     => $this->payment_account_code,
            'payment_account_name'     => $this->payment_account_name,
            'payment_account_slug'     => $this->payment_account_slug,
            'activated_at'             => $this->activated_at,
            'state'                    => $this->state,
            'state_icon'               => $this->state->stateIcon(),
            'show_in_checkout'         => $this->show_in_checkout,
            'number_payments'          => $this->number_payments,
            'amount_successfully_paid' => $this->amount_successfully_paid,
            'shop_currency_code'       => $this->shop_currency_code,
        ];
    }
}
