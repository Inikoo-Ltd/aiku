<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Feb 2024 23:54:37 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\SysAdmin;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Http\Resources\Helpers\TaxNumberResource;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaAccountManagement extends RetinaAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root;
    }


    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle($request);
    }


    public function handle(ActionRequest $request): Response
    {

        $customer = $request->user()->customer;

        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Account management'),
                'pageHead'    => [
                    'title' => __('Account management'),
                ],
                "formData" => [
                    "blueprint" =>
                    [
                        [
                            'title'  => __('contact information'),
                            'label'  => __('contact'),
                            'icon'    => 'fa-light fa-address-book',
                            'fields' => [
                                    'contact_name' => [
                                        'type'  => 'input',
                                        'label' => __('contact name'),
                                        'value' => $customer->contact_name
                                    ],
                                    'company_name' => [
                                        'type'  => 'input',
                                        'label' => __('company'),
                                        'value' => $customer->company_name
                                    ],
                                    'email' => [
                                        'type'  => 'input',
                                        'label' => __('email'),
                                        'value' => $customer->email
                                    ],
                                    'phone'        => [
                                        'type'  => 'phone',
                                        'label' => __('Phone'),
                                        'value' => $customer->phone
                                    ],
                                    'contact_address' => [
                                        'type'    => 'address',
                                        'label'   => __('Billing Address'),
                                        'value'   => AddressFormFieldsResource::make($customer->address)->getArray(),
                                        'options' => [
                                            'countriesAddressData' => GetAddressData::run()
                                        ]
                                    ],
//                                    'delivery_address' => [
//                                        'type'    => 'delivery_address',
//                                        'label'   => __('Delivery Address'),
//                                        'value'   => AddressFormFieldsResource::make($customer->deliveryAddress)->getArray(),
//                                        'options' => [
//                                            'use_billing_address' => $customer->address_id === $customer->delivery_address_id,
//                                            'countriesAddressData' => GetAddressData::run()
//                                        ]
//                                    ],
                                    'tax_number'      => [
                                        'type'    => 'tax_number',
                                        'label'   => __('Tax number'),
                                        'value'   => $customer->taxNumber ? TaxNumberResource::make($customer->taxNumber)->getArray() : null,
                                        'country' => $customer->address->country_code,
                                    ]
                                ]
                        ]
                    ],
                    "args"      => [
                        "updateRoute" => [
                            "name"       => "retina.models.customer.update",
                            'parameters' => [$customer->id]
                        ],
                    ],
                ],
            ]
        );
    }



    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.sysadmin.settings.edit'
                            ],
                            'label'  => __('Account management'),
                        ]
                    ]
                ]
            );
    }
}
