<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 May 2025 15:14:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\PaymentAccountShop\UI;

use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\Concerns\AsObject;

class GetRetinaPaymentAccountShopData
{
    use AsObject;

    public function handle(Order $order, PaymentAccountShop $paymentAccountShop)
    {
        if ($paymentAccountShop->type == PaymentAccountTypeEnum::CHECKOUT) {
            return
                [
                    'label' => __('Online payments'),
                    'icon'  => 'fal fa-credit-card-front',
                    'data' => GetRetinaPaymentAccountShopCheckoutComData::run($order, $paymentAccountShop)
                ];
        } elseif ($paymentAccountShop->type == PaymentAccountTypeEnum::BANK) {

            return
                [
                    'label' => __('Bank transfer'),
                    'icon'  => 'fal fa-university',
                    'data'  => [
                        'bank_name' => 'AAA',
                        'account_number' => 'xxxx',
                        'iban' => 'yyyy'
                    ]
                ];
        }

        return null;
    }
}
