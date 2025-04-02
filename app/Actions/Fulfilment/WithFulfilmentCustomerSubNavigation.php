<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 19 May 2024 12:26:53 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment;

use App\Enums\Fulfilment\RentalAgreement\RentalAgreementStateEnum;
use App\Models\Fulfilment\FulfilmentCustomer;
use Lorisleiva\Actions\ActionRequest;

trait WithFulfilmentCustomerSubNavigation
{
    public function getFulfilmentCustomerSubNavigation(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): array
    {

        $user = $request->user();

        $subNavigation = [];

        $subNavigation[] = [
            'isAnchor' => true,
            'route' => [
                'name'      => 'grp.org.fulfilments.show.crm.customers.show',
                'parameters' => $request->route()->originalParameters()

            ],

            'label'     => __('Customer'),
            'leftIcon'  => [
                'icon'    => 'fal fa-stream',
                'tooltip' => __('customer'),
            ],


        ];

        if ($fulfilmentCustomer->space_rental) {

            $subNavigation[] = [
                'route' => [
                    'name'      => 'grp.org.fulfilments.show.crm.customers.show.spaces.index',
                    'parameters' => $request->route()->originalParameters()
                ],

                'label'     => __("Spaces"),
                'leftIcon'  => [
                    'icon'    => 'fal fa-parking',
                    'tooltip' => __("Customer's Spaces"),
                ],
                'number' => $fulfilmentCustomer->number_spaces

            ];

        }


        if ($user->hasPermissionTo('fulfilment.'.$fulfilmentCustomer->fulfilment->id.'.view')) {

            $subNavigation[] = [
                'route' => [
                    'name'      => 'grp.org.fulfilments.show.crm.customers.show.web-users.index',
                    'parameters' => $request->route()->originalParameters()

                ],

                'label'     => __('Web users'),
                'leftIcon'  => [
                    'icon'    => 'fal fa-terminal',
                    'tooltip' => __('Web users'),
                ],
                'number' => $fulfilmentCustomer->customer->stats->number_web_users

            ];
        }

        if ($fulfilmentCustomer->dropshipping) {
            $subNavigation[] = [
                'route' => [
                    'name'      => 'grp.org.fulfilments.show.crm.customers.show.platforms.index',
                    'parameters' => $request->route()->originalParameters()

                ],

                'label'     => __('Channels'),
                'leftIcon'  => [
                    'icon'    => 'fal fa-parachute-box',
                    'tooltip' => __('Channels'),
                ],
                'number' => $fulfilmentCustomer->customer->platforms->count()

            ];
        }

        if ($fulfilmentCustomer->pallets_storage && $fulfilmentCustomer->rentalAgreement()->where('state', RentalAgreementStateEnum::ACTIVE)->exists()) {
            $subNavigation[] = [
                'route' => [
                    'name'      => 'grp.org.fulfilments.show.crm.customers.show.pallets.index',
                    'parameters' => $request->route()->originalParameters()

                ],

                'label'     => __('Pallets'),
                'leftIcon'  => [
                    'icon'    => 'fal fa-pallet',
                    'tooltip' => __('Pallets'),
                ],
                'number' => $fulfilmentCustomer->number_pallets_status_storing

            ];

            if ($fulfilmentCustomer->items_storage) {

                $subNavigation[] = [
                    'route' => [
                        'name'      => 'grp.org.fulfilments.show.crm.customers.show.stored-items.index',
                        'parameters' => $request->route()->originalParameters()
                    ],

                    'label'     => __("SKUs"),
                    'leftIcon'  => [
                        'icon'    => 'fal fa-narwhal',
                        'tooltip' => __("Customer's SKUs"),
                    ],
                    'number' => $fulfilmentCustomer->number_stored_items_state_active

                ];

                if (!app()->isProduction()) {
                    $subNavigation[] = [
                        'route' => [
                            'name'      => 'grp.org.fulfilments.show.crm.customers.show.customer-clients.index',
                            'parameters' => $request->route()->originalParameters()
                        ],

                        'label'     => __("Clients"),
                        'leftIcon'  => [
                            'icon'    => 'fal fa-users',
                            'tooltip' => __("Customer's Clients"),
                        ],
                        'number' => $fulfilmentCustomer->number_stored_items_state_active
                    ];
                }
            }

            $subNavigation[] = [
                'route' => [
                    'name'      => 'grp.org.fulfilments.show.crm.customers.show.pallet_deliveries.index',
                    'parameters' => $request->route()->originalParameters()
                ],

                'label'     => __('Deliveries'),
                'leftIcon'  => [
                    'icon'    => 'fal fa-truck-couch',
                    'tooltip' => __('Pallet deliveries'),
                ],
                'number' => $fulfilmentCustomer->number_pallet_deliveries

            ];


        }



        if (($fulfilmentCustomer->pallets_storage || $fulfilmentCustomer->dropshipping) &&
            (
                $fulfilmentCustomer->number_pallets_status_storing   ||
                $fulfilmentCustomer->number_pallets_status_returning ||
                $fulfilmentCustomer->number_pallets_status_returned  ||
                $fulfilmentCustomer->number_pallet_returns
            )) {


            $subNavigation[] = [
                'route' => [
                    'name'      => 'grp.org.fulfilments.show.crm.customers.show.pallet_returns.index',
                    'parameters' => $request->route()->originalParameters()
                ],

                'label'     => __('Returns'),
                'leftIcon'  => [
                    'icon'    => 'fal fa-sign-out-alt',
                    'tooltip' => __('Pallet returns'),
                ],
                'number' => $fulfilmentCustomer->number_pallet_returns

            ];

        }

        if ($fulfilmentCustomer->rentalAgreement()->exists()) {


            $subNavigation[] = [
                'route' => [
                    'name'      => 'grp.org.fulfilments.show.crm.customers.show.recurring_bills.index',
                    'parameters' => $request->route()->originalParameters()
                ],

                'label'     => __('Recurring bills'),
                'leftIcon'  => [
                    'icon'    => 'fal fa-receipt',
                    'tooltip' => __('Recurring bills'),
                ],
                'number' => $fulfilmentCustomer->number_recurring_bills

            ];

            $subNavigation[] = [
                'route' => [
                    'name'      => 'grp.org.fulfilments.show.crm.customers.show.invoices.index',
                    'parameters' => $request->route()->originalParameters()
                ],

                'label'     => __('Invoices'),
                'leftIcon'  => [
                    'icon'    => 'fal fa-file-invoice-dollar',
                    'tooltip' => __('Invoices'),
                ],
                'number' => $fulfilmentCustomer->customer->stats->number_invoices

            ];
        }


        return $subNavigation;
    }
}
