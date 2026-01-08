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
use App\Actions\GrpAction;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;

class CreateShopFromMaster extends GrpAction
{
    // TODO master authorisation to create shop (?)
    private MasterShop $masterShop;

    public function asController(MasterShop $masterShop, Organisation $organisation, ActionRequest $request): Organisation
    {
        $group            = group();
        $this->masterShop = $masterShop;
        $this->initialisation($group, $request);

        return $organisation;
    }

    /**
     * @throws \Exception
     */
    public function htmlResponse(Organisation $organisation, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateShop',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('New shop in master').' '.$this->masterShop->code,
                'pageHead'    => [
                    'title'   => __('New shop in').' '.$organisation->code.' '.__('from').' '.$this->masterShop->name,
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
                                'code'         => [
                                    'type'     => 'input',
                                    'label'    => __('Code'),
                                    'required' => true,
                                ],
                                'name'         => [
                                    'type'     => 'input',
                                    'label'    => __('Name'),
                                    'required' => true,
                                    'value'    => '',
                                ],
                                'domain'       => [
                                    'type'        => 'input',
                                    'label'       => __('Domain'),
                                    'required'    => true,
                                    'value'       => '',
                                    'placeholder' => 'my-shop.com'
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
                                    'searchable'  => true,
                                    'mode'        => 'single'
                                ],
                                'language_id' => [
                                    'type'        => 'select',
                                    'label'       => __('Language'),
                                    'placeholder' => __('Select a language'),
                                    'options'     => GetLanguagesOptions::make()->all(),
                                    'required'    => true,
                                    'searchable'  => true,
                                    'mode'        => 'single'
                                ],
                                'currency_id' => [
                                    'type'        => 'select',
                                    'label'       => __('Currency'),
                                    'placeholder' => __('Select a currency'),
                                    'options'     => GetCurrenciesOptions::run(),
                                    'required'    => true,
                                    'searchable'  => true,
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
                        'name'       => 'grp.masters.master_shops.show.shop.store',
                        'parameters' => [
                            'masterShop'   => $this->masterShop->slug,
                            'organisation' => $organisation
                        ]
                    ]
                ],

            ]
        );
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowMasterShop::make()->getBreadcrumbs($this->masterShop),
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
