<?php

namespace App\Actions\SupplyChain\Supplier\UI;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\UI\ShowSupplyChainDashboard;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Http\Resources\SupplyChain\AgentSuppliersResource;
use App\InertiaTable\InertiaTable;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexAgentSuppliers extends OrgAction
{
    use WithSupplyChainAuthorisation;

    private Group $parent;

    public function handle(Group $group, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('suppliers.code', $value)
                    ->orWhereAnyWordStartWith('suppliers.name', $value)
                    ->orWhereStartWith('agents.code', $value)
                    ->orWhereAnyWordStartWith('agents.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(Supplier::class)
            ->leftJoin('supplier_stats', 'supplier_stats.supplier_id', '=', 'suppliers.id')
            ->join('agents', 'agents.id', '=', 'suppliers.agent_id')
            ->where('suppliers.group_id', $group->id)
            ->where('suppliers.status', true)
            ->whereNotNull('suppliers.agent_id')
            ->defaultSort('suppliers.code')
            ->select([
                'suppliers.id',
                'suppliers.slug',
                'suppliers.code',
                'suppliers.name',
                'suppliers.location',
                'agents.slug as agent_slug',
                'agents.code as agent_code',
                'agents.name as agent_name',
                'supplier_stats.number_supplier_products',
                'supplier_stats.number_purchase_orders',
                'supplier_stats.number_stock_deliveries',
            ])
            ->allowedSorts([
                'code',
                'name',
                'agent_code',
                'location',
                'number_supplier_products',
                'number_purchase_orders',
                'number_stock_deliveries',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group $group, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($group, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('Agent Supplier'), __('Agent Suppliers')])
                ->withEmptyState([
                    'title' => __('No Agent Suppliers Found'),
                    'count' => $group->supplyChainStats->number_active_suppliers_in_agents,
                ])
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'agent_code', label: __('Agent'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'location', label: __('Location'), canBeHidden: false, sortable: true)
                ->column(key: 'number_supplier_products', label: __("Supplier's Products"), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'number_purchase_orders', label: __('Purchase Orders'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'number_stock_deliveries', label: __('Stock Deliveries'), canBeHidden: false, sortable: true, align: 'right')
                ->defaultSort('code');
        };
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $group = group();
        $this->parent = $group;
        $this->initialisationFromGroup($group, $request);

        return $this->handle($group);
    }

    public function htmlResponse(LengthAwarePaginator $suppliers): Response
    {
        return Inertia::render(
            'SupplyChain/AgentSuppliers',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Agent Suppliers'),
                'pageHead'    => [
                    'title' => __('Agent Suppliers'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-people-arrows'],
                        'title' => __('Agent Suppliers'),
                    ],
                ],
                'data' => AgentSuppliersResource::collection($suppliers),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function jsonResponse(LengthAwarePaginator $suppliers): AnonymousResourceCollection
    {
        return AgentSuppliersResource::collection($suppliers);
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowSupplyChainDashboard::make()->getBreadcrumbs(),
            [[
                'type'   => 'simple',
                'simple' => [
                    'label' => __('Agent Suppliers'),
                    'icon'  => 'fal fa-bars',
                    'route' => [
                        'name' => 'grp.supply-chain.agent_suppliers.index',
                    ],
                ],
            ]]
        );
    }
}
