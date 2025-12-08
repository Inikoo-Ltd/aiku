<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 15:47:35 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\Asset;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\EnumHelperTrait;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

enum AssetTypeEnum: string
{
    use EnumHelperTrait;

    case PRODUCT = 'product';
    case SERVICE = 'service';
    case SUBSCRIPTION = 'subscription';
    case RENTAL = 'rental';
    case CHARGE = 'charge';
    case SHIPPING_ZONE = 'shipping_zone';

    public static function labels(Shop|Organisation|Group|ProductCategory|Collection|null $parent = null): array
    {
        $labels = [
            'product' => __('Product'),
            'service' => __('Services'),
            'subscription' => __('Subscriptions'),
            'rental' => __('Rentals'),
            'charge' => __('Charges'),
            'shipping_zone' => __('Shipping zones'),
        ];

        if ($parent instanceof Shop) {
            unset($labels['subscription']);
            if ($parent->type != ShopTypeEnum::FULFILMENT) {
                unset($labels['rental']);
            }
        }

        return $labels;
    }

    public static function typeIcon(): array
    {
        return [
            'product' => [
                'tooltip' => __('Physical good'),
                'icon' => 'fal fa-cube',
                'app' => [
                    'name' => 'cube',
                    'type' => 'font-awesome-5',
                ],
            ],
            'subscription' => [
                'tooltip' => __('Subscription'),
                'icon' => 'fal fa-bell',
                'app' => [
                    'name' => 'bell',
                    'type' => 'font-awesome-5',
                ],
            ],
            'service' => [
                'tooltip' => __('Service'),
                'icon' => 'fal fa-concierge-bell',
                'app' => [
                    'name' => 'concierge-bell',
                    'type' => 'font-awesome-5',
                ],
            ],
            'rental' => [
                'tooltip' => __('Rental'),
                'icon' => 'fal fa-garage',
                'app' => [
                    'name' => 'garage',
                    'type' => 'font-awesome-5',
                ],
            ],
            'shipping_zone' => [
                'tooltip' => __('Shipping zone'),
                'icon' => 'fal fa-shipping-fast',
                'app' => [
                    'name' => 'shipping-fast',
                    'type' => 'font-awesome-5',
                ],
            ],
            'charge' => [
                'tooltip' => __('Charges'),
                'icon' => 'fal fa-charging-station',
                'app' => [
                    'name' => 'shipping-fast',
                    'type' => 'font-awesome-5',
                ],
            ],

        ];
    }

    public static function count(Shop|Organisation|Group|ProductCategory|Collection $parent): array
    {
        if ($parent instanceof Shop) {
            $stats = $parent->stats;
        } elseif ($parent instanceof ProductCategory) {
            $stats = $parent->shop->stats;
        } elseif ($parent instanceof Collection) {
            $stats = $parent->shop->stats;
        } else {
            $stats = $parent->catalogueStats;
        }

        $counts = [
            'product' => $stats->number_assets_type_products,
            'subscription' => $stats->number_assetd_type_subscription,
            'service' => $stats->number_assets_type_service,
            'rental' => $stats->number_assets_type_rental,
            'charge' => $stats->number_assets_type_charge,
            'shipping_zone' => $stats->number_assets_type_shipping_zone,
        ];

        if ($parent instanceof Shop) {
            unset($counts['subscription']);
            if ($parent->type != ShopTypeEnum::FULFILMENT) {
                unset($counts['rental']);
            }
        }

        return $counts;
    }
}
