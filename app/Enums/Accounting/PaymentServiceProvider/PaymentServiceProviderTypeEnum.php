<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 27 Apr 2023 11:53:10 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Enums\Accounting\PaymentServiceProvider;

use App\Enums\EnumHelperTrait;

enum PaymentServiceProviderTypeEnum: string
{
    use EnumHelperTrait;
    case ACCOUNT = 'account';
    case CASH = 'cash';
    case BANK = 'bank';
    case ELECTRONIC_PAYMENT_SERVICE = 'electronic_payment_service';

    case ELECTRONIC_BANKING_E_PAYMENT = 'electronic_banking_e_payment';
    case CASH_ON_DELIVERY = 'cash_on_delivery';
    case BUY_NOW_PAY_LATER = 'buy_now_pay_later';

    public static function labels(): array
    {
        return [
            'account' => __('Account'),
            'cash' => __('Cash'),
            'bank' => __('Bank'),
            'electronic_payment_service' => __('Electronic Payment Service'),
            'cash_on_delivery' => __('Cash on Delivery'),
            'buy_now_pay_later' => __('Buy Now Pay Later'),
        ];
    }
}
