<?php

namespace App\Actions\Helpers\Brand;

use App\Models\Helpers\Brand;

trait WithBrandSubNavigation
{
    protected function getBrandSubNavigation(Brand $brand): array
    {
        $currentRoute = request()->route()->getName();

        $brandRoute = [
            'name'          => 'grp.trade_units.brands.show',
            'parameters'    => [
                'brand'        => $brand->slug,
            ]
        ];

        if (in_array($currentRoute, [
            "grp.trade_units.brands.show",
            "grp.trade_units.brands.trade_units.index",
        ])) {
            $tradeUnitRoute = [
                'name'          => "grp.trade_units.brands.trade_units.index",
                'parameters'    => [
                    'brand'        => $brand->slug,
                ],
            ];
        }

        return [
            [
                'isAnchor' => true,
                'label'    => __('Brand'),
                'route'    => $brandRoute,
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-copyright'],
                    'tooltip' => __('Brand')
                ]
            ],
            [
                'label'    => __('Trade Units'),
                'number'   => $brand->number_trade_units,
                'route'    => $tradeUnitRoute,
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-atom'],
                    'tooltip' => __('Trade Units under this Brand')
                ]
            ],
        ];
    }
}
