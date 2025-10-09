<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 11:59:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\ShippingZoneSchema;

use App\Models\Catalogue\Shop;

trait WithShippingZoneSchemaSubNavigation
{
    protected function getShippingZoneSchemaSubNavigation(Shop $shop): array
    {
        $navigation = [
            [
                'isAnchor' => true,
                'label'    => __('Schemas'),
                'route'     => [
                        'name'       => 'grp.org.shops.show.billables.shipping.index',
                        'parameters' => [$this->organisation->slug, $shop->slug]
                    ],
                    'leftIcon' => [
                        'icon'    => ['fal', 'fa-shipping-fast'],
                        'tooltip' => __('Shipping Schemas')
                        ]
            ],
        ];
        if ($shop->currentShippingZoneSchema) {
            $current      = [
                'label'    => __('Current'),
                'route'     => [
                    'name'       => 'grp.org.shops.show.billables.shipping.show',
                    'parameters' => [$this->organisation->slug, $shop->slug, $shop->currentShippingZoneSchema->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-cube'],
                    'tooltip' => __('Current Schema')
                ]
                ];
            $navigation[] = $current;
        }

        if ($shop->discountShippingZoneSchema) {
            $discount     = [
                'label'    => __('Discount'),
                'route'     => [
                    'name'       => 'grp.org.shops.show.billables.shipping.show',
                    'parameters' => [$this->organisation->slug, $shop->slug, $shop->discountShippingZoneSchema->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-cube'],
                    'tooltip' => __('Discount Schema')
                ]
                ];
            $navigation[] = $discount;
        }

        return $navigation;
    }

}
