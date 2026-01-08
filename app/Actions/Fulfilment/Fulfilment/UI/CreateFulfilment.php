<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jan 2024 14:52:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Fulfilment\UI;

use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Actions\Helpers\Currency\UI\GetCurrenciesOptions;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\Helpers\TimeZone\UI\GetTimeZonesOptions;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class CreateFulfilment extends OrgAction
{
    public function handle()
    {
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('org-supervisor.'.$this->organisation->id);
    }

    /**
     * @throws \Exception
     */
    public function htmlResponse(ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('New fulfilment shop'),
                'pageHead'    => [
                    'title'   => __('New fulfilment shop'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.org.fulfilments.index',
                                'parameters' => $request->route()->originalParameters()
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'title'  => __('detail'),
                            'fields' => [

                                'code' => [
                                    'type'     => 'input',
                                    'label'    => __('Code'),
                                    'required' => true,
                                ],
                                'name' => [
                                    'type'     => 'input',
                                    'label'    => __('Name'),
                                    'required' => true,
                                    'value'    => '',
                                ],
                                'type' => [
                                    'type'        => 'select',
                                    'label'       => __('Type'),
                                    'placeholder' => __('Select one option'),
                                    'options'     => Options::forEnum(ShopTypeEnum::class),
                                    'value'       => ShopTypeEnum::FULFILMENT,
                                    'required'    => true,
                                    'mode'        => 'single',
                                    'searchable'  => true,
                                    'readonly'    => true
                                ],
                            ]
                        ],

                        [
                            'title'  => __('localization'),
                            'icon'   => 'fa-light fa-phone',
                            'fields' => [
                                'country_id'  => [
                                    'type'        => 'select',
                                    'label'       => __('Country'),
                                    'placeholder' => __('Select a country'),
                                    'options'     => GetCountriesOptions::run(),
                                    'value'       => $this->organisation->country_id,
                                    'required'    => true,
                                    'mode'        => 'single'
                                ],
                                'language_id' => [
                                    'type'        => 'select',
                                    'label'       => __('Language'),
                                    'placeholder' => __('Select a language'),
                                    'options'     => GetLanguagesOptions::make()->all(),
                                    'value'       => $this->organisation->language_id,
                                    'required'    => true,
                                    'mode'        => 'single'
                                ],
                                'currency_id' => [
                                    'type'        => 'select',
                                    'label'       => __('Currency'),
                                    'placeholder' => __('Select a currency'),
                                    'options'     => GetCurrenciesOptions::run(),
                                    'value'       => $this->organisation->currency_id,
                                    'required'    => true,
                                    'mode'        => 'single'
                                ],
                                'timezone_id' => [
                                    'type'        => 'select',
                                    'label'       => __('timezone'),
                                    'placeholder' => __('Select a timezone'),
                                    'options'     => GetTimeZonesOptions::run(),
                                    'value'       => $this->organisation->timezone_id,
                                    'required'    => true,
                                    'mode'        => 'single'
                                ],

                            ]
                        ],
                        [
                            'title'  => __('Contact/details'),
                            'fields' => [
                                'contact_name' => [
                                    'type'  => 'input',
                                    'label' => __('Contact name'),
                                    'value' => '',
                                ],
                                'company_name' => [
                                    'type'  => 'input',
                                    'label' => __('Company name'),
                                    'value' => '',
                                ],
                                'email'        => [
                                    'type'    => 'input',
                                    'label'   => __('Email'),
                                    'value'   => '',
                                    'options' => [
                                        'inputType' => 'email'
                                    ]
                                ],
                                'phone'        => [
                                    'type'  => 'phone',
                                    'label' => __('Telephone'),
                                    'value' => ''
                                ],
                            ]
                        ],
                    ],
                    'route'     => [
                        'name'       => 'grp.models.org.fulfilment.store',
                        'parameters' => [
                            'organisation' => $this->organisation->id
                        ]
                    ]
                ],

            ]
        );
    }



    public function asController(Organisation $organisation, ActionRequest $request): ActionRequest
    {
        $this->initialisation($organisation, $request);

        return $request;
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexFulfilments::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __("creating fulfilment shop"),
                    ]
                ]
            ]
        );
    }
}
