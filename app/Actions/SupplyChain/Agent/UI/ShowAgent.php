<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Apr 2024 20:10:25 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\UI;

use App\Actions\GrpAction;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\SupplyChain\Agent\WithAgentSubNavigation;
use App\Actions\SupplyChain\Supplier\UI\IndexSuppliers;
use App\Actions\SupplyChain\SupplierProduct\UI\IndexSupplierProducts;
use App\Actions\SupplyChain\UI\ShowSupplyChainDashboard;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Enums\UI\SupplyChain\AgentTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\SupplyChain\AgentsResource;
use App\Http\Resources\SupplyChain\SupplierProductsResource;
use App\Http\Resources\SupplyChain\SuppliersResource;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowAgent extends GrpAction
{
    use WithAgentSubNavigation;
    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo('supply-chain.edit');

        return $request->user()->authTo('supply-chain.view');
    }

    public function handle(Agent $agent): Agent
    {
        return $agent;
    }


    public function asController(Agent $agent, ActionRequest $request): Agent
    {
        $this->initialisation(app('group'), $request)->withTab(AgentTabsEnum::values());

        return $this->handle($agent);
    }


    public function htmlResponse(Agent $agent, ActionRequest $request): Response
    {

        return Inertia::render(
            'SupplyChain/Agent',
            [
                'title'                        => __("agent"),
                'breadcrumbs'                  => $this->getBreadcrumbs(
                    $agent,
                    $request->route()->originalParameters()
                ),
                'navigation'                   => [
                    'previous' => $this->getPrevious($agent, $request),
                    'next'     => $this->getNext($agent, $request),
                ],
                'pageHead'                     => [
                    'model'   => __('Agent'),
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'people-arrows'],
                            'title' => __('Agent'),
                            'label' => __('Agent')
                        ],
                    'subNavigation' => $this->getAgentNavigation($agent),
                    'title'   => $agent->organisation->name,
                    'actions' => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false,
                        $this->canDelete ? [
                            'type'  => 'button',
                            'style' => 'delete',
                            'route' => [
                                'name'       => 'grp.org.procurement.marketplace.agents.remove',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false,
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'create',
                            'route' => [
                                'name'       => 'grp.org.procurement.marketplace.agents.show.suppliers.create',
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                            'label' => __('Supplier')
                        ] : false,
                    ],


                ],
                'tabs'                         => [
                    'current'    => $this->tab,
                    'navigation' => AgentTabsEnum::navigation()
                ],
                AgentTabsEnum::SHOWCASE->value => $this->tab == AgentTabsEnum::SHOWCASE->value ?
                    fn () => GetAgentShowcase::run($agent)
                    : Inertia::lazy(fn () => GetAgentShowcase::run($agent)),

                // AgentTabsEnum::SUPPLIERS->value => $this->tab == AgentTabsEnum::SUPPLIERS->value
                //     ?
                //     fn () => SuppliersResource::collection(
                //         IndexSuppliers::run(
                //             parent: $agent,
                //             prefix: 'suppliers'
                //         )
                //     )
                //     : Inertia::lazy(fn () => SuppliersResource::collection(
                //         IndexSuppliers::run(
                //             parent: $agent,
                //             prefix: 'suppliers'
                //         )
                //     )),

                // AgentTabsEnum::SUPPLIER_PRODUCTS->value => $this->tab == AgentTabsEnum::SUPPLIER_PRODUCTS->value ?
                //     fn () => SupplierProductsResource::collection(IndexSupplierProducts::run($agent))
                //     : Inertia::lazy(fn () => SupplierProductsResource::collection(IndexSupplierProducts::run($agent))),

                AgentTabsEnum::HISTORY->value => $this->tab == AgentTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($agent))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($agent)))


            ]
        )->table(
            IndexHistory::make()->tableStructure(
                prefix: AgentTabsEnum::HISTORY->value
            )
        );
    }


    public function jsonResponse(Agent $agent): AgentsResource
    {
        return new AgentsResource($agent);
    }

    public function getBreadcrumbs(Agent $agent, array $routeParameters, $suffix = null): array
    {

        return array_merge(
            ShowSupplyChainDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name' => 'grp.supply-chain.agents.index',
                            ],
                            'label' => __("Agents"),
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.supply-chain.agents.show',
                                'parameters' => $routeParameters
                            ],
                            'label' => $agent->organisation->code,
                        ],
                    ],
                    'suffix' => $suffix,

                ],
            ]
        );
    }

    public function getPrevious(Agent $agent, ActionRequest $request): ?array
    {
        $previous = Organisation::where('group_id', $agent->group_id)->where('type', OrganisationTypeEnum::AGENT)->where('code', '<', $agent->organisation->code)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous?->agent, $request->route()->getName());
    }

    public function getNext(Agent $agent, ActionRequest $request): ?array
    {
        $next = Organisation::where('group_id', $agent->group_id)->where('type', OrganisationTypeEnum::AGENT)->where('code', '>', $agent->organisation->code)->orderBy('code')->first();

        return $this->getNavigation($next?->agent, $request->route()->getName());
    }

    private function getNavigation(?Agent $agent, string $routeName): ?array
    {
        if (!$agent) {
            return null;
        }

        return match ($routeName) {
            'grp.supply-chain.agents.show' => [
                'label' => $agent->organisation->name,
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
