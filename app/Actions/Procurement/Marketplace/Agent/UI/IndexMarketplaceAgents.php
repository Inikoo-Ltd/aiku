<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 04 May 2024 12:55:09 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\Marketplace\Agent\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Http\Resources\Procurement\MarketplaceAgentResource;
use App\InertiaTable\InertiaTable;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexMarketplaceAgents extends OrgAction
{
    private bool $canCreateAgent = false;

    public function handle($prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('agents.code', $value)
                    ->orWhereAnyWordStartWith('agents.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Agent::class);

        /*
        foreach ($this->elementGroups as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                prefix: $prefix,
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine']
            );
        }
        */



        return $queryBuilder
            ->leftJoin('agent_stats', 'agent_stats.agent_id', '=', 'agents.id')
            ->defaultSort('agents.code')
            ->select(['code', 'name', 'slug', 'number_suppliers', 'number_supplier_products', 'location'
          //  DB::raw(' (select count(*) from org_agents where org_agents.agent_id=agents.id and  org_agents.organisation_id=? )  as adoption',$this->organisation->id)
            ])
            ->selectRaw('
                (select count(*) from org_agents where org_agents.agent_id=agents.id and  org_agents.organisation_id=? )  as adoption', [$this->organisation->id])


            ->allowedFilters([$globalSearch])
            ->allowedSorts(['code', 'name', 'number_suppliers', 'number_supplier_products'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {

            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title'       => __('no agents'),
                        'description' => $this->canCreateAgent ? __('Get started by creating a new agent.') : null,
                        'count'       => $this->organisation->procurementStats->number_agents,
                        'action'      => $this->canEdit ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('New agent'),
                            'label'   => __('agent'),
                            'route'   => [
                                'name'       => 'grp.supply-chain.org_agents.create',

                            ]
                        ] : null
                    ]
                )
                ->column(key: 'adoption', label: [
                    'type'   => 'icon',
                    'data'   => ['fal','fa-yin-yang'],
                    'tooltip' => __('adoption')
                ], canBeHidden: false)
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'location', label: __('location'), canBeHidden: false)
                ->column(key: 'number_suppliers', label: __('suppliers'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_supplier_products', label: __('products'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit        = $request->user()->authTo("procurement.{$this->organisation->id}.view");
        $this->canCreateAgent = $request->user()->authTo("supply-chain.edit");
        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {

        $this->initialisation($organisation, $request);

        return $this->handle();
    }


    public function jsonResponse(LengthAwarePaginator $agent): AnonymousResourceCollection
    {
        return MarketplaceAgentResource::collection($agent);
    }


    public function htmlResponse(LengthAwarePaginator $agent, ActionRequest $request): Response
    {


        return Inertia::render(
            'Procurement/MarketplaceAgents',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __("agent's marketplace"),
                'pageHead'    => [
                    'title'  => __("agent's marketplace"),

                    'actions' => [
                        $this->canEdit && $request->route()->getName() == 'grp.org.procurement.marketplace.org_agents.index' ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('New agent'),
                            'label'   => __('agent'),
                            'route'   => [
                                'name'       => 'grp.org.procurement.marketplace.org_agents.create',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false,
                    ]
                ],
                'data'        => MarketplaceAgentResource::collection($agent),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return
            array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.procurement.marketplace.org_agents.index',
                                'parameters' => array_values($routeParameters)
                            ],
                            'label' => __("Agent's marketplace"),
                            'icon'  => 'fal fa-bars'
                        ]
                    ]
                ]
            );
    }
}
