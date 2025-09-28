<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 May 2025 15:14:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\PaymentAccountShop\UI;

use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Models\Accounting\OrderPaymentApiPoint;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Billables\Charge;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\Concerns\AsObject;
use Illuminate\Support\Arr;

class GetRetinaPaymentAccountShopData
{
    use AsObject;

    public function handle(Order $order, PaymentAccountShop $paymentAccountShop, OrderPaymentApiPoint $orderPaymentApiPoint): ?array
    {
        if ($paymentAccountShop->type == PaymentAccountTypeEnum::CHECKOUT) {
            if (app()->environment('production')) {
                $publicKey = Arr::get($paymentAccountShop->paymentAccount->data, 'credentials.public_key');
            } else {
                $publicKey = config('app.sandbox.checkout_com.public_key');
            }

            return
                [
                    'label'                   => __('Online payments'),
                    'key'                     => 'credit_card',
                    'public_key'              => $publicKey,
                    'environment'             => app()->environment('production') ? 'production' : 'sandbox',
                    'locale'                  => $paymentAccountShop->shop->language->code,
                    'icon'                    => 'fal fa-credit-card-front',
                    'data'                    => GetRetinaPaymentAccountShopCheckoutComData::run($order, $paymentAccountShop, $orderPaymentApiPoint),
                    'order_payment_api_point' => $orderPaymentApiPoint->ulid
                ];
        } elseif ($paymentAccountShop->type == PaymentAccountTypeEnum::BANK) {
            return
                [
                    'label' => __('Bank transfer'),
                    'key'   => 'bank_transfer',
                    'icon'  => 'fal fa-university',
                    'data'  => [
                        'bank_name'      => Arr::get($paymentAccountShop->paymentAccount->data, 'bank.name'),
                        'account_number' => Arr::get($paymentAccountShop->paymentAccount->data, 'bank.account'),
                        'iban'           => Arr::get($paymentAccountShop->paymentAccount->data, 'bank.iban'),
                    ]
                ];
        } elseif ($paymentAccountShop->type == PaymentAccountTypeEnum::CASH_ON_DELIVERY) {

            /** @var Charge $charge */
            $charge      = $order->shop->charges()->where('type', ChargeTypeEnum::COD)
                ->where('state', ChargeStateEnum::ACTIVE)
                ->first();
            $chargesInfo = $charge?->description;


            return
                [
                    'label'   => __('Cash on delivery'),
                    'key'     => 'cash_on_delivery',
                    'icon'    => 'fal fa-hand-holding-usd',
                    'content' => [
                        'charges' => $chargesInfo
                    ]

                ];
        }

        return null;
    }

}
