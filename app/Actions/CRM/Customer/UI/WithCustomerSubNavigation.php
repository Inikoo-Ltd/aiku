<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Jun 2024 14:05:15 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\UI;

use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerHasPlatform;
use App\Models\Dropshipping\CustomerClient;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

trait WithCustomerSubNavigation
{
    protected function getCustomerSubNavigation(Customer $customer, ActionRequest $request): array
    {

        $deliveryNotedLabel = __('Delivery notes');
        $webUsersLabel = __('Web users');

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

                'label'    => $webUsersLabel,
                'leftIcon' => [
                    'icon'    => 'fal fa-terminal',
                    'tooltip' => $webUsersLabel,
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
                'label'    => $deliveryNotedLabel,
                'number'   => $customer->stats->number_delivery_notes,
                'route'     => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.delivery_notes.index',
                    'parameters' => [$this->organisation->slug, $customer->shop->slug, $customer->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-truck'],
                    'tooltip' => $deliveryNotedLabel
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
        $deliveryNotedLabel = __('Delivery notes');
        $webUsersLabel = __('Web users');

        $baseNavigation = [
            [
                'isAnchor' => true,
                'label'    => __('Customer'),
                'route'    => [
                    'name'       => 'grp.org.shops.show.crm.customers.show',
                    'parameters' => [$this->organisation->slug, $customer->shop->slug, $customer->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Customer')
                ]
            ],
            [
                'route'    => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.platforms.index',
                    'parameters' => $request->route()->originalParameters()
                ],
                'label'    => __('Channels'),
                'leftIcon' => [
                    'icon'    => 'fal fa-parachute-box',
                    'tooltip' => __('Channels'),
                ],
                'number'   => $customer->platforms->count()
            ],
        ];

        if ($customer->status != CustomerStatusEnum::APPROVED && Arr::get($customer->shop->settings, 'registration.require_approval')) {
            return $baseNavigation;
        }

        return array_merge($baseNavigation, [
            [
                'route'    => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.web-users.index',
                    'parameters' => $request->route()->originalParameters()
                ],
                'label'    => $webUsersLabel,
                'leftIcon' => [
                    'icon'    => 'fal fa-terminal',
                    'tooltip' => $webUsersLabel,
                ],
                'number'   => $customer->stats->number_web_users
            ],
            [
                'label'    => __('Orders'),
                'number'   => $customer->stats->number_orders,
                'route'    => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.orders.index',
                    'parameters' => [$this->organisation->slug, $customer->shop->slug, $customer->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-shopping-cart'],
                    'tooltip' => __('orders')
                ]
            ],
            [
                'label'    => $deliveryNotedLabel,
                'number'   => $customer->stats->number_delivery_notes,
                'route'    => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.delivery_notes.index',
                    'parameters' => [$this->organisation->slug, $customer->shop->slug, $customer->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-truck'],
                    'tooltip' => $deliveryNotedLabel
                ]
            ],
            [
                'label'    => __('Invoices'),
                'number'   => $customer->stats->number_invoices,
                'route'    => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.invoices.index',
                    'parameters' => [$this->organisation->slug, $customer->shop->slug, $customer->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-file-invoice'],
                    'tooltip' => __('invoices')
                ]
            ],
        ]);
    }

    protected function getCustomerClientSubNavigation(CustomerClient $customerClient, CustomerHasPlatform $customerHasPlatform): array
    {
        return [
            [
                'isAnchor' => true,
                'label'    => __('Client'),
                'route'     => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.platforms.show.customer_clients.show',
                    'parameters' => [$this->organisation->slug, $customerClient->shop->slug, $customerClient->customer->slug, $customerHasPlatform->id, $customerClient->ulid]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Client')
                ]
            ],
            [
                'label'    => __('Orders'),
                'number'   => $customerClient->stats->number_orders ?? 0,
                'route'     => [
                    'name'       => 'grp.org.shops.show.crm.customers.show.platforms.show.customer_clients.show.orders.index',
                    'parameters' => [$this->organisation->slug, $customerClient->shop->slug, $customerClient->customer->slug, $customerHasPlatform->id, $customerClient->ulid]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-shopping-cart'],
                    'tooltip' => __('orders')
                ]
            ],
        ];
    }


}
