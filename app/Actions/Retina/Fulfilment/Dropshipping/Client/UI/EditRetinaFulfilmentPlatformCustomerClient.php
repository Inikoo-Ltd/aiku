<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 20-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Fulfilment\Dropshipping\Client\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\RetinaAction;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditRetinaFulfilmentPlatformCustomerClient extends RetinaAction
{
    public function handle(CustomerClient $customerClient, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $customerClient,
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
                                "label"  => __("Profile"),
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
                                            $customerClient->address
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
                            'name'      => 'retina.models.customer-client.update',
                            'parameters' => [
                                'customerClient' => $customerClient->id
                            ],
                            'method' => 'patch'
                        ]
                    ]
                ]
            ]
        );
    }

    public function asController(
        CustomerSalesChannel $customerSalesChannel,
        CustomerClient $customerClient,
        ActionRequest $request
    ): Response {
        $this->initialisation($request);

        return $this->handle($customerClient, $request);
    }

    public function getBreadcrumbs(CustomerClient $customerClient, $routeName, $routeParameters): array
    {
        return array_merge(
            ShowRetinaFulfilmentCustomerClient::make()->getBreadcrumbs(
                $customerClient,
                $routeName,
                $routeParameters
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
