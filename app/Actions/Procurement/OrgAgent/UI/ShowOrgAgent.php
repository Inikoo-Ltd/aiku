<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 13:52:57 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Procurement\OrgAgent\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\WithOrgAgentSubNavigation;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Enums\UI\Procurement\OrgAgentTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Procurement\OrgAgentResource;
use App\Models\Procurement\OrgAgent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrgAgent extends OrgAction
{
    use WithOrgAgentSubNavigation;
    public function handle(OrgAgent $orgAgent): OrgAgent
    {
        return $orgAgent;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit   = $request->user()->authTo("procurement.{$this->organisation->id}.edit");
        $this->canDelete = $request->user()->authTo("procurement.{$this->organisation->id}.edit");

        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    public function asController(Organisation $organisation, OrgAgent $orgAgent, ActionRequest $request): RedirectResponse|OrgAgent
    {
        $this->initialisation($organisation, $request)->withTab(OrgAgentTabsEnum::values());

        return $this->handle($orgAgent);
    }

    public function maya(Organisation $organisation, OrgAgent $orgAgent, ActionRequest $request): OrgAgent
    {
        $this->maya   = true;
        $this->initialisation($organisation, $request)->withTab(OrgAgentTabsEnum::values());
        return $this->handle($orgAgent);
    }

    public function htmlResponse(OrgAgent $orgAgent, ActionRequest $request): Response
    {
        return Inertia::render(
            'Procurement/OrgAgent',
            [
                'title'       => __('Agent'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($orgAgent, $request),
                    'next'     => $this->getNext($orgAgent, $request),
                ],
                'pageHead'    => [
                    'model'         => __('Agent'),
                    'icon'          =>
                        [
                            'icon'  => ['fal', 'people-arrows'],
                            'title' => __('Agent')
                        ],
                    'subNavigation' => $this->getOrgAgentNavigation($orgAgent),
                    'title'         => $orgAgent->agent->organisation->name,
                    'actions'       => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('Edit'),
                            'route' => [
                                'name'       => preg_replace('/show$/', 'show.edit', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false,
                    ],
                    'create_direct' => $this->canEdit ? [
                        'route' => [
                            'name'       => 'grp.models.org_agent.purchase-order.store',
                            'parameters' => array_values($request->route()->originalParameters())
                        ],
                        'label' => __('Purchase Order')
                    ] : false,

                    // 'meta' => [
                    //     [
                    //         'name'     => trans_choice('supplier|suppliers', $orgAgent->stats->number_org_suppliers),
                    //         'number'   => $orgAgent->stats->number_org_suppliers,
                    //         'route'     => [
                    //             'grp.org.procurement.org_agents.show.org_suppliers.index',
                    //             $orgAgent->organisation->slug
                    //         ],
                    //         'leftIcon' => [
                    //             'icon'    => 'fal fa-person-dolly',
                    //             'tooltip' => __('suppliers')
                    //         ]
                    //     ],
                    //     [
                    //         'name'     => trans_choice('product|products', $orgAgent->stats->number_org_supplier_products),
                    //         'number'   => $orgAgent->stats->number_org_supplier_products,
                    //         'route'     => [
                    //             'grp.org.procurement.org_agents.show.org_suppliers.index',
                    //             $orgAgent->organisation->slug
                    //         ],
                    //         'leftIcon' => [
                    //             'icon'    => 'fal fa-box-usd',
                    //             'tooltip' => __('products')
                    //         ]
                    //     ]
                    // ]

                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => OrgAgentTabsEnum::navigation()
                ],

                OrgAgentTabsEnum::SHOWCASE->value => $this->tab == OrgAgentTabsEnum::SHOWCASE->value ?
                    fn () => GetOrgAgentShowcase::run($orgAgent)
                    : Inertia::lazy(fn () => GetOrgAgentShowcase::run($orgAgent)),

                OrgAgentTabsEnum::HISTORY->value => $this->tab == OrgAgentTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($orgAgent))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($orgAgent)))
            ]
        )->table(IndexHistory::make()->tableStructure(prefix: OrgAgentTabsEnum::HISTORY->value));
    }


    public function jsonResponse(OrgAgent $orgAgent): OrgAgentResource
    {
        return new OrgAgentResource($orgAgent);
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        $orgAgent = OrgAgent::where('slug', $routeParameters['orgAgent'])->first();

        return array_merge(
            (new ShowProcurementDashboard())->getBreadcrumbs($routeParameters),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.procurement.org_agents.index',
                                'parameters' => [
                                    $routeParameters['organisation'],
                                ]
                            ],
                            'label' => __('Agents')
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.procurement.org_agents.show',
                                'parameters' => [
                                    $routeParameters['organisation'],
                                    $orgAgent->slug
                                ]
                            ],
                            'label' => $orgAgent->agent->code,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],
            ]
        );
    }

    public function getPrevious(OrgAgent $orgAgent, ActionRequest $request): ?array
    {
        $previous = OrgAgent::where('slug', '<', $orgAgent->slug)->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(OrgAgent $orgAgent, ActionRequest $request): ?array
    {
        $next = OrgAgent::where('slug', '>', $orgAgent->slug)->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?OrgAgent $orgAgent, string $routeName): ?array
    {
        if (!$orgAgent) {
            return null;
        }

        return match ($routeName) {
            'grp.org.procurement.org_agents.show' => [
                'label' => $orgAgent->organisation->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $orgAgent->organisation->slug,
                        'orgAgent'     => $orgAgent->slug
                    ]

                ]
            ]
        };
    }

}
