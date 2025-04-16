<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Jun 2024 14:05:15 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\UI;

use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use Lorisleiva\Actions\ActionRequest;

trait WithCustomerSubNavigation
{
    protected function getCustomerSubNavigation(Customer $customer, ActionRequest $request): array
    {
        return [
            [
                'isAnchor'   => true,
                'label'    => __('Customer'),
                'route'     => [
                    'name'       => 'grp.org.shops.show.crm.customers.show',
                    'parameters' => [$this->organisation->slug, $customer->shop->slug, $customer->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-atom-alt'],
                    'tooltip' => __('Customer')
                ]
            ],

            [
                'route' => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.web-users.index',
                    'parameters' => $request->route()->originalParameters()

                ],

                'label'    => __('Web users'),
                'leftIcon' => [
                    'icon'    => 'fal fa-terminal',
                    'tooltip' => __('Web users'),
                ],
                'number'   => $customer->stats->number_web_users
            ],
            [
                'label'    => __('Orders'),
                'number'   => $customer->orders()->count(),
                'route'     => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.orders.index',
                    'parameters' => [$this->organisation->slug, $customer->shop->slug, $customer->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-shopping-cart'],
                    'tooltip' => __('Orders')
                ]
            ],
            [
                'label'    => __('Delivery notes'),
                'number'   => $customer->stats->number_delivery_notes,
                'route'     => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.delivery_notes.index',
                    'parameters' => [$this->organisation->slug, $customer->shop->slug, $customer->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-truck'],
                    'tooltip' => __('Delivery notes')
                ]
            ],
            [
                'label'    => __('Invoices'),
                'number'   => $customer->stats->number_invoices,
                'route'     => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.invoices.index',
                    'parameters' => [$this->organisation->slug, $customer->shop->slug, $customer->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-file-invoice'],
                    'tooltip' => __('invoices')
                ]
            ],
        ];
    }

    protected function getCustomerDropshippingSubNavigation(Customer $customer, ActionRequest $request): array
    {
        return [
            [
                'isAnchor' => true,
                'label'    => __('Customer'),
                'route'     => [
                    'name'       => 'grp.org.shops.show.crm.customers.show',
                    'parameters' => [$this->organisation->slug, $customer->shop->slug, $customer->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Customer')
                ]
            ],
            [
                'route' => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.web-users.index',
                    'parameters' => $request->route()->originalParameters()

                ],

                'label'    => __('Web users'),
                'leftIcon' => [
                    'icon'    => 'fal fa-terminal',
                    'tooltip' => __('Web users'),
                ],
                'number'   => $customer->stats->number_web_users
            ],
            [
                'route' => [
                    'name'      => 'grp.org.shops.show.crm.customers.show.platforms.index',
                    'parameters' => $request->route()->originalParameters()

                ],

                'label'     => __('Channels'),
                'leftIcon'  => [
                    'icon'    => 'fal fa-parachute-box',
                    'tooltip' => __('Channels'),
                ],
                'number' => $customer->platforms->count()

            ],
            // [
            //     'label'    => __('Clients'),
            //     'number'   => $customer->stats->number_current_customer_clients,
            //     'route'     => [
            //         'name'       => 'grp.org.shops.show.crm.customers.show.customer-clients.index',
            //         'parameters' => [$this->organisation->slug, $customer->shop->slug, $customer->slug]
            //     ],
            //     'leftIcon' => [
            //         'icon'    => ['fal', 'fa-user-friends'],
            //         'tooltip' => __('clients')
            //     ]
            // ],
            [
                'label'    => __('Portfolio'),
                'number'   => $customer->portfolios()->count(),
                'route'     => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.portfolios.index',
                    'parameters' => [$this->organisation->slug, $customer->shop->slug, $customer->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-chess-board'],
                    'tooltip' => __('portfolio')
                ]
            ],
            [
                'label'    => __('Orders'),
                'number'   => $customer->stats->number_orders,
                'route'     => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.orders.index',
                    'parameters' => [$this->organisation->slug, $customer->shop->slug, $customer->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-shopping-cart'],
                    'tooltip' => __('orders')
                ]
            ],
            [
                'label'    => __('Delivery notes'),
                'number'   => $customer->stats->number_delivery_notes,
                'route'     => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.delivery_notes.index',
                    'parameters' => [$this->organisation->slug, $customer->shop->slug, $customer->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-truck'],
                    'tooltip' => __('Delivery notes')
                ]
            ],
            [
                'label'    => __('Invoices'),
                'number'   => $customer->stats->number_invoices,
                'route'     => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.invoices.index',
                    'parameters' => [$this->organisation->slug, $customer->shop->slug, $customer->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-file-invoice'],
                    'tooltip' => __('invoices')
                ]
            ],
        ];
    }

    protected function getCustomerClientSubNavigation(CustomerClient $customerClient): array
    {
        return [
            [
                'isAnchor' => true,
                'label'    => __('Client'),
                'route'     => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.customer-clients.show',
                    'parameters' => [$this->organisation->slug, $customerClient->shop->slug, $customerClient->customer->slug, $customerClient->ulid]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Client')
                ]
            ],
            [
                'label'    => __('Orders'),
                'number'   => $customerClient->stats->number_orders,
                'route'     => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.customer-clients.orders.index',
                    'parameters' => [$this->organisation->slug, $customerClient->shop->slug, $customerClient->customer->slug, $customerClient->ulid]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-shopping-cart'],
                    'tooltip' => __('orders')
                ]
            ],
            [
                'label'    => __('Delivery notes'),
                'number'   => $customerClient->stats->number_delivery_notes,
                'route'     => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.customer-clients.delivery_notes.index',
                    'parameters' => [$this->organisation->slug, $customerClient->shop->slug, $customerClient->customer->slug, $customerClient->ulid]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-truck'],
                    'tooltip' => __('Delivery notes'),
                ]
            ],
            [
                'label'    => __('Invoices'),
                'number'   => $customerClient->stats->number_invoices,
                'route'     => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.customer-clients.invoices.index',
                    'parameters' => [$this->organisation->slug, $customerClient->shop->slug, $customerClient->customer->slug, $customerClient->ulid]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-file-invoice'],
                    'tooltip' => __('invoices')
                ]
            ],
        ];
    }


}
