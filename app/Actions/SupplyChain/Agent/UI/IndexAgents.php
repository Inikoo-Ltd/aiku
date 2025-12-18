<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 19:07:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\UI;

use App\Actions\GrpAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\SupplyChain\UI\ShowSupplyChainDashboard;
use App\Http\Resources\SupplyChain\AgentsResource;
use App\InertiaTable\InertiaTable;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexAgents extends GrpAction
{
    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo('supply-chain.edit');
        return $request->user()->authTo('supply-chain.view');
    }

    protected function getElementGroups(Group $group): array
    {
        return
            [
                'status' => [
                    'label'    => __('Status'),
                    'elements' => [
                        'active'    =>
                            [
                                __('Active'),
                                $group->supplyChainStats->number_active_agents
                            ],
                        'suspended' => [
                            __('Suspended'),
                            $group->supplyChainStats->number_archived_agents
                        ]
                    ],
                    'engine'   => function ($query, $elements) {
                        $query->where('agents.status', array_pop($elements) === 'active');
                    }

                ],
            ];
    }

    public function handle($prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('organisations.code', $value)
                    ->orWhereAnyWordStartWith('organisations.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Agent::class);


        foreach ($this->getElementGroups($this->group) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }



        return $queryBuilder
            ->leftJoin('organisations', 'organisation_id', 'organisations.id')
            ->leftJoin('agent_stats', 'agent_stats.agent_id', '=', 'agents.id')
            ->defaultSort('organisations.code')
            ->select(['organisations.code', 'agents.name', 'agents.slug', 'number_suppliers', 'number_supplier_products', 'location'])
            ->allowedFilters([$globalSearch])
            ->allowedSorts(['code', 'name', 'number_suppliers', 'number_supplier_products'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {

            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }


            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('Agent'),__('Agents')])
                ->withEmptyState(
                    [
                        'title'       => __('No Agents'),
                        'description' => $this->canEdit ? __('Get started by creating a new agent.') : null,
                        'count'       => $parent->supplyChainStats->number_agents,
                        'action'      => $this->canEdit ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('New Agent'),
                            'label'   => __('Agent'),
                            'route'   => [
                                'name'       => 'grp.supply-chain.agents.create',
                            ]
                        ] : null
                    ]
                )

                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'location', label: __('Location'), canBeHidden: false)
                ->column(key: 'number_suppliers', label: __('Suppliers'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_supplier_products', label: __('Products'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }



    public function asController(ActionRequest $request): LengthAwarePaginator
    {

        $this->initialisation(app('group'), $request);

        return $this->handle();
    }

    public function jsonResponse(LengthAwarePaginator $agent): AnonymousResourceCollection
    {
        return AgentsResource::collection($agent);
    }

    public function htmlResponse(LengthAwarePaginator $agent, ActionRequest $request): Response
    {


        return Inertia::render(
            'SupplyChain/Agents',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __("Agents"),
                'pageHead'    => [
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'fa-people-arrows'],
                            'title' => __('Agents')
                        ],
                    'title'  => __("Agents"),

                    'actions' => [
                        $this->canEdit && $request->route()->getName() == 'grp.supply-chain.agents.index' ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('New agent'),
                            'label'   => __('Agent'),
                            'route'   => [
                                'name'       => 'grp.supply-chain.agents.create',
                            ]
                        ] : false,
                    ]
                ],
                'data'        => AgentsResource::collection($agent),
            ]
        )->table($this->tableStructure($this->group));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Agents'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };
        return match ($routeName) {
            'grp.supply-chain.agents.index' =>
            array_merge(
                ShowSupplyChainDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name' => 'grp.supply-chain.agents.index',
                        'parameters' => []
                    ]
                )
            ),
            'grp.overview.procurement.agents.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name' => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => []
        };
    }
}
