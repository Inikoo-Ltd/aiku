<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Apr 2024 19:02:34 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\UI;

use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Actions\Helpers\Currency\UI\GetCurrenciesOptions;
use App\Actions\OrgAction;
use App\Actions\Procurement\Marketplace\Agent\UI\RemoveMarketplaceAgent;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\SupplyChain\Agent;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditAgent extends OrgAction
{
    public function handle(Agent $agent): Agent
    {
        return $agent;
    }

    public function authorize(ActionRequest $request): bool
    {

        return $request->user()->authTo('supply-chain.edit');
    }

    public function asController(Agent $agent, ActionRequest $request): RedirectResponse|Agent
    {
        $this->initialisationFromGroup($agent->group, $request);

        return $this->handle($agent);
    }


    public function htmlResponse(Agent $agent, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Edit marketplace agent'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $agent,
                    $request->route()->originalParameters()
                ),
                'navigation'                              => [
                    'previous' => $this->getPrevious($agent, $request),
                    'next'     => $this->getNext($agent, $request),
                ],
                'pageHead'    => [
                    'title'     => $agent->code,
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],

                'formData' => [
                    'blueprint' => [


                        [
                            'title'  => __('ID/contact details'),
                            'icon'   => ['fal', 'fa-address-book'],
                            'fields' => [
                                'code'         => [
                                    'type'  => 'input',
                                    'label' => __('Code'),
                                    'value' => $agent->code
                                ],
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('Name'),
                                    'value' => $agent->organisation->name
                                ],
                                'contact_name' => [
                                    'type'  => 'input',
                                    'label' => __('Contact Name'),
                                    'value' => $agent->organisation->contact_name
                                ],
                                'email'        => [
                                    'type'    => 'input',
                                    'label'   => __('email'),
                                    'value'   => $agent->organisation->email,
                                    'options' => [
                                        'inputType' => 'email'
                                    ]
                                ],
                                'phone'        => [
                                    'type'    => 'phone',
                                    'label'   => __('phone'),
                                    'value'   => $agent->organisation->phone,
                                ],
                                'address'      => [
                                    'type'    => 'address',
                                    'label'   => __('Address'),
                                    'value'   => AddressResource::make($agent->organisation->address)->getArray(),
                                    'options' => [
                                        'countriesAddressData' => GetAddressData::run()

                                    ]
                                ],
                            ]
                        ],
                        [
                            'title'  => __('settings'),
                            'icon'   => 'fa-light fa-cog',
                            'fields' => [

                                'currency_id' => [
                                    'type'        => 'select',
                                    'label'       => __('currency'),
                                    'placeholder' => __('Select a currency'),
                                    'options'     => GetCurrenciesOptions::run(),
                                    'required'    => true,
                                    'mode'        => 'single'
                                ],

                                'default_product_country_origin' => [
                                    'type'        => 'select',
                                    'label'       => __("Asset's country of origin"),
                                    'placeholder' => __('Select a country'),
                                    'options'     => GetCountriesOptions::run(),
                                    'mode'        => 'single'
                                ],
                            ]
                        ],
                        // [
                        //     'title'     => __('Delete'),
                        //     'icon'      => 'fa-light fa-trash-alt',
                        //     'operation' => [
                        //         [
                        //             'component' => 'removeModelAction',
                        //             'data'      => RemoveMarketplaceAgent::make()->getAction(
                        //                 route:[
                        //                     'name'       => 'grp.models.marketplace-agent.delete',
                        //                     'parameters' => array_values($request->route()->originalParameters())
                        //                 ]
                        //             )
                        //         ],


                        //     ]
                        // ],

                    ],

                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.models.agent.update',
                            'parameters' => $agent->id

                        ],
                    ]
                ]
            ]
        );
    }


    public function getBreadcrumbs(Agent $agent, array $routeParameters): array
    {
        return ShowAgent::make()->getBreadcrumbs(
            $agent,
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }

    public function getPrevious(Agent $agent, ActionRequest $request): ?array
    {
        $previous = Agent::where('code', '<', $agent->code)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Agent $agent, ActionRequest $request): ?array
    {
        $next = Agent::where('code', '>', $agent->code)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Agent $agent, string $routeName): ?array
    {
        if (!$agent) {
            return null;
        }

        return match ($routeName) {
            'grp.supply-chain.agents.edit',
            'grp.org.procurement.marketplace.agents.edit' => [
                'label' => $agent->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'agent' => $agent->slug
                    ]

                ]
            ]
        };
    }
}
