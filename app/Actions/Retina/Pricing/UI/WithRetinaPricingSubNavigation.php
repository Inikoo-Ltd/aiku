<?php

/*
 * author Arya Permana - Kirin
 * created on 21-01-2025-10h-56m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Pricing\UI;

use App\Models\Fulfilment\Fulfilment;

trait WithRetinaPricingSubNavigation
{
    protected function getPricingNavigation(Fulfilment $parent): array
    {
        return [
            [
                'isAnchor' => true,
                'label' => __('All'),

                'route' => [
                    'name' => 'retina.fulfilment.pricing.index',
                    'parameters' => [],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-usd-circle'],
                    'tooltip' => __('All Pricings'),
                ],
            ],
            [
                'number' => $parent->shop->stats->number_assets_type_product,
                'label' => __('Goods'),
                'route' => [
                    'name' => 'retina.fulfilment.pricing.goods',
                    'parameters' => [],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-cube'],
                    'tooltip' => __('Goods'),
                ],
            ],
            [
                'number' => $parent->shop->stats->number_assets_type_service,
                'label' => __('Services'),
                'route' => [
                    'name' => 'retina.fulfilment.pricing.services',
                    'parameters' => [],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-concierge-bell'],
                    'tooltip' => __('Services'),
                ],
            ],
            [
                'number' => $parent->shop->stats->number_assets_type_rental,
                'label' => __('Rentals'),
                'route' => [
                    'name' => 'retina.fulfilment.pricing.rentals',
                    'parameters' => [],
                ],
                'leftIcon' => [
                    'icon' => ['fal', 'fa-garage'],
                    'tooltip' => __('Rentals'),
                ],
            ],
        ];
    }
}
