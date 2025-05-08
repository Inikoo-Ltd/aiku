<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 May 2023 11:40:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Actions\Helpers\Currency\UI\GetCurrenciesOptions;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\OrgAction;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Exception;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditShop extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(['org-admin.'.$this->organisation->id, 'shop-admin.'.$this->shop->id]);
    }

    public function handle(Shop $shop): Shop
    {
        return $shop;
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request);
        return $this->handle($shop);
    }

    /**
     * @throws Exception
     */
    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {

        return Inertia::render(
            'EditModel',
            [
                'title'        => __('edit shop'),
                'breadcrumbs'  => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),

                'pageHead'    => [
                    'title'     => $shop->name,
                    'icon'      => [
                        'title' => __('Shop'),
                        'icon'  => 'fal fa-store-alt'
                    ],
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => 'grp.org.shops.show.dashboard.show',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],

                'formData' => [
                    'blueprint' => [
                        [
                            'label'  => __('Detail'),
                            'icon'   => 'fa-light fa-id-card',
                            'fields' => [
                                'code' => [
                                    'type'         => 'input',
                                    'label'        => __('code'),
                                    'value'        => $shop->code,
                                    'required'     => true,
                                ],
                                'name' => [
                                    'type'         => 'input',
                                    'label'        => __('name'),
                                    'value'        => $shop->name,
                                    'required'     => true,
                                ],
                                "image" => [
                                    "type"  => "avatar",
                                    "label" => __("Logo"),
                                    "value" => $shop->imageSources(320, 320)
                                ],
                            ]
                        ],
                        [
                            'label'  => __('properties'),
                            'icon'   => 'fa-light fa-fingerprint',
                            'fields' => [
                                'country_id'  => [
                                    'type'          => 'select',
                                    'label'         => __('country'),
                                    'placeholder'   => __('Select your country'),
                                    'value'         => $shop->country_id,
                                    'options'       => GetCountriesOptions::run(),
                                    'searchable'    => true
                                ],
                                'currency_id' => [
                                    'type'          => 'select',
                                    'label'         => __('currency'),
                                    'placeholder'   => __('Select your currency'),
                                    'required'      => true,
                                    'value'         => $shop->currency_id,
                                    'options'       => GetCurrenciesOptions::run(),
                                    'searchable'    => true
                                ],
                                'language_id' => [
                                    'type'          => 'select',
                                    'label'         => __('language'),
                                    'placeholder'   => __('Select your language'),
                                    'required'      => true,
                                    'value'         => $shop->language_id,
                                    'options'       => GetLanguagesOptions::make()->all(),
                                    'searchable'    => true
                                ]
                            ],

                        ],
                        [
                            'label'  => __('contact/details'),
                            'icon'   => 'fa-light fa-user',
                            'fields' => [
                                'contact_name' => [
                                    'type'  => 'input',
                                    'label' => __('contact name'),
                                    'value' => $shop->contact_name,
                                ],
                                'company_name' => [
                                    'type'  => 'input',
                                    'label' => __('company name'),
                                    'value' => $shop->company_name,
                                ],
                                'email'        => [
                                    'type'    => 'input',
                                    'label'   => __('email'),
                                    'value'   => $shop->email,
                                    'options' => [
                                        'inputType' => 'email'
                                    ]
                                ],
                                'phone'        => [
                                    'type'  => 'phone',
                                    'label' => __('telephone'),
                                    'value' => $shop->phone,
                                ],
                                'address' => [
                                    'type'    => 'address',
                                    'label'   => __('Address'),
                                    'value'   => AddressFormFieldsResource::make($shop->address)->getArray(),
                                    'options' => [
                                        'countriesAddressData' => GetAddressData::run()
                                    ]
                                ],
                                'registration_number'        => [
                                    'type'  => 'input',
                                    'label' => __('registration number'),
                                    'value' => $shop->data['registration_number'] ?? '',
                                ],
                                'vat_number'        => [
                                    'type'  => 'input',
                                    'label' => __('VAT number'),
                                    'value' => $shop->data['vat_number'] ?? '',
                                ],
                            ]
                        ],
                        [
                            'label'  => __('customer'),
                            'icon'   => 'fal fa-transporter',
                            'fields' => [
                                'required_approval' => [
                                    'type'  => 'toggle',
                                    'label' => __('Require approval'),
                                    'value' => Arr::get($shop->settings, 'customer.required_approval', false),
                                ],
                            ],

                        ],
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.org.shop.update',
                            'parameters' => [
                                'organisation' => $shop->organisation_id,
                                'shop'         => $shop->id
                            ]
                        ],
                    ]
                ],

            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            'grp.org.shops.show.settings.edit' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.settings.edit',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Settings')
                        ]
                    ]
                ]
            ),
            default => []
        };
    }



}
