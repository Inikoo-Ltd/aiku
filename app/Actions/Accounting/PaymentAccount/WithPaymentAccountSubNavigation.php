<?php

/*
 * author Arya Permana - Kirin
 * created on 10-01-2025-09h-06m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\PaymentAccount;

use App\Models\Accounting\PaymentAccount;

trait WithPaymentAccountSubNavigation
{
    protected function getPaymentAccountNavigation(PaymentAccount $parent): array
    {
        return [
            [
                "isAnchor" => true,
                "label"    => __($parent->name),

                "route"     => [
                    "name"       => "grp.org.accounting.payment-accounts.show",
                    "parameters" => [$parent->organisation->slug, $parent->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-money-check-alt"],
                    "tooltip" => __("Payment Account"),
                ],
            ],
            [
                "number"   => $parent->stats->number_payments,
                "label"    => __("Payments"),
                "route"     => [
                    "name"       => "grp.org.accounting.payment-accounts.show.payments.index",
                    "parameters" => [
                        'organisation' => $parent->organisation->slug,
                        'paymentAccount' => $parent->slug
                    ],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-coins"],
                    "tooltip" => __("payments"),
                ],
            ],
            [
                "number"   => $parent->stats->number_pas,
                "label"    => __("Shops"),
                "route"     => [
                    "name"       => "grp.org.accounting.payment-accounts.show.shops.index",
                    "parameters" => [
                        'organisation' => $parent->organisation->slug,
                        'paymentAccount' => $parent->slug
                    ],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-store-alt"],
                    "tooltip" => __("payment account shops"),
                ],
            ],
            [
                "number"   => $parent->stats->number_customers,
                "label"    => __("Customers"),
                "route"     => [
                    "name"       => "grp.org.accounting.payment-accounts.show.customers.index",
                    "parameters" => [
                        'organisation' => $parent->organisation->slug,
                        'paymentAccount' => $parent->slug
                    ],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-store-alt"],
                    "tooltip" => __("payment account customers"),
                ],
            ],
        ];
    }
}
