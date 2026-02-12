<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\Payment;

use App\Models\SysAdmin\Organisation;

trait WithPaymentSubNavigation
{
    protected function getPaymentSubNavigation(Organisation $parent): array
    {
        return [
            [
                'label' => __('Payments'),
                'route' => [
                    'name'       => 'grp.org.accounting.payments.index',
                    'parameters' => [$parent->slug]
                ],
            ],
            [
                'label' => __('Payment Methods'),
                'route' => [
                    'name'       => 'grp.org.accounting.payments.methods.index',
                    'parameters' => [$parent->slug]
                ],
            ],
        ];
    }
}
