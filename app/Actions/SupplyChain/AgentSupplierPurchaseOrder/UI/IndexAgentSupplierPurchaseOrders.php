<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 19:30:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentSupplierPurchaseOrder\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\SupplyChain\UI\ShowSupplyChainDashboard;
use App\Http\Resources\SupplyChain\AgentSupplierPurchaseOrdersResource;
use App\InertiaTable\InertiaTable;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexAgentSupplierPurchaseOrders extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if (str_starts_with($request->route()->getName(), 'grp.org.')) {
            $this->canEdit = $request->user()->authTo("procurement.{$this->organisation->id}.edit");

            return $request->user()->authTo("procurement.{$this->organisation->id}.view");
        }

        $this->canEdit = $request->user()->authTo("supply-chain.edit");

        return $request->user()->authTo("supply-chain.view");
    }

    public function handle(?Agent $agent = null, ?Organisation $organisation = null, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('agent_supplier_purchase_orders.reference', $value)
                    ->orWhereStartWith('suppliers.code', $value)
                    ->orWhereStartWith('purchase_orders.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(AgentSupplierPurchaseOrder::class)
            ->leftJoin('suppliers', 'suppliers.id', 'agent_supplier_purchase_orders.supplier_id')
            ->leftJoin('purchase_orders', 'purchase_orders.id', 'agent_supplier_purchase_orders.purchase_order_id')
            ->leftJoin('currencies', 'currencies.id', 'agent_supplier_purchase_orders.currency_id')
            ->where('agent_supplier_purchase_orders.group_id', $this->group->id);

        if ($agent) {
            $queryBuilder->where('suppliers.agent_id', $agent->id);
        } elseif ($organisation) {
            $queryBuilder->where('purchase_orders.organisation_id', $organisation->id);
        }

        return $queryBuilder
            ->select([
                'agent_supplier_purchase_orders.slug',
                'agent_supplier_purchase_orders.reference',
                'agent_supplier_purchase_orders.state',
                'agent_supplier_purchase_orders.delivery_state',
                'agent_supplier_purchase_orders.date',
                'agent_supplier_purchase_orders.cost_total',
                'currencies.code as currency_code',
                'suppliers.code as supplier_code',
                'suppliers.slug as supplier_slug',
                'purchase_orders.reference as purchase_order_reference',
            ])
            ->defaultSort('-date')
            ->allowedFilters([$globalSearch])
            ->allowedSorts(['reference', 'date', 'cost_total', 'supplier_code', 'purchase_order_reference'])
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
                ->withLabelRecord([__('Agent supplier purchase order'), __('Agent supplier purchase orders')])
                ->withEmptyState(
                    [
                        'title' => __('No agent supplier purchase orders'),
                    ]
                )
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'supplier_code', label: __('Supplier'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'purchase_order_reference', label: __('Purchase order'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'state', label: __('State'), canBeHidden: false)
                ->column(key: 'delivery_state', label: __('Delivery'), canBeHidden: false)
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true)
                ->column(key: 'cost_total', label: __('Amount'), canBeHidden: false, sortable: true)
                ->defaultSort('-date');
        };
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle();
    }

    public function inAgent(Agent $agent, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($agent);
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        $agent = Agent::where('organisation_id', $organisation->id)->first();

        return $agent ? $this->handle($agent) : $this->handle(null, $organisation);
    }

    public function jsonResponse(LengthAwarePaginator $agentSupplierPurchaseOrders): AnonymousResourceCollection
    {
        return AgentSupplierPurchaseOrdersResource::collection($agentSupplierPurchaseOrders);
    }

    public function htmlResponse(LengthAwarePaginator $agentSupplierPurchaseOrders, ActionRequest $request): Response
    {
        return Inertia::render(
            'SupplyChain/AgentSupplierPurchaseOrders',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Agent supplier purchase orders'),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-clipboard-list'],
                        'title' => __('Agent supplier purchase orders')
                    ],
                    'title' => __('Agent supplier purchase orders'),
                ],
                'data'        => AgentSupplierPurchaseOrdersResource::collection($agentSupplierPurchaseOrders),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = [
            [
                'type'   => 'simple',
                'simple' => [
                    'route' => [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    'label' => __('Agent supplier purchase orders'),
                    'icon'  => 'fal fa-bars'
                ],
            ],
        ];

        if (str_starts_with($routeName, 'grp.org.')) {
            return array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb
            );
        }

        return array_merge(
            ShowSupplyChainDashboard::make()->getBreadcrumbs(),
            $headCrumb
        );
    }
}
