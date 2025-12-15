<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jul 2024 16:42:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Fulfilment\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Actions\Helpers\Currency\UI\GetCurrenciesOptions;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\OrgAction;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditFulfilment extends OrgAction
{
    public function handle(Fulfilment $fulfilment): Fulfilment
    {
        return $fulfilment;
    }

    public function asController(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): Fulfilment
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment);
    }


    public function htmlResponse(Fulfilment $fulfilment, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('fulfilment setting'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'model' => $fulfilment->shop->name,
                    'title' => __('Settings'),
                    'icon'  => 'fal fa-sliders-h'
                ],

                'formData' => [
                    'blueprint' => [
                        [
                            'label'  => __('Detail'),
                            'icon'   => 'fa-light fa-id-card',
                            'fields' => [
                                'code'  => [
                                    'type'     => 'input',
                                    'label'    => __('Code'),
                                    'value'    => $fulfilment->shop->code,
                                    'required' => true,
                                ],
                                'name'  => [
                                    'type'     => 'input',
                                    'label'    => __('Name'),
                                    'value'    => $fulfilment->shop->name,
                                    'required' => true,
                                ],
                                "image" => [
                                    "type"  => "image_crop_square",
                                    "label" => __("Logo"),
                                    "value" => $fulfilment->shop->imageSources(320, 320)
                                ],
                                // "image_id" => [
                                //     "type"  => "image_crop_square",
                                //     "label" => __("favicon"),
                                //     "value" => $fulfilment->shop->imageSources(32, 32)
                                // ],
                            ]
                        ],
                        [
                            'label'  => __('properties'),
                            'icon'   => 'fa-light fa-fingerprint',
                            'fields' => [
                                'country_id'  => [
                                    'type'        => 'select',
                                    'label'       => __('country'),
                                    'placeholder' => __('Select your country'),
                                    'value'       => $fulfilment->shop->country_id,
                                    'options'     => GetCountriesOptions::run(),
                                    'searchable'  => true
                                ],
                                'currency_id' => [
                                    'type'        => 'select',
                                    'label'       => __('currency'),
                                    'placeholder' => __('Select your currency'),
                                    'required'    => true,
                                    'value'       => $fulfilment->shop->currency_id,
                                    'options'     => GetCurrenciesOptions::run(),
                                    'searchable'  => true
                                ],
                                'language_id' => [
                                    'type'        => 'select',
                                    'label'       => __('language'),
                                    'placeholder' => __('Select your language'),
                                    'required'    => true,
                                    'value'       => $fulfilment->shop->language_id,
                                    'options'     => GetLanguagesOptions::make()->all(),
                                    'searchable'  => true
                                ]
                            ],

                        ],
                        [
                            'label'  => __('contact/details'),
                            'icon'   => 'fa-light fa-user',
                            'fields' => [
                                'contact_name'        => [
                                    'type'  => 'input',
                                    'label' => __('contact name'),
                                    'value' => $fulfilment->shop->contact_name,
                                ],
                                'company_name'        => [
                                    'type'  => 'input',
                                    'label' => __('company name'),
                                    'value' => $fulfilment->shop->company_name,
                                ],
                                'email'               => [
                                    'type'    => 'input',
                                    'label'   => __('email'),
                                    'value'   => $fulfilment->shop->email,
                                    'options' => [
                                        'inputType' => 'email'
                                    ]
                                ],
                                'phone'               => [
                                    'type'  => 'phone',
                                    'label' => __('telephone'),
                                    'value' => $fulfilment->shop->phone,
                                ],
                                'address'             => [
                                    'type'    => 'address',
                                    'label'   => __('Address'),
                                    'value'   => AddressFormFieldsResource::make($fulfilment->shop->address)->getArray(),
                                    'options' => [
                                        'countriesAddressData' => GetAddressData::run()
                                    ]
                                ],
                                'registration_number' => [
                                    'type'  => 'input',
                                    'label' => __('registration number'),
                                    'value' => $fulfilment->shop->data['registration_number'] ?? '',
                                ],
                                'vat_number'          => [
                                    'type'  => 'input',
                                    'label' => __('VAT number'),
                                    'value' => $fulfilment->shop->data['vat_number'] ?? '',
                                ],
                            ]
                        ],
                        [
                            'label'  => __('invoices footer'),
                            'icon'   => 'fa-light fa-shoe-prints',
                            'fields' => [
                                'invoice_footer'  => [
                                    'type'        => 'textEditor',
                                    'label'       => __('invoice footer'),
                                    'full'      => true,
                                    'value'       => $fulfilment->shop->invoice_footer
                                ],
                            ],
                        ],
                        [
                            'label'  => __('sender email'),
                            'icon'   => 'fa-light fa-envelope',
                            'fields' => [
                                'sender_email' => [
                                    'type'  => 'input',
                                    'label' => __('email'),
                                    'verification' => [
                                        'route' => [
                                            'name' => 'grp.models.shop.sender_email.verify',
                                            'parameters' => [
                                                'shop' => $fulfilment->shop_id
                                            ]
                                        ],
                                        'state' => $fulfilment->shop?->senderEmail?->state
                                    ],
                                    'value' => $fulfilment->shop?->senderEmail?->email_address,
                                ],
                            ],
                        ],
                        [
                            'title'  => __('recurring bill settings'),
                            'icon'   => 'fa-light fa-flag-checkered',
                            'label'  => __('cut off day'),
                            'fields' => [
                                'monthly_cut_off'    => [
                                    'type'    => 'date_radio',
                                    'label'   => __('monthly cut off day'),
                                    'options' => [
                                        1,
                                        2,
                                        3,
                                        4,
                                        5,
                                        6,
                                        7,
                                        8,
                                        9,
                                        10,
                                        11,
                                        12,
                                        13,
                                        14,
                                        15,
                                        16,
                                        17,
                                        18,
                                        19,
                                        20,
                                        21,
                                        22,
                                        23,
                                        24,
                                        25,
                                        26,
                                        27,
                                        28
                                    ],
                                    'noSaveButton'  => true,
                                    'value'   => [
                                        'date'       => Arr::get($fulfilment->settings, 'rental_agreement_cut_off.monthly.day'),
                                        'isWeekdays' => Arr::get($fulfilment->settings, 'rental_agreement_cut_off.monthly.is_weekdays'),
                                    ]
                                ],
                                'weekly_cut_off_day' => [
                                    'type'      => 'radio',
                                    'mode'      => 'compact',
                                    'options'   => [
                                        [
                                            'label' => __('Monday'),
                                            'value' => 'Monday'
                                        ],
                                        [
                                            'label' => __('Tuesday'),
                                            'value' => 'Tuesday'
                                        ],
                                        [
                                            'label' => __('Wednesday'),
                                            'value' => 'Wednesday'
                                        ],
                                        [
                                            'label' => __('Thursday'),
                                            'value' => 'Thursday'
                                        ],
                                        [
                                            'label' => __('Friday'),
                                            'value' => 'Friday'
                                        ],
                                        [
                                            'label' => __('Saturday'),
                                            'value' => 'Saturday'
                                        ],
                                        [
                                            'label' => __('Sunday'),
                                            'value' => 'Sunday'
                                        ],

                                    ],
                                    'valueProp' => 'value',
                                    'label'     => __('weekly cut off day'),
                                    'value'     => $fulfilment->settings['rental_agreement_cut_off']['weekly']['day']
                                ]
                            ]
                        ]

                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.fulfilment.update',
                            'parameters' => [$fulfilment->id]
                        ],
                    ]

                ],

            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            'grp.org.fulfilments.show.settings.edit' =>
            array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.settings.edit',
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
