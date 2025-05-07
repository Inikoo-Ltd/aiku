<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 24-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Comms\Traits;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;

trait WithAccountingSubNavigation
{
    public function getSubNavigation(Fulfilment|Shop $parent): array
    {
        if ($parent instanceof Shop) {
            $shop = $parent;
        } else {
            $shop = $parent->shop;
        }
        return [
            [
                "isAnchor" => true,
                "label"    => __("Shop Accounting Dashboard"),

                "route"    => [
                    "name"       => "grp.org.fulfilments.show.operations.accounting.dashboard",
                    "parameters" => [$parent->organisation->slug, $parent->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-chart-network"],
                    "tooltip" => __("Shop Accounting Dashboard"),
                ],
            ],
            [
                "label"    => __("Accounts"),
                'number'   => $shop->accountingStats->number_payment_accounts ?? 0,
                "route"    => [
                    "name"       => "grp.org.fulfilments.show.operations.accounting.accounts.index",
                    "parameters" => [$parent->organisation->slug, $parent->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-inbox-out"],
                    "tooltip" => __("Accounts"),
                ],
            ],
            [
                "label"    => __("Payments"),
                'number'   => $shop->accountingStats->number_payments ?? 0,
                "route"    => [
                    "name"       => "grp.org.fulfilments.show.operations.accounting.payments.index",
                    "parameters" => [$parent->organisation->slug, $parent->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-inbox-out"],
                    "tooltip" => __("Outboxes"),
                ],
            ],
            [
                "label"    => __("customers balance"),
                'number'   => $shop->accountingStats->number_customers_with_balances ?? 0,
                "route"    => [
                    "name"       => "grp.org.fulfilments.show.operations.accounting.customer_balances.index",
                    "parameters" => [$parent->organisation->slug, $parent->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-inbox-out"],
                    "tooltip" => __("customer balance"),
                ],
            ],
        ];
    }
    public function getSubNavigationShop(Shop|Fulfilment $parent): array
    {
        if ($parent instanceof Shop) {
            $shop = $parent;
        } else {
            $shop = $parent->shop;
        }

        return [
            [
                "isAnchor" => true,
                "label"    => __("Shop Accounting Dashboard"),

                "route"    => [
                    "name"       => "grp.org.shops.show.dashboard.payments.accounting.dashboard",
                    "parameters" => [$parent->organisation->slug, $parent->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-chart-network"],
                    "tooltip" => __("Shop Accounting Dashboard"),
                ],
            ],
            [
                "label"    => __("Accounts"),
                'number'   => $shop->accountingStats->number_payment_accounts ?? 0,
                "route"    => [
                    "name"       => "grp.org.shops.show.dashboard.payments.accounting.accounts.index",
                    "parameters" => [$parent->organisation->slug, $parent->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-inbox-out"],
                    "tooltip" => __("Accounts"),
                ],
            ],
            [
                "label"    => __("Payments"),
                'number'   => $shop->accountingStats->number_payments ?? 0,
                "route"    => [
                    "name"       => "grp.org.shops.show.dashboard.payments.accounting.payments.index",
                    "parameters" => [$parent->organisation->slug, $parent->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-inbox-out"],
                    "tooltip" => __("Outboxes"),
                ],
            ],
            [
                "label"    => __("Customers Balance"),
                'number'   => $shop->accountingStats->number_customers_with_balances ?? 0,
                "route"    => [
                    "name"       => "grp.org.shops.show.dashboard.payments.accounting.customer_balances.index",
                    "parameters" => [$parent->organisation->slug, $parent->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-inbox-out"],
                    "tooltip" => __("customer balance"),
                ],
            ],
            $shop->type == ShopTypeEnum::DROPSHIPPING ? [
                "label"    => __("Top Ups"),
                'number'   => $shop->accountingStats->number_top_ups ?? 0,
                "route"    => [
                    "name"       => "grp.org.shops.show.dashboard.payments.accounting.top_ups.index",
                    "parameters" => [$parent->organisation->slug, $parent->slug],
                ],
                "leftIcon" => [
                    "icon"    => ["fal", "fa-money-bill-wave"],
                    "tooltip" => __("top ups"),
                ],
            ] : []
        ];
    }
}
