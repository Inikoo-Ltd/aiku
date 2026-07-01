<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Jun 2026 16:36:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Accounting\Traits;

use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Accounting\PaymentAccountShop;
use Illuminate\Support\Arr;

trait HasPaymentAccountShopFields
{
    public function blueprint(PaymentAccountShop $paymentAccountShop): array
    {

        return match ($paymentAccountShop->type) {
            PaymentAccountTypeEnum::PASTPAY => [
                [
                    'label'  => __('Credit terms'),
                    'icon'   => 'fa-light fa-stopwatch',
                    'fields' => [
                        'pastpay_charges' => [
                            'type'     => 'dynamic_list',
                            'label'    => __('Terms'),
                            'required' => true,
                            'value'    => Arr::get($paymentAccountShop->data, 'charges.options', []),
                            'fields'   => [
                                ['key' => 'days', 'label' => __('Days'), 'placeholder' => __('Input Days')],
                                ['key' => 'charge', 'label' => __('Charge (%)'), 'placeholder' => __('Input Charge')],
                            ],
                            'addLabel' => __('Add charge'),
                        ]
                    ]
                ],
                [
                    'label'  => __('Invoices footer'),
                    'icon'   => 'fa-light fa-shoe-prints',
                    'fields' => [
                        'invoice_footer' => [
                            'type'  => 'textEditor',
                            'label' => __('Invoice footer'),
                            'full'  => true,
                            'value' => $paymentAccountShop->invoice_footer
                        ],
                    ],
                ],
            ],
            default => []
        };
    }
}
