<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Jun 2024 14:05:15 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue;

use App\Models\Catalogue\Shop;

trait WithCollectionsSubNavigation
{
    public function getCollectionsSubNavigation(Shop $shop): array
    {
        $stats = $shop->stats;

        return [
            [
                'label'  => __('Active'),
                'root'   => 'grp.org.shops.show.catalogue.collections.active.index',
                'route'  => [
                    'name'       => 'grp.org.shops.show.catalogue.collections.active.index',
                    'parameters' => [
                        $this->organisation->slug,
                        $this->shop->slug
                    ]
                ],
                'number' => $stats->number_collections_state_active
            ],
            [
                'label'  => __('Inactive'),
                'root'   => 'grp.org.shops.show.catalogue.collections.inactive.index',
                'route'  => [
                    'name'       => 'grp.org.shops.show.catalogue.collections.inactive.index',
                    'parameters' => [
                        $this->organisation->slug,
                        $this->shop->slug
                    ]
                ],
                'number' => $stats->number_collections_state_inactive,
            ],
            [
                'label'  => __('In Process'),
                'root'   => 'grp.org.shops.show.catalogue.collections.in_process.index',
                'route'  => [
                    'name'       => 'grp.org.shops.show.catalogue.collections.in_process.index',
                    'parameters' => [
                        $this->organisation->slug,
                        $this->shop->slug
                    ]
                ],
                'number' => $stats->number_collections_state_in_process,
            ],
            [
                'label'  => __('All'),
                'root'   => 'grp.org.shops.show.catalogue.collections.index',
                'route'  => [
                    'name'       => 'grp.org.shops.show.catalogue.collections.index',
                    'parameters' => [
                        $this->organisation->slug,
                        $this->shop->slug
                    ]
                ],
                'number' => $stats->number_collections,
                'align'  => 'right'
            ],
        ];
    }

}
