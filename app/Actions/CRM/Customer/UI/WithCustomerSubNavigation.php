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
                'label'    => $customer->name,
                'href'     => [
                    'name'       => 'grp.org.shops.show.crm.customers.show',
                    'parameters' => [$this->organisation->slug, $customer->shop->slug, $customer->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-user'],
                    'tooltip' => __('Customer')
                ]
            ],

            [
                'href' => [
                    'name'      => 'grp.org.shops.show.crm.customers.show.web-users.index',
                    'parameters'=> $request->route()->originalParameters()

                ],

                'label'     => __('Web users'),
                'leftIcon'  => [
                    'icon'    => 'fal fa-terminal',
                    'tooltip' => __('Web users'),
                ],
                'number'=> $customer->stats->number_web_users
            ],


            [
                'label'    => __('Clients'),
                'number'   => $customer->stats->number_clients,
                'href'     => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.customer-clients.index',
                    'parameters' => [$this->organisation->slug, $customer->shop->slug, $customer->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-folder'],
                    'tooltip' => __('clients')
                ]
            ],
            [
                'label'    => __('Orders'),
                'number'   => $customer->orders()->count(),
                'href'     => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.orders.index',
                    'parameters' => [$this->organisation->slug, $customer->shop->slug, $customer->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-shopping-cart'],
                    'tooltip' => __('orders')
                ]
            ],
            [
                'label'    => __('Portfolios'),
                'number'   => $customer->portfolios()->count(),
                'href'     => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.portfolios.index',
                    'parameters' => [$this->organisation->slug, $customer->shop->slug, $customer->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-folder'],
                    'tooltip' => __('portfolios')
                ]
            ],
        ];
    }

    protected function getCustomerClientSubNavigation(CustomerClient $customerClient, ActionRequest $request): array
    {
        return [
            [
                'label'    => $customerClient->name,
                'href'     => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.customer-clients.show',
                    'parameters' => [$this->organisation->slug, $customerClient->shop->slug, $customerClient->customer->slug, $customerClient->ulid]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-user'],
                    'tooltip' => __('Customer')
                ]
            ],
            [
                'label'    => __('Orders'),
                'number'   => $customerClient->orders()->count(),
                'href'     => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.customer-clients.orders.index',
                    'parameters' => [$this->organisation->slug, $customerClient->shop->slug, $customerClient->customer->slug, $customerClient->ulid]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-shopping-cart'],
                    'tooltip' => __('orders')
                ]
            ],
        ];
    }

}
