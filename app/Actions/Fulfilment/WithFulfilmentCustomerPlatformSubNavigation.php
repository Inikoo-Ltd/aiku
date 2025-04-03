<?php
/*
 * author Arya Permana - Kirin
 * created on 02-04-2025-16h-03m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment;

use App\Enums\Fulfilment\RentalAgreement\RentalAgreementStateEnum;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Ordering\ModelHasPlatform;
use Lorisleiva\Actions\ActionRequest;

trait WithFulfilmentCustomerPlatformSubNavigation
{
    public function getFulfilmentCustomerPlatformSubNavigation(ModelHasPlatform $modelHasPlatform, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): array
    {
        $subNavigation = [];

        $subNavigation[] = [
            'isAnchor' => true,
            'route' => [
                'name'      => 'grp.org.fulfilments.show.crm.customers.show.platforms.show',
                'parameters' => $request->route()->originalParameters()

            ],

            'label'     => __('Channel'),
            'leftIcon'  => [
                'icon'    => 'fal fa-parachute-box',
                'tooltip' => __('channel'),
            ],
        ];

        $subNavigation[] = [
            'label'     => __('Portfolios'),
            'route' => [
                'name'      => 'grp.org.fulfilments.show.crm.customers.show.platforms.show.portfolios.index',
                'parameters' => $request->route()->originalParameters()

            ],
            'leftIcon'  => [
                'icon'    => 'fal fa-box',
                'tooltip' => __('portfolio'),
            ],
        ];
        
        $subNavigation[] = [
            'label'     => __('Clients'),
            'leftIcon'  => [
                'icon'    => 'fal fa-users',
                'tooltip' => __('clients'),
            ],
        ];
        $subNavigation[] = [
            'label'     => __('Orders'),
            'leftIcon'  => [
                'icon'    => 'fal fa-shopping-cart',
                'tooltip' => __('order'),
            ],
        ];

        return $subNavigation;
    }
}
