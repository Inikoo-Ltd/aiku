<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier\UI;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\Agent\UI\ShowAgent;
use App\Actions\SupplyChain\Agent\WithAgentSubNavigation;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
use App\Http\Resources\SupplyChain\AssignableSuppliersResource;
use App\InertiaTable\InertiaTable;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexAssignableSuppliers extends OrgAction
{
    use WithSupplyChainEditAuthorisation;
    use WithAgentSubNavigation;

    private Agent $agent;

    public function handle(Agent $agent, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('suppliers.code', $value)
                    ->orWhereAnyWordStartWith('suppliers.name', $value)
                    ->orWhereAnyWordStartWith('agent_organisations.name', $value)
                    ->orWhereRaw("suppliers.location->>1 ilike ?", [$value.'%']);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $countryFilter = AllowedFilter::callback('country', function ($query, $value) {
            $query->whereRaw("suppliers.location->>0 = ?", [$value]);
        });

        return QueryBuilder::for(Supplier::class)
            ->leftJoin('agents', function ($join) {
                $join->on('agents.id', '=', 'suppliers.agent_id')->whereNull('agents.deleted_at');
            })
            ->leftJoin('organisations as agent_organisations', 'agent_organisations.id', '=', 'agents.organisation_id')
            ->where('suppliers.group_id', $agent->group_id)
            ->where('suppliers.status', true)
            ->where(function ($query) use ($agent) {
                $query->whereNull('suppliers.agent_id')
                    ->orWhere('suppliers.agent_id', '!=', $agent->id);
            })
            ->defaultSort('suppliers.code')
            ->select([
                'suppliers.id',
                'suppliers.slug',
                'suppliers.code',
                'suppliers.name',
                'suppliers.location',
                'suppliers.agent_id',
                'agents.slug as agent_slug',
                'agents.code as agent_code',
                'agent_organisations.name as agent_name',
            ])
            ->selectRaw(
                "(select string_agg(organisations.name, ', ' order by organisations.name)
                    from org_suppliers
                    join organisations on organisations.id = org_suppliers.organisation_id
                    where org_suppliers.supplier_id = suppliers.id
                    and org_suppliers.status = true
                    and org_suppliers.organisation_id not in (select org_agents.organisation_id from org_agents where org_agents.agent_id = ?)
                ) as organisations_losing_supplier",
                [$agent->id]
            )
            ->allowedSorts(['code', 'name', 'agent_code', 'location'])
            ->allowedFilters([$globalSearch, $countryFilter])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Agent $agent, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($agent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $countryOptions = Supplier::query()
                ->where('group_id', $agent->group_id)
                ->where('status', true)
                ->whereRaw("location->>0 <> ''")
                ->selectRaw("distinct location->>0 as country_code, location->>1 as country_name")
                ->orderByRaw('location->>1')
                ->get()
                ->mapWithKeys(fn ($country) => [$country->country_code => $country->country_name ?: $country->country_code])
                ->all();

            $table
                ->selectFilter('country', $countryOptions, __('Country'))
                ->withGlobalSearch()
                ->withLabelRecord([__('Supplier'), __('Suppliers')])
                ->withEmptyState([
                    'title' => __('No Suppliers Found'),
                ])
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'agent_code', label: __('Current Agent'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'location', label: __('Location'), canBeHidden: false, sortable: true)
                ->column(key: 'actions', label: '', canBeHidden: false)
                ->defaultSort('code');
        };
    }

    public function asController(Agent $agent, ActionRequest $request): LengthAwarePaginator
    {
        $this->agent = $agent;
        $this->initialisationFromGroup($agent->group, $request);

        return $this->handle($agent);
    }

    public function jsonResponse(LengthAwarePaginator $suppliers): AnonymousResourceCollection
    {
        return AssignableSuppliersResource::collection($suppliers);
    }

    public function htmlResponse(LengthAwarePaginator $suppliers, ActionRequest $request): Response
    {
        return Inertia::render(
            'SupplyChain/AssignableSuppliers',
            [
                'breadcrumbs'   => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'         => __('Add Supplier'),
                'pageHead'      => [
                    'title'         => __('Add Supplier'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-people-arrows'],
                        'title' => __('Add Supplier'),
                    ],
                    'subNavigation' => $this->getAgentNavigation($this->agent),
                    'actions'       => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.supply-chain.agents.show.suppliers.index',
                                'parameters' => $request->route()->originalParameters(),
                            ],
                        ],
                    ],
                ],
                'agent'         => [
                    'id'   => $this->agent->id,
                    'code' => $this->agent->code,
                    'name' => $this->agent->organisation->name,
                ],
                'data'          => AssignableSuppliersResource::collection($suppliers),
            ]
        )->table($this->tableStructure($this->agent));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowAgent::make()->getBreadcrumbs($this->agent, 'grp.supply-chain.agents.show.suppliers.index', $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'label' => __('Add Supplier'),
                    ],
                ],
            ]
        );
    }
}
