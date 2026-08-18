<?php

namespace App\Actions\Procurement\OrgSupplier\UI;

use App\Actions\Procurement\WithParentSiblingsNavigation;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgAgent\WithOrgAgentSubNavigation;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Http\Resources\Procurement\OrgAgentSuppliersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrgAgentSuppliers extends OrgAction
{
    use WithParentSiblingsNavigation;
    use WithOrgAgentSubNavigation;
    use WithProcurementAuthorisation;

    private Organisation|OrgAgent $parent;

    public function handle(Organisation|OrgAgent $parent, ?string $prefix = null): LengthAwarePaginator
    {
        $organisation = $parent instanceof Organisation ? $parent : $parent->organisation;
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

        $purchaseOrderCounts = DB::table('agent_supplier_purchase_orders')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'agent_supplier_purchase_orders.purchase_order_id')
            ->whereNull('agent_supplier_purchase_orders.deleted_at')
            ->selectRaw('purchase_orders.organisation_id, agent_supplier_purchase_orders.supplier_id, count(*) as number_agent_supplier_purchase_orders')
            ->groupBy('purchase_orders.organisation_id', 'agent_supplier_purchase_orders.supplier_id');

        $supplierDeliveryCounts = DB::table('stock_deliveries')
            ->whereNull('stock_deliveries.deleted_at')
            ->whereNotNull('stock_deliveries.supplier_id')
            ->selectRaw('stock_deliveries.organisation_id, stock_deliveries.supplier_id, count(*) as number_supplier_deliveries')
            ->groupBy('stock_deliveries.organisation_id', 'stock_deliveries.supplier_id');

        $queryBuilder = QueryBuilder::for(OrgSupplier::class)
            ->leftJoin('suppliers', 'org_suppliers.supplier_id', '=', 'suppliers.id')
            ->leftJoin('org_supplier_stats', 'org_supplier_stats.org_supplier_id', '=', 'org_suppliers.id')
            ->join('org_agents', 'org_agents.id', '=', 'org_suppliers.org_agent_id')
            ->join('agents', 'agents.id', '=', 'org_agents.agent_id')
            ->leftJoinSub($purchaseOrderCounts, 'agent_supplier_purchase_order_counts', function (JoinClause $join) {
                $join->on('agent_supplier_purchase_order_counts.organisation_id', '=', 'org_suppliers.organisation_id')
                    ->on('agent_supplier_purchase_order_counts.supplier_id', '=', 'org_suppliers.supplier_id');
            })
            ->leftJoinSub($supplierDeliveryCounts, 'supplier_delivery_counts', function (JoinClause $join) {
                $join->on('supplier_delivery_counts.organisation_id', '=', 'org_suppliers.organisation_id')
                    ->on('supplier_delivery_counts.supplier_id', '=', 'org_suppliers.supplier_id');
            })
            ->where('org_suppliers.organisation_id', $organisation->id)
            ->whereNotNull('org_suppliers.org_agent_id')
            ->where('org_suppliers.status', true);

        if ($parent instanceof OrgAgent) {
            $queryBuilder->where('org_suppliers.org_agent_id', $parent->id);
        }

        return $queryBuilder
            ->defaultSort('suppliers.code')
            ->select([
                'suppliers.code',
                'suppliers.name',
                'suppliers.location',
                'org_suppliers.slug as org_supplier_slug',
                'org_agents.slug as org_agent_slug',
                'agents.code as agent_code',
                'agents.name as agent_name',
                'org_supplier_stats.number_org_supplier_products',
                DB::raw('coalesce(agent_supplier_purchase_order_counts.number_agent_supplier_purchase_orders, 0) as number_agent_supplier_purchase_orders'),
                DB::raw('coalesce(supplier_delivery_counts.number_supplier_deliveries, 0) as number_supplier_deliveries'),
            ])
            ->allowedSorts([
                'code',
                'name',
                'agent_code',
                'location',
                'number_org_supplier_products',
                'number_agent_supplier_purchase_orders',
                'number_supplier_deliveries',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Organisation|OrgAgent $parent, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withLabelRecord([__('Agent supplier'), __('Agent suppliers')])
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No Agent Suppliers Found'),
                    'count' => $parent instanceof Organisation
                        ? $parent->procurementStats->number_active_org_suppliers_in_agents
                        : $parent->stats->number_active_org_suppliers,
                ])
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);

            if ($parent instanceof Organisation) {
                $table->column(key: 'agent_code', label: __('Agent'), canBeHidden: false, sortable: true, searchable: true);
            }

            $table
                ->column(key: 'location', label: __('Location'), canBeHidden: false, sortable: true)
                ->column(key: 'number_org_supplier_products', label: __("Supplier's Products"), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'number_agent_supplier_purchase_orders', label: __('Agent Supplier Purchase Orders'), shortLabel: __('Purchase Orders'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'number_supplier_deliveries', label: __('Supplier Deliveries'), canBeHidden: false, sortable: true, align: 'right')
                ->defaultSort('code');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function inOrgAgent(Organisation $organisation, OrgAgent $orgAgent, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $orgAgent;
        $this->initialisation($organisation, $request);

        return $this->handle($orgAgent);
    }

    public function htmlResponse(LengthAwarePaginator $suppliers, ActionRequest $request): Response
    {
        return Inertia::render(
            'Procurement/OrgAgentSuppliers',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('Agent Suppliers'),
                'navigation'  => $this->getParentSiblingsNavigation($this->parent, $request),
                'pageHead'    => [
                    'title'         => __('Agent Suppliers'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-people-arrows'],
                        'title' => __('Agent Suppliers'),
                    ],
                    'subNavigation' => $this->parent instanceof OrgAgent ? $this->getOrgAgentNavigation($this->parent) : null,
                ],
                'data' => OrgAgentSuppliersResource::collection($suppliers),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function jsonResponse(LengthAwarePaginator $suppliers): AnonymousResourceCollection
    {
        return OrgAgentSuppliersResource::collection($suppliers);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        if ($routeName === 'grp.org.procurement.org_agents.show.suppliers.index') {
            return array_merge(
                ShowOrgAgent::make()->getBreadcrumbs('grp.org.procurement.org_agents.show', $routeParameters),
                [[
                    'type'   => 'simple',
                    'simple' => [
                        'label' => __('Suppliers'),
                        'icon'  => 'fal fa-bars',
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters,
                        ],
                    ],
                ]]
            );
        }

        return array_merge(
            ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
            [[
                'type'   => 'simple',
                'simple' => [
                    'label' => __('Agent Suppliers'),
                    'icon'  => 'fal fa-bars',
                    'route' => [
                        'name'       => 'grp.org.procurement.org_agent_suppliers.index',
                        'parameters' => ['organisation' => $routeParameters['organisation']],
                    ],
                ],
            ]]
        );
    }
}
