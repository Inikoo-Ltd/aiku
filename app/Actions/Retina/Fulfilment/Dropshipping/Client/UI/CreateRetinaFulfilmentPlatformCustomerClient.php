<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 16 Oct 2024 10:47:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\Dropshipping\Client\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Retina\Dropshipping\Client\UI\IndexRetinaCustomerClients;
use App\Actions\RetinaAction;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Helpers\Address;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateRetinaFulfilmentPlatformCustomerClient extends RetinaAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('new client'),
                'pageHead'    => [
                    'title'   => __('new client'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-user'],
                        'title' => __('client')
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => preg_replace('/create$/', 'index', $request->route()->getName()),
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
                                    'email'        => [
                                        'type'  => 'input',
                                        'label' => __('email')
                                    ],
                                    'phone'        => [
                                        'type'  => 'input',
                                        'label' => __('phone')
                                    ],
                                    'address'      => [
                                        'type'    => 'address',
                                        'label'   => __('Address'),
                                        'value'   => AddressFormFieldsResource::make(
                                            new Address(
                                                [
                                                    'country_id' => $this->customer->shop->country_id,

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
                    'route'     => [
                        'name'       => 'retina.models.customer_sales_channel.fulfilment.customer-client.store',
                        'parameters' => [
                            'customerSalesChannel' => $customerSalesChannel->id
                        ]
                    ]
                ]

            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        $customerSalesChannel = $request->route()->parameter('customerSalesChannel');
        if ($customerSalesChannel->customer_id == $this->customer->id) {
            return true;
        }
        return false;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle($customerSalesChannel, $request);
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            IndexRetinaCustomerClients::make()->getBreadcrumbs(),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating Client'),
                    ]
                ]
            ]
        );
    }
}
