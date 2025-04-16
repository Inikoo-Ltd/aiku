<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\OrgAction;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerHasPlatform;
use App\Models\Helpers\Address;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateCustomerClient extends OrgAction
{
    private CustomerHasPlatform|Customer $scope;

    public function handle(Customer $customer, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('new client'),
                'pageHead'    => [
                    'title'        => __('new client'),
                    'icon'         => [
                        'icon'  => ['fal', 'fa-user'],
                        'title' => __('client')
                    ],
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => match ($request->route()->getName()) {
                                    'grp.org.shops.show.crm.customers.show.customer-clients.create' => preg_replace('/create$/', 'index', $request->route()->getName()),
                                    'grp.org.shops.show.crm.customers.show.platforms.show.customer-clients.create' => preg_replace('/create$/', 'aiku.index', $request->route()->getName()),
                                    default                       => preg_replace('/create$/', 'index', $request->route()->getName())
                                },
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' =>
                        [
                            [
                                'title'  => __('contact'),
                                'fields' => [
                                    'company_name' => [
                                        'type'  => 'input',
                                        'label' => __('company')
                                    ],
                                    'contact_name' => [
                                        'type'  => 'input',
                                        'label' => __('contact name')
                                    ],
                                    'email' => [
                                        'type'  => 'input',
                                        'label' => __('email')
                                    ],
                                    'phone' => [
                                        'type'  => 'input',
                                        'label' => __('phone')
                                    ],
                                    'address'      => [
                                        'type'    => 'address',
                                        'label'   => __('Address'),
                                        'value'   => AddressFormFieldsResource::make(
                                            new Address(
                                                [
                                                    'country_id' => $customer->shop->country_id,

                                                ]
                                            )
                                        )->getArray(),
                                        'options' => [
                                            'countriesAddressData' => GetAddressData::run()

                                        ]
                                    ]
                                ]
                            ]
                        ],
                    'route'     => $this->scope instanceof CustomerHasPlatform ? [
                        'name'      => 'grp.models.customer.platform-client.store',
                        'parameters' => [
                            'customer'     => $customer->id,
                            'platform'     => $this->scope->platform_id
                            ]
                    ] : [
                        'name'      => 'grp.models.customer.client.store',
                        'parameters' => [
                            'customer'     => $customer->id
                            ]
                    ]
                ]

            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }


    public function asController(Organisation $organisation, Shop $shop, Customer $customer, ActionRequest $request): Response
    {
        $this->scope = $customer;
        $this->initialisationFromShop($shop, $request);
        return $this->handle($customer, $request);
    }

    public function inPlatformInCustomer(Organisation $organisation, Shop $shop, Customer $customer, CustomerHasPlatform $customerHasPlatform, ActionRequest $request): Response
    {
        $this->scope = $customerHasPlatform;
        $this->initialisationFromShop($shop, $request);
        return $this->handle($customer, $request);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            'grp.org.shops.show.crm.customers.show.customer-clients.create' =>
            array_merge(
                IndexCustomerClients::make()->getBreadcrumbs(
                    routeName: preg_replace('/create$/', 'index', $routeName),
                    routeParameters: $routeParameters,
                ),
                [
                    [
                        'type'          => 'creatingModel',
                        'creatingModel' => [
                            'label' => __('Creating Client'),
                        ]
                    ]
                ]
            ),
            'grp.org.shops.show.crm.customers.show.platforms.show.customer-clients.create' =>
            array_merge(
                IndexCustomerClients::make()->getBreadcrumbs(
                    routeName: preg_replace('/create$/', 'aiku.index', $routeName),
                    routeParameters: $routeParameters,
                ),
                [
                    [
                        'type'          => 'creatingModel',
                        'creatingModel' => [
                            'label' => __('Creating Client'),
                        ]
                    ]
                ]
            ),
            default => []
        };
    }
}
