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
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Http\Resources\Helpers\TaxNumberResource;
use Inertia\Inertia;
use Inertia\Response;
use App\Actions\Helpers\Country\UI\IsEuropeanUnion;
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
        $spain = \App\Models\Helpers\Country::where('code', 'ES')->first();
        $isEu = false;
        // To ensure VAT info only shows on EU shop
        if ($this->organisation->country) {
            $isEu = $this->organisation->country->continent == 'EU';
        }

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
                            'title'  => __('Contact Information'),
                            'label'  => __('Contact'),
                            'icon'    => 'fa-light fa-address-book',
                            'fields' => [
                                    'contact_name' => [
                                        'type'  => 'input',
                                        'label' => __('Contact Name'),
                                        'value' => $customer->contact_name
                                    ],
                                    'company_name' => [
                                        'type'  => 'input',
                                        'label' => __('Company'),
                                        'value' => $customer->company_name
                                    ],
                                    'email' => [
                                        'type'  => 'input',
                                        'label' => __('Email'),
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
                                    'delivery_address'         => [
                                        'hidden' => $customer->shop->type == ShopTypeEnum::DROPSHIPPING,
                                        'type'    => 'delivery_address',
                                        'label'   => __('Delivery Address'),
                                        'noSaveButton'  => true,
                                        'options' => [
                                            'same_as_contact' => [
                                                'label'         => __('Same as contact address'),
                                                'key_payload'   => 'delivery_address_id',
                                                'payload'       => $customer->address_id
                                            ],
                                            'countriesAddressData'    => GetAddressData::run()
                                        ],
                                        'value'   => [
                                            'is_same_as_contact'    => $customer->delivery_address_id == $customer->address_id,
                                            'address'               => AddressFormFieldsResource::make($customer->deliveryAddress)->getArray()
                                        ],
                                    ],
                                    'tax_number'      => [
                                        'type'    => 'tax_number',
                                        'label'   => __('Tax number'),
                                        'value'   => $customer->taxNumber ? TaxNumberResource::make($customer->taxNumber)->getArray() : null,
                                        'country' => $customer->address->country_code,
                                        'europeanUnion' => $isEu ? implode(', ', IsEuropeanUnion::getEUCountryCodes()) : '',
                                    ],
                                    'is_re'           => [
                                        'type'   => 'toggle',
                                        'hidden' => $this->organisation->country_id != $spain->id || $customer->address->country_id != $spain->id,
                                        'label'  => 'Recargo de equivalencia',
                                        'value'  => $customer->is_re,

                                    ]
                                ]
                        ],
                        [
                            'title'  => __('Interest'),
                            'label'  => __('Interest'),
                            'icon'    => 'fal fa-tags',
                            'fields' => [
                                'tags' => [
                                    'type'  => 'retina-tags-customer',
                                    'label' => __('Interest'),
                                    'value' => $customer
                                        ->tags()
                                        ->where('tags.scope', TagScopeEnum::USER_CUSTOMER->value)
                                        ->pluck('tags.id')
                                        ->toArray(),
                                    'isWithRefreshFieldForm' => true,
                                    'tag_routes' => [
                                        'index_tag' => [
                                            'name'       => 'retina.json.customer.tags.index',
                                            'parameters' => [
                                                'customer' => $customer->id,
                                            ]
                                        ],
                                        'attach_tag' => [
                                            'name'       => 'retina.models.customer.tags.attach',
                                            'parameters' => [
                                                'customer' => $customer->id,
                                            ],
                                            'method'    => 'post'
                                        ],
                                        'detach_tag' => [
                                            'name'       => 'retina.models.customer.tags.detach',
                                            'parameters' => [
                                                'customer' => $customer->id,
                                            ],
                                            'method'    => 'delete'
                                        ],
                                    ],
                                ],
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
