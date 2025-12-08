<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 07-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Fulfilment\Pallet;

use App\Models\Fulfilment\FulfilmentCustomer;

trait WithRetinaPalletSubNavigation
{
    public function getPalletSubNavigation(FulfilmentCustomer $fulfilmentCustomer): array
    {

        $subNavigation = [
            [
                'number' => $fulfilmentCustomer->number_pallets_status_storing + $fulfilmentCustomer->number_pallets_status_returning,
                'isAnchor' => true,
                'leftIcon' => [
                    'icon' => 'fal fa-warehouse-alt',
                    'tooltip' => __('Storing'),
                ],
                'label' => __('Storing'),
                'route' => [
                    'name' => 'retina.fulfilment.storage.pallets.storing_pallets.index',
                ],
            ],
            [
                'number' => $fulfilmentCustomer->number_pallets_status_in_process,
                'leftIcon' => [
                    'icon' => 'fal fa-seedling',
                    'tooltip' => __('In Process'),
                ],
                'label' => __('In Process'),
                'route' => [
                    'name' => 'retina.fulfilment.storage.pallets.in_process_pallets.index',
                ],
            ],
            [
                'number' => $fulfilmentCustomer->number_pallets_status_returned,
                'leftIcon' => [
                    'icon' => 'fal fa-arrow-alt-from-left',
                    'tooltip' => __('Returned'),
                ],
                'label' => __('Returned'),
                'route' => [
                    'name' => 'retina.fulfilment.storage.pallets.returned_pallets.index',
                ],
            ],
            [
                'number' => $fulfilmentCustomer->number_pallets_status_incident,
                'leftIcon' => [
                    'icon' => 'fal fa-sad-cry',
                    'tooltip' => __('Incidents'),
                ],
                'label' => __('Incidents'),
                'route' => [
                    'name' => 'retina.fulfilment.storage.pallets.incidents_pallets.index',
                ],
            ],
            [
                'number' => $fulfilmentCustomer->number_pallets,
                'leftIcon' => [
                    'icon' => 'fal fa-align-justify',
                    'tooltip' => __('All'),
                ],
                'label' => __('All'),
                'route' => [
                    'name' => 'retina.fulfilment.storage.pallets.index',
                ],
            ],

        ];

        return $subNavigation;
    }
}
