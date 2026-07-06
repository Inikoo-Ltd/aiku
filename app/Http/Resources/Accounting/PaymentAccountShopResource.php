<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Accounting;

use App\Models\Accounting\PaymentAccountShop;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

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
class PaymentAccountShopResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var PaymentAccountShop $paymentAccountShop */
        $paymentAccountShop = $this;

        return [
            'id'                       => $paymentAccountShop->id,
            'shop_id'                  => $paymentAccountShop->shop_id,
            'shop_code'                => $paymentAccountShop->shop->code,
            'shop_name'                => $paymentAccountShop->shop->name,
            'shop_slug'                => $paymentAccountShop->shop->slug,
            'payment_account_code'     => $paymentAccountShop->paymentAccount->code,
            'payment_account_name'     => $paymentAccountShop->paymentAccount->name,
            'payment_account_slug'     => $paymentAccountShop->paymentAccount->slug,
            'activated_at'             => $paymentAccountShop->activated_at,
            'state'                    => $paymentAccountShop->state,
            'state_icon'               => $paymentAccountShop->state->stateIcon(),
            'show_in_checkout'         => $paymentAccountShop->show_in_checkout,
            'number_payments'          => $paymentAccountShop->stats->number_payments,
            'amount_successfully_paid' => $paymentAccountShop->stats->amount_successfully_paid,
            'shop_currency_code'       => $paymentAccountShop->shop->currency->code,
            'pastpay_credit_terms'     => Arr::get($paymentAccountShop->data, 'charges', []),
        ];
    }
}
