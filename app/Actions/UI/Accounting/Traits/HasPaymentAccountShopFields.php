<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Jun 2026 16:36:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Accounting\Traits;

use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Models\Accounting\PaymentAccountShop;
use Illuminate\Support\Arr;

trait HasPaymentAccountShopFields
{
    public function blueprint(PaymentAccountShop $paymentAccountShop): array
    {
        $isActiveField = [
            'is_active' => [
                'type'  => 'toggle',
                'label' => __('Active'),
                'value' => $paymentAccountShop->state == PaymentAccountShopStateEnum::ACTIVE,
            ],
        ];

        return match ($paymentAccountShop->type) {
            PaymentAccountTypeEnum::PASTPAY => [
                [
                    'label'  => __('Settings'),
                    'icon'   => 'fa-light fa-sliders-h',
                    'fields' => array_merge($isActiveField, [
                        'pastpay_tax_number' => [
                            'type'  => 'input',
                            'required' => true,
                            'label' => __('Creditor tax number (as registered with PastPay)'),
                            'value' => Arr::get($paymentAccountShop->paymentAccount->data, 'tax_number'),
                        ],
                        'pastpay_charges' => [
                            'type'     => 'dynamic_list',
                            'label'    => __('Credit terms'),
                            'required' => true,
                            'value'    => Arr::get($paymentAccountShop->data, 'charges.options', []),
                            'fields'   => [
                                ['key' => 'days', 'label' => __('Days'), 'placeholder' => __('Input Days')],
                                ['key' => 'charge', 'label' => __('Charge (%)'), 'placeholder' => __('Input Charge')],
                            ],
                            'addLabel' => __('Add charge'),
                        ],

                        'invoice_footer' => [
                            'type'  => 'textEditor',
                            'required' => true,
                            'label' => __('Invoice footer'),
                            'full'  => true,
                            'value' => $paymentAccountShop->invoice_footer
                        ],
                    ]),
                ],
            ],
            default => [
                [
                    'label'  => __('Settings'),
                    'icon'   => 'fa-light fa-sliders-h',
                    'fields' => $isActiveField,
                ],
            ]
        };
    }
}
