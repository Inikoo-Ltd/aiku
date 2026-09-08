<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 18:20:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Actions\Helpers\Currency\UI\GetCurrenciesOptions;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Models\Helpers\Address;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateAgent extends OrgAction
{
    use WithSupplyChainEditAuthorisation;

    public function asController(ActionRequest $request): ActionRequest
    {
        $this->initialisationFromGroup(group(), $request);

        return $request;
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        $routeParameters = array_values($request->route()->originalParameters());

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs($routeParameters),
                'title'       => __('New agent'),
                'pageHead'    => [
                    'title'   => __('New agent'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.supply-chain.agents.index',
                                'parameters' => $routeParameters,
                            ],
                        ],
                    ],
                ],
                'formData'    => [
                    'blueprint' => $this->getBlueprint(),
                    'route'     => [
                        'name' => 'grp.models.agent.store',
                    ],
                ],
            ]
        );
    }

    protected function getBlueprint(): array
    {
        return [
            [
                'title'  => __('ID/Contact Details'),
                'icon'   => 'fal fa-address-book',
                'fields' => [
                    'code'            => [
                        'type'     => 'input',
                        'label'    => __('Code'),
                        'value'    => '',
                        'required' => true,
                    ],
                    'name'            => [
                        'type'     => 'input',
                        'label'    => __('Name'),
                        'value'    => '',
                        'required' => false,
                    ],
                    'contact_name'    => [
                        'type'     => 'input',
                        'label'    => __('Contact Name'),
                        'value'    => '',
                        'required' => true,
                    ],
                    'contact_website' => [
                        'type'     => 'input',
                        'label'    => __('Contact Website'),
                        'value'    => '',
                        'required' => false,
                    ],
                    'company_name'    => [
                        'type'     => 'input',
                        'label'    => __('Company Name'),
                        'value'    => '',
                        'required' => false,
                    ],
                    'email'           => [
                        'type'    => 'input',
                        'label'   => __('Email'),
                        'value'   => '',
                        'options' => [
                            'inputType' => 'email',
                        ],
                    ],
                    'phone'           => [
                        'type'  => 'phone',
                        'label' => __('Phone'),
                        'value' => '',
                    ],
                    'address'         => [
                        'type'    => 'address',
                        'label'   => __('Address'),
                        'value'   => AddressFormFieldsResource::make(new Address(['country_id' => group()->country_id]))->getArray(),
                        'options' => [
                            'countriesAddressData' => GetAddressData::run(),
                        ],
                    ],
                ],
            ],
            [
                'title'  => __('Settings'),
                'icon'   => 'fa-light fa-cog',
                'fields' => [
                    'currency_id'                    => [
                        'type'        => 'select',
                        'label'       => __('Currency'),
                        'placeholder' => __('Select a currency'),
                        'options'     => GetCurrenciesOptions::run(),
                        'required'    => true,
                        'mode'        => 'single',
                        'searchable'  => true,
                    ],
                    'default_product_country_origin' => [
                        'type'        => 'select',
                        'label'       => __("Product's country of origin"),
                        'placeholder' => __('Select a country'),
                        'options'     => GetCountriesOptions::run(),
                        'mode'        => 'single',
                        'searchable'  => true,
                    ],
                ],
            ],
        ];
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexAgents::make()->getBreadcrumbs('grp.supply-chain.agents.index', $routeParameters),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating Agent'),
                    ],
                ],
            ]
        );
    }
}
