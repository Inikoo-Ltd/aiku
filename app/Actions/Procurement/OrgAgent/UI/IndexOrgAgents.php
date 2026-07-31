<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 19:07:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
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
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    public function handle(Organisation $organisation, $prefix = null): LengthAwarePaginator
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

        $queryBuilder = QueryBuilder::for(OrgAgent::class);
        $queryBuilder->leftJoin('agents', 'agents.id', 'org_agents.agent_id');
        $queryBuilder->leftJoin('organisations', 'organisations.id', 'agents.organisation_id');
        $queryBuilder->leftJoin('org_agent_stats', 'org_agent_stats.org_agent_id', 'org_agents.id');
        $queryBuilder->where('org_agents.organisation_id', $organisation->id);

        return $queryBuilder
            ->defaultSort('agents.code')
            ->select([
                'agents.code',
                'agents.name',
                'organisations.location',
                'number_org_suppliers',
                'number_purchase_orders',
                'number_stock_deliveries',
                'org_agents.status',
                'org_agents.slug',
            ])
            ->allowedSorts([
                'code',
                'name',
                'location',
                'number_org_suppliers',
                'number_purchase_orders',
                'number_stock_deliveries',
            ])
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
                ->withLabelRecord([__('Agent'), __('Agents')])
                ->withEmptyState(
                    [
                        'title' => __('No Agents'),
                        'count' => $organisation->procurementStats->number_org_agents,
                    ]
                )
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'location', label: __('Location'), sortable: true, canBeHidden: false)
                ->column(key: 'number_org_suppliers', label: __('Suppliers'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_purchase_orders', label: __('Purchase Orders'), shortLabel: 'PO', canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_stock_deliveries', label: __('Stock Deliveries'), shortLabel: 'SD', canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
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
                'title'       => __('Agents'),
                'pageHead'    => [
                    'model'   => __('Procurement'),
                    'title'   => __('Agents'),
                    'icon'    => [
                        'title' => __('Agents'),
                        'icon'  => 'fal fa-people-arrows',
                    ],
                ],
                'data'        => OrgAgentsResource::collection($agents),
            ],
        )->table($this->tableStructure($this->organisation));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'label' => __('Agents'),
                        'icon'  => 'fal fa-bars',
                        'route' => [
                            'name'       => 'grp.org.procurement.org_agents.index',
                            'parameters' => ['organisation' => $routeParameters['organisation']],
                        ],
                    ],
                ],
            ],
        );
    }
}
