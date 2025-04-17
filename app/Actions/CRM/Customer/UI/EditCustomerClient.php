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
use App\Models\Dropshipping\CustomerClient;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Helpers\Address;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditCustomerClient extends OrgAction
{
    private Customer|FulfilmentCustomer|CustomerHasPlatform $parent;

    public function handle(CustomerClient $customerClient, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('edit client'),
                'pageHead'    => [
                    'title'        => __('edit client'),
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
                                    'shops.show.customers.create' => 'shops.show.customers.index',
                                    default                       => preg_replace('/edit$/', 'show', $request->route()->getName())
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
                                        'label' => __('company'),
                                        'value' => $customerClient->company_name
                                    ],
                                    'contact_name' => [
                                        'type'  => 'input',
                                        'label' => __('contact name'),
                                        'value' => $customerClient->contact_name
                                    ],
                                    'email' => [
                                        'type'  => 'input',
                                        'label' => __('email'),
                                        'value' => $customerClient->email
                                    ],
                                    'phone' => [
                                        'type'  => 'input',
                                        'label' => __('phone'),
                                        'value' => $customerClient->phone
                                    ],
                                    'address'      => [
                                        'type'    => 'address',
                                        'label'   => __('Address'),
                                        'value'   => AddressFormFieldsResource::make(
                                            new Address(
                                                [
                                                    'country_id' => $customerClient->shop->country_id,

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
                    'args' => [
                        'updateRoute'     => [
                            'name'      => 'grp.models.customer-client.update',
                            'parameters' => [
                                'customerClient' => $customerClient->id
                            ]
                        ]
                    ]
                ]
            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        $shop = $request->route()->parameter('shop');
        return $request->user()->authTo("crm.{$shop->id}.edit");
    }

    public function asController(Organisation $organisation, Shop $shop, Customer $customer, CustomerClient $customerClient, ActionRequest $request): Response
    {
        $this->parent = $customer;
        $this->initialisationFromShop($shop, $request);
        return $this->handle($customerClient, $request);
    }

    public function inPlatform(Organisation $organisation, Shop $shop, Customer $customer, CustomerHasPlatform $customerHasPlatform, CustomerClient $customerClient, ActionRequest $request): Response
    {
        $this->parent = $customerHasPlatform;
        $this->initialisationFromShop($shop, $request);
        return $this->handle($customerClient, $request);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowCustomerClient::make()->getBreadcrumbs(
                parent: $this->parent,
                routeName: preg_replace('/edit$/', 'show', $routeName), // Adjust to match edit/index
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Editing Client'),
                    ]
                ]
            ]
        );
    }
}
