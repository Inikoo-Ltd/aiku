<?php
/*
 * author Arya Permana - Kirin
 * created on 16-05-2025-16h-28m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Client\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\OrgAction;
use App\Actions\RetinaAction;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerHasPlatform;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Platform;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Helpers\Address;
use App\Models\SysAdmin\Organisation;
use Cassandra\Type\Custom;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditRetinaCustomerClient extends RetinaAction
{
    public function handle(CustomerClient $customerClient, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $customerClient
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
        CustomerClient $customerClient,
        ActionRequest $request
    ): Response {
        $this->initialisation($request);

        return $this->handle($customerClient, $request);
    }

    public function getBreadcrumbs(CustomerClient $customerClient): array
    {
        return array_merge(
            ShowRetinaCustomerClient::make()->getBreadcrumbs(
                customerClient: $customerClient
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
