<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 18 May 2023 14:27:30 Central European Summer, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Actions\Helpers\Currency\UI\GetCurrenciesOptions;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\Helpers\TimeZone\UI\GetTimeZonesOptions;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateExternalShop extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('org-admin.'.$this->organisation->id);
    }

    /**
     * @throws \Exception
     */
    public function htmlResponse(ActionRequest $request): Response
    {
        $engine = $request->route()->parameter('engine');

        $externalShopBlueprint = match ($engine) {
            ShopEngineEnum::FAIRE->value => [
                [
                    'title'  => __('detail'),
                    'fields' => [
                        'code' => [
                            'type'     => 'input',
                            'label'    => __('Code'),
                            'required' => true,
                            'value'    => '',
                        ],
                        'access_token' => [
                            'type'     => 'input',
                            'label'    => __('Access Token'),
                            'required' => true,
                            'value'    => '',
                        ]
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
                            'mode'        => 'single',
                            'searchable'  => true
                        ],
                        'language_id' => [
                            'type'        => 'select',
                            'label'       => __('Language'),
                            'placeholder' => __('Select a language'),
                            'options'     => GetLanguagesOptions::make()->all(),
                            'value'       => $this->organisation->language_id,
                            'required'    => true,
                            'mode'        => 'single',
                            'searchable'  => true
                        ],
                        'currency_id' => [
                            'type'        => 'select',
                            'label'       => __('Currency'),
                            'placeholder' => __('Select a currency'),
                            'options'     => GetCurrenciesOptions::run(),
                            'value'       => $this->organisation->currency_id,
                            'required'    => true,
                            'mode'        => 'single',
                            'searchable'  => true
                        ],
                        'timezone_id' => [
                            'type'        => 'select',
                            'label'       => __('timezone'),
                            'placeholder' => __('Select a timezone'),
                            'options'     => GetTimeZonesOptions::run(),
                            'value'       => $this->organisation->timezone_id,
                            'required'    => true,
                            'mode'        => 'single',
                            'searchable'  => true
                        ],

                    ]
                ]
            ],
            ShopEngineEnum::SHOPIFY->value => [
                [
                    'title'  => __('detail'),
                    'fields' => [
                        'code' => [
                            'type'     => 'input',
                            'label'    => __('Code'),
                            'required' => true,
                            'value'    => '',
                        ],
                        'shop_url' => [
                            'type'     => 'input',
                            'label'    => __('Shop Url'),
                            'required' => true,
                            'value'    => '',
                        ]
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
                            'mode'        => 'single',
                            'searchable'  => true
                        ],
                        'language_id' => [
                            'type'        => 'select',
                            'label'       => __('Language'),
                            'placeholder' => __('Select a language'),
                            'options'     => GetLanguagesOptions::make()->all(),
                            'value'       => $this->organisation->language_id,
                            'required'    => true,
                            'mode'        => 'single',
                            'searchable'  => true
                        ],
                        'currency_id' => [
                            'type'        => 'select',
                            'label'       => __('Currency'),
                            'placeholder' => __('Select a currency'),
                            'options'     => GetCurrenciesOptions::run(),
                            'value'       => $this->organisation->currency_id,
                            'required'    => true,
                            'mode'        => 'single',
                            'searchable'  => true
                        ],
                        'timezone_id' => [
                            'type'        => 'select',
                            'label'       => __('timezone'),
                            'placeholder' => __('Select a timezone'),
                            'options'     => GetTimeZonesOptions::run(),
                            'value'       => $this->organisation->timezone_id,
                            'required'    => true,
                            'mode'        => 'single',
                            'searchable'  => true
                        ],

                    ]
                ]
            ],
            default => []
        };

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('New shop'),
                'pageHead'    => [
                    'title'   => __('New shop'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.org.shops.index',
                                'parameters' => $request->route()->originalParameters()
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => $externalShopBlueprint,
                    'route'     => [
                        'name' => 'grp.models.org.shop.external.store',
                        'parameters' => [
                            'organisation' => $this->organisation->id,
                            'engine'       => $engine
                        ]
                    ]
                ],
            ]
        );
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->set('engine', $request->route()->parameter('engine'));
    }

    public function rules(): array
    {
        return [
            'engine' => ['required', Rule::in(ShopEngineEnum::values())]
        ];
    }

    public function asController(Organisation $organisation, string $engine, ActionRequest $request): ActionRequest
    {
        $this->initialisation($organisation, $request);

        return $request;
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexShops::make()->getBreadcrumbs('grp.org.shops.index', $routeParameters),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __("creating shop"),
                    ]
                ]
            ]
        );
    }
}
