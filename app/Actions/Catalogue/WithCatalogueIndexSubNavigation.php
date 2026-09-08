<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue;

use App\Models\Catalogue\Shop;

trait WithCatalogueIndexSubNavigation
{
    protected function getCatalogueIndexSubNavigation(Shop $shop, string $label, string $indexRoute, string $salesRoute, array $icon, ?int $number = null): array
    {
        $parameters = [$shop->organisation->slug, $shop->slug];

        return [
            [
                'isAnchor' => true,
                'label'    => $label,
                'number'   => $number,
                'route'    => [
                    'name'       => $indexRoute,
                    'parameters' => $parameters
                ],
                'leftIcon' => [
                    'icon'    => $icon,
                    'tooltip' => $label
                ]
            ],
            [
                'label'    => __('Sales'),
                'route'    => [
                    'name'       => $salesRoute,
                    'parameters' => $parameters
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-money-bill-wave'],
                    'tooltip' => __('Sales')
                ]
            ],
        ];
    }

    protected function getFamiliesIndexSubNavigation(Shop $shop): array
    {
        return $this->getCatalogueIndexSubNavigation(
            $shop,
            __('Families'),
            'grp.org.shops.show.catalogue.families.index',
            'grp.org.shops.show.catalogue.families.sales',
            ['fal', 'fa-folder'],
            $shop->stats->number_current_families
        );
    }

    protected function getDepartmentsIndexSubNavigation(Shop $shop): array
    {
        return $this->getCatalogueIndexSubNavigation(
            $shop,
            __('Departments'),
            'grp.org.shops.show.catalogue.departments.index',
            'grp.org.shops.show.catalogue.departments.sales',
            ['fal', 'fa-folder-tree'],
            $shop->stats->number_current_departments
        );
    }

    protected function getSubDepartmentsIndexSubNavigation(Shop $shop): array
    {
        return $this->getCatalogueIndexSubNavigation(
            $shop,
            __('Sub-departments'),
            'grp.org.shops.show.catalogue.sub_departments.index',
            'grp.org.shops.show.catalogue.sub_departments.sales',
            ['fal', 'fa-folder-download'],
            $shop->stats->number_current_sub_departments
        );
    }
}
