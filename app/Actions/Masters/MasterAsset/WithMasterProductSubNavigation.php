<?php

/*
 * author Louis Perez
 * created on 09-03-2026-16h-03m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterAsset;

use App\Models\Masters\MasterAsset;

trait WithMasterProductSubNavigation
{
    protected function getMasterProductsSubNavigation(MasterAsset $masterProduct): array
    {
        $masterShop = $masterProduct->masterShop;
        $currentRoute = request()->route()->getName();

        $masterProductRoute = [
            'name'          => 'grp.masters.master_shops.show.master_products.show',
            'parameters'    => [
                'masterShop'        => $masterShop->slug,
                'masterProduct'     => $masterProduct->slug,
            ]
        ];

        $productInShopRoute = [
            'name'          => 	'grp.masters.master_shops.show.master_products.products',
            'parameters'    => [
                'masterShop'        => $masterShop->slug,
                'masterProduct'     => $masterProduct->slug,
            ]
        ];

        $isProductRoute = preg_match('/products$/', $currentRoute);
        $lastStringCheck = $isProductRoute ? 'products' : 'show';

        if (in_array($currentRoute, [
            "grp.masters.master_shops.show.master_products.mismatch_detected.{$lastStringCheck}",
            "grp.masters.master_shops.show.master_departments.show.master_families.show.master_products.{$lastStringCheck}",
            "grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.master_products.{$lastStringCheck}",
            "grp.masters.master_shops.show.master_departments.show.master_products.{$lastStringCheck}",
            "grp.masters.master_shops.show.master_families.master_products.{$lastStringCheck}"
        ])) {
            $masterProductRoute = [
                'name'          => $this->getMasterProductRoute($isProductRoute, $currentRoute),
                'parameters'    => request()->route()->originalParameters()
            ];

            $productInShopRoute = [
                'name'          => $this->getProductInShopRoute($isProductRoute, $currentRoute),
                'parameters'    => request()->route()->originalParameters()
            ];
        }

        return [
            [
                'isAnchor' => true,
                'label'    => __('Master Product'),
                'route'    => $masterProductRoute,
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Products')
                ]
            ],
            [
                'label'    => __('Product in Shop'),
                'number'   => $masterProduct->stats->number_current_assets,
                'route'    => $productInShopRoute,
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-store'],
                    'tooltip' => __('Products in shop')
                ]
            ],
        ];
    }

    private function getMasterProductRoute(bool $isProductRoute, string $currentRoute)
    {
        return !$isProductRoute ? $currentRoute : preg_replace("/products$/", "show", $currentRoute);
    }

    private function getProductInShopRoute(bool $isProductRoute, string $currentRoute)
    {
        return $isProductRoute ? $currentRoute : preg_replace("/show$/", "products", $currentRoute);
    }
}
