<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Jun 2024 14:05:15 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue;

use App\Models\Catalogue\Collection;

trait WithCollectionSubNavigation
{
    protected function getCollectionSubNavigation(Collection $collection): array
    {
        return [
            [
                'isAnchor' => true,
                'label'    => __('Collection'),
                'href'     => [
                        'name'       => 'grp.org.shops.show.catalogue.collections.show',
                        'parameters' => [$this->organisation->slug, $collection->shop->slug, $collection->slug]
                    ],
                    'leftIcon' => [
                        'icon'    => ['fal', 'fa-cube'],
                        'tooltip' => __('Collection')
                        ]
            ],
            [
                'label'    => __('Departments'),
                'number'   => $collection->stats->number_departments ?? 0,
                'href'     => [
                    'name'       => 'grp.org.shops.show.catalogue.collections.departments.index',
                    'parameters' => [$this->organisation->slug, $collection->shop->slug, $collection->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Sub-Department')
                ]
            ],
            [
                'label'    => __('Families'),
                'number'   => $collection->stats->number_families ?? 0,
                'href'     => [
                    'name'       => 'grp.org.shops.show.catalogue.collections.families.index',
                    'parameters' => [$this->organisation->slug, $collection->shop->slug, $collection->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-folder'],
                    'tooltip' => __('families')
                ]
            ],
            [
                'label'    => __('Products'),
                'number'   => $collection->stats->number_products ?? 0,
                'href'     => [
                    'name'       => 'grp.org.shops.show.catalogue.collections.products.index',
                    'parameters' => [$this->organisation->slug, $collection->shop->slug, $collection->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-cube'],
                    'tooltip' => __('products')
                ]
            ],
            [
                'label'    => __('Collections'),
                'number'   => $collection->stats->number_collections ?? 0,
                'href'     => [
                    'name'       => 'grp.org.shops.show.catalogue.collections.collections.index',
                    'parameters' => [$this->organisation->slug, $collection->shop->slug, $collection->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-cube'],
                    'tooltip' => __('collections')
                ]
            ],
        ];
    }

}
