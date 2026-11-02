<?php

namespace App\Actions\Procurement\OrgAgent\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
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

class IndexSuspendedOrgAgents extends OrgAction
{
    use WithProcurementAuthorisation;

    public function handle(Organisation $organisation, ?string $prefix = null): LengthAwarePaginator
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

        return QueryBuilder::for(OrgAgent::class)
            ->join('agents', 'agents.id', '=', 'org_agents.agent_id')
            ->join('organisations', 'organisations.id', '=', 'agents.organisation_id')
            ->leftJoin('org_agent_stats', 'org_agent_stats.org_agent_id', '=', 'org_agents.id')
            ->where('org_agents.organisation_id', $organisation->id)
            ->where('org_agents.status', false)
            ->defaultSort('agents.code')
            ->select([
                'org_agents.slug',
                'org_agents.status',
                'agents.code',
                'agents.name',
                'organisations.location',
                'org_agent_stats.number_org_suppliers',
                'org_agent_stats.number_purchase_orders',
                'org_agent_stats.number_stock_deliveries',
            ])
            ->allowedSorts(['code', 'name', 'number_org_suppliers', 'number_purchase_orders', 'number_stock_deliveries'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withLabelRecord([__('Suspended agent'), __('Suspended agents')])
                ->withGlobalSearch()
                ->withEmptyState(['title' => __('No suspended agents')])
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'location', label: __('Supplier location'), canBeHidden: false)
                ->column(key: 'number_org_suppliers', label: __('Suppliers'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'number_purchase_orders', label: __('Purchase Orders'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'number_stock_deliveries', label: __('Deliveries'), canBeHidden: false, sortable: true, align: 'right')
                ->defaultSort('code');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function htmlResponse(LengthAwarePaginator $orgAgents, ActionRequest $request): Response
    {
        return Inertia::render(
            'Procurement/SuspendedOrgAgents',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Suspended agents'),
                'pageHead'    => [
                    'model' => __('Agents'),
                    'title' => __('Suspended agents'),
                    'icon'  => [
                        'title' => __('Suspended agents'),
                        'icon'  => 'fal fa-people-arrows',
                    ],
                ],
                'data'        => OrgAgentsResource::collection($orgAgents),
            ]
        )->table($this->tableStructure());
    }

    public function jsonResponse(LengthAwarePaginator $orgAgents): AnonymousResourceCollection
    {
        return OrgAgentsResource::collection($orgAgents);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexOrgAgents::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'label' => __('Suspended'),
                        'route' => [
                            'name'       => 'grp.org.procurement.org_agents.suspended',
                            'parameters' => ['organisation' => $routeParameters['organisation']],
                        ],
                    ],
                ],
            ],
        );
    }
}
