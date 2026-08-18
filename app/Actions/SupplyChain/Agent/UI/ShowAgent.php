<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Apr 2024 20:10:25 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
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

class ShowAgent extends OrgAction
{
    use WithSupplyChainAuthorisation;
    use WithAgentSubNavigation;
    use WithAgentEditAction;

    public function handle(Agent $agent): Agent
    {
        return $agent;
    }


    public function asController(Agent $agent, ActionRequest $request): Agent
    {
        $this->initialisationFromGroup(app('group'), $request)->withTab(AgentTabsEnum::values());

        return $this->handle($agent);
    }


    public function htmlResponse(Agent $agent, ActionRequest $request): Response
    {
        return Inertia::render(
            'SupplyChain/Agent',
            [
                'title'                        => __("Agent") . " " . $agent->name,
                'breadcrumbs'                  => $this->getBreadcrumbs(
                    $agent,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                   => [
                    'previous' => $this->getPrevious($agent, $request),
                    'next'     => $this->getNext($agent, $request),
                ],
                'pageHead'                     => [
                    'model'         => __('Agent'),
                    'icon'          =>
                        [
                            'icon'  => ['fal', 'people-arrows'],
                            'title' => __('Agent')
                        ],
                    'subNavigation' => $this->getAgentNavigation($agent),
                    'title'         => $agent->organisation->name,
                    'actions'       => [
                        $this->agentEditAction(
                            'grp.supply-chain.agents.edit',
                            $request->route()->originalParameters()
                        ),
                    ],
                ],
                'tabs'                         => [
                    'current'    => $this->tab,
                    'navigation' => AgentTabsEnum::navigation()
                ],
                AgentTabsEnum::SHOWCASE->value => $this->tab == AgentTabsEnum::SHOWCASE->value ?
                    fn () => GetAgentShowcase::run($agent)
                    : Inertia::optional(fn () => GetAgentShowcase::run($agent)),

                // AgentTabsEnum::SUPPLIERS->value => $this->tab == AgentTabsEnum::SUPPLIERS->value
                //     ?
                //     fn () => SuppliersResource::collection(
                //         IndexSuppliers::run(
                //             parent: $agent,
                //             prefix: 'suppliers'
                //         )
                //     )
                //     : Inertia::optional(fn () => SuppliersResource::collection(
                //         IndexSuppliers::run(
                //             parent: $agent,
                //             prefix: 'suppliers'
                //         )
                //     )),

                // AgentTabsEnum::SUPPLIER_PRODUCTS->value => $this->tab == AgentTabsEnum::SUPPLIER_PRODUCTS->value ?
                //     fn () => SupplierProductsResource::collection(IndexSupplierProducts::run($agent))
                //     : Inertia::optional(fn () => SupplierProductsResource::collection(IndexSupplierProducts::run($agent))),

                AgentTabsEnum::HISTORY->value => $this->tab == AgentTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($agent))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($agent)))


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

    public function getBreadcrumbs(Agent $agent, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (Agent $agent, array $routeParameters, $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Agents'),
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $agent->organisation->code,
                        ],
                    ],
                    'suffix' => $suffix,
                ],
            ];
        };

        return match ($routeName) {
            'grp.supply-chain.agents.show',
            'grp.supply-chain.agents.edit',
            'grp.supply-chain.agents.show.supplier_products.index',
            'grp.supply-chain.agents.show.supplier_products.show',
            'grp.supply-chain.agents.show.suppliers.index',
            'grp.supply-chain.agents.show.suppliers.show',
            'grp.supply-chain.agents.show.suppliers.supplier_products.index',
            'grp.supply-chain.agents.show.suppliers.supplier_products.show' =>
            array_merge(
                ShowSupplyChainDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    $agent,
                    [
                        'index' => [
                            'name'       => 'grp.supply-chain.agents.index',
                            'parameters' => []
                        ],
                        'model' => [
                            'name'       => 'grp.supply-chain.agents.show',
                            'parameters' => ['agent' => $agent->slug]
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(Agent $agent, ActionRequest $request): ?array
    {
        $previous = Organisation::where('group_id', $agent->group_id)->where('type', OrganisationTypeEnum::AGENT)->where('code', '<', $agent->organisation->code)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous?->agent, $request);
    }

    public function getNext(Agent $agent, ActionRequest $request): ?array
    {
        $next = Organisation::where('group_id', $agent->group_id)->where('type', OrganisationTypeEnum::AGENT)->where('code', '>', $agent->organisation->code)->orderBy('code')->first();

        return $this->getNavigation($next?->agent, $request);
    }

    private function getNavigation(?Agent $agent, ActionRequest $request): ?array
    {
        if (!$agent) {
            return null;
        }

        return [
            'label' => $agent->organisation->name,
            'route' => [
                'name'       => $request->route()->getName(),
                'parameters' => array_merge(
                    $request->route()->originalParameters(),
                    ['agent' => $agent->slug]
                ),
            ],
        ];
    }

}
