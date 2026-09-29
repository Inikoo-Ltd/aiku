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
                    ->orWhereAnyWordStartWith('agents.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(Supplier::class)
            ->leftJoin('agents', 'agents.id', '=', 'suppliers.agent_id')
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
                'agents.name as agent_name',
            ])
            ->allowedSorts(['code', 'name', 'agent_code', 'location'])
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
                    'slug' => $this->agent->slug,
                    'code' => $this->agent->code,
                    'name' => $this->agent->name,
                ],
                'data'          => AssignableSuppliersResource::collection($suppliers),
            ]
        )->table($this->tableStructure());
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
