<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 16 Oct 2024 10:47:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Client\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\RetinaAction;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Helpers\Address;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateRetinaCustomerClient extends RetinaAction
{
    /**
     * @var \App\Models\Dropshipping\CustomerSalesChannel|\Eloquent|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    private CustomerSalesChannel $scope;

    public function handle(ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
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
                                'name'       =>  preg_replace('/create$/', 'index', $request->route()->getName()),
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
                        'name'       => 'retina.models.customer_sales_channel.customer-client.store',
                        'parameters' => [
                            'customerSalesChannel' => $this->scope->id
                        ]
                    ]
                ]

            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root;
    }


    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);
        $this->parent = $this->customer;

        return $this->handle($request);
    }

    public function inPlatform(Platform $platform, ActionRequest $request): Response
    {

        $customer = $request->user()->customer;
        $this->initialisationFromPlatform($platform, $request);
        $customerSalesChannel = CustomerSalesChannel::where('customer_id', $customer->id)->where('platform_id', $platform->id)->first();
        $this->scope = $customerSalesChannel;

        return $this->handle($request);
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
