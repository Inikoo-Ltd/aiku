<?php

/*
 * author Arya Permana - Kirin
 * created on 16-05-2025-16h-28m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Client\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\RetinaAction;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditRetinaCustomerClient extends RetinaAction
{
    public function handle(CustomerClient $customerClient, ActionRequest $request): Response
    {
        $request->route()->getName();

        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $customerClient,
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
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
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
                                        'label'   => __('Delivery Address'),
                                        'value'   => AddressFormFieldsResource::make(
                                            $customerClient->address
                                        )->getArray(),
                                        'options' => [
                                            'countriesAddressData' => GetAddressData::run()

                                        ]
                                    ]
                                ]
                            ],
                            [
                                "label"  => __("Status"),
                                'title'  => __('status'),
                                'fields' => [
                                    'status' => [
                                        'type'  => 'toggle',
                                        'label' => __('status'),
                                        'value' => $customerClient->status
                                    ],
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

    public function asController(CustomerSalesChannel $customerSalesChannel, CustomerClient $customerClient, ActionRequest $request): Response {
        $this->initialisation($request);

        return $this->handle($customerClient, $request);
    }

    public function getBreadcrumbs(CustomerClient $customerClient, $routeParameters): array
    {
        return array_merge(
            ShowRetinaCustomerClient::make()->getBreadcrumbs(
                $customerClient,
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
