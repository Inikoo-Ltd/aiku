<?php

/*
 * author Louis Perez
 * created on 22-12-2025-10h-14m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Actions\Helpers\Currency\UI\GetCurrenciesOptions;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\Helpers\Organisation\UI\GetOrganisationOptions;
use App\Actions\Helpers\TimeZone\UI\GetTimeZonesOptions;
use App\Actions\GrpAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Masters\MasterShop;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;

class CreateShopInGroup extends GrpAction
{
    // TODO master authorisation to create shop (?) 
    private MasterShop $masterShop;

    public function asController(MasterShop $masterShop, ActionRequest $request): ActionRequest
    {
        $group        = group();
        $this->masterShop = $masterShop;
        $this->initialisation($group, $request);

        return $request;
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
                'title'       => __('New shop'),
                'pageHead'    => [
                    'title'   => __('New shop'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.masters.master_shops.show',
                                'parameters' => [
                                    'tab' => 'shops',
                                    ...$request->route()->originalParameters()
                                ]
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'title'  => __('Detail'),
                            'icon'   => 'fal fa-file-signature',
                            'fields' => [
                                'organisation' => [
                                    'type'        => 'select',
                                    'label'       => __('Organisation'),
                                    'placeholder' => __('Select one option'),
                                    'options'     => GetOrganisationOptions::run(),
                                    'required'    => true,
                                    'mode'        => 'single',
                                    'searchable'  => true
                                ],
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
                                    'required'    => true,
                                    'mode'        => 'single',
                                    'searchable'  => true
                                ],  
                            ]
                        ],
                        [
                            'title'  => __('Localization'),
                            'icon'   => 'fa-light fa-globe',
                            'fields' => [
                                'country_id'  => [
                                    'type'        => 'select',
                                    'label'       => __('Country'),
                                    'placeholder' => __('Select a country'),
                                    'options'     => GetCountriesOptions::run(),
                                    'required'    => true,
                                    'mode'        => 'single'
                                ],
                                'language_id' => [
                                    'type'        => 'select',
                                    'label'       => __('Language'),
                                    'placeholder' => __('Select a language'),
                                    'options'     => GetLanguagesOptions::make()->all(),
                                    'required'    => true,
                                    'mode'        => 'single'
                                ],
                                'currency_id' => [
                                    'type'        => 'select',
                                    'label'       => __('Currency'),
                                    'placeholder' => __('Select a currency'),
                                    'options'     => GetCurrenciesOptions::run(),
                                    'required'    => true,
                                    'mode'        => 'single'
                                ],
                                'timezone_id' => [
                                    'type'        => 'select',
                                    'label'       => __('Timezone'),
                                    'placeholder' => __('Select a timezone'),
                                    'options'     => GetTimeZonesOptions::run(),
                                    'required'    => true,
                                    'mode'        => 'single'
                                ],

                            ]
                        ],
                        [
                            'title'  => __('Contact/Details'),
                            'icon'   => 'fa-light fa-address-book',
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
                        'name' => 'grp.masters.master_shops.show.shop.store',
                        'parameters' => [
                            'masterShop'    => $this->masterShop->slug
                        ]
                    ]
                ],

            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowMasterShop::make()->getBreadcrumbs($this->masterShop, 'grp.masters.master_shops.show'),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __("Creating shop"),
                    ]
                ]
            ]
        );
    }
}
