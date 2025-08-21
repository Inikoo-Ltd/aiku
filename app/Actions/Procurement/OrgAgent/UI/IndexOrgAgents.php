<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 19:07:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Enums\UI\Procurement\OrgAgentTabsEnum;
use App\Http\Resources\Procurement\OrgAgentsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\OrgAgent;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrgAgents extends OrgAction
{
    public function handle(Organisation $organisation, $prefix = null): LengthAwarePaginator
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

        $queryBuilder = QueryBuilder::for(OrgAgent::class);


        $queryBuilder->where('org_agents.organisation_id', $organisation->id);



        return $queryBuilder
            ->defaultSort('organisations.code')
            ->select([
                'organisations.code',
                'organisations.name',
                'org_agents.slug',
                'organisations.location as location',
                'number_org_suppliers',
                'number_stock_deliveries',
                'number_purchase_orders',
                'number_org_supplier_products'
            ])
            ->leftJoin('agents', 'agents.id', 'org_agents.agent_id')
            ->leftJoin('organisations', 'organisations.id', 'agents.organisation_id')
            ->leftJoin('org_agent_stats', 'org_agent_stats.org_agent_id', 'org_agents.id')
            ->allowedSorts(['code', 'name', 'number_purchase_orders', 'number_org_suppliers', 'number_stock_deliveries', 'number_org_supplier_products', 'location'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Organisation $organisation, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix, $organisation) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }
            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __('no agents'),
                        'count' => $organisation->procurementStats->number_org_agents,

                    ]
                )
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'location', label: __('location'), sortable: true, canBeHidden: false)
                ->column(key: 'number_org_supplier_products', label: __('supplier products'), shortLabel: 'SP', canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_purchase_orders', label: __('purchase orders'), shortLabel: 'PO', canBeHidden: false, sortable: true, searchable: true)

                ->column(key: 'number_org_suppliers', label: __('suppliers'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_stock_deliveries', label: __('delivery stocks'), shortLabel: 'DS', canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo("procurement.{$this->organisation->id}.edit");

        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request)->withTab(OrgAgentTabsEnum::values());
        return $this->handle($organisation);
    }

    public function maya(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->maya   = true;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }


    public function jsonResponse(LengthAwarePaginator $agents): AnonymousResourceCollection
    {
        return OrgAgentsResource::collection($agents);
    }


    public function htmlResponse(LengthAwarePaginator $agents, ActionRequest $request): Response
    {
        return Inertia::render(
            'Procurement/OrgAgents',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'       => __('agents'),
                'pageHead'    => [
                    'model'   => __('Procurement'),
                    'title'   => __('agents'),
                    'icon'    => [
                        'title' => __('website'),
                        'icon'  => 'fal fa-people-arrows'
                    ],
                    'actions' => [
                        $this->canEdit && $request->route()->getName() == 'grp.org.procurement.org_agents.index' ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('new agent'),
                            'label'   => __('agent'),
                            'route'   => [
                                'name'       => 'grp.org.procurement.org_agents.create',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false,
                    ]
                ],
                'data'        => OrgAgentsResource::collection($agents),
            ]
        )->table($this->tableStructure($this->organisation));
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
                                'name'       => 'grp.org.procurement.org_agents.index',
                                'parameters' => ['organisation' => $routeParameters['organisation']]
                            ],
                            'label' => __('Agents'),
                            'icon'  => 'fal fa-bars'
                        ]
                    ]
                ]
            );
    }
}
