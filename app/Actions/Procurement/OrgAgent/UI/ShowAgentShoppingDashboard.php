<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\GetAgentOrderCapacity;
use App\Actions\Procurement\OrgAgent\GetAgentStockCoverBuckets;
use App\Actions\Procurement\OrgAgent\GetAgentSupplierPerformance;
use App\Actions\Procurement\OrgAgent\WithAgentShoppingSubNavigation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemPriorityEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowAgentShoppingDashboard extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithAgentShoppingSubNavigation;

    private OrgAgent $orgAgent;

    /**
     * Orders already placed with the sub-suppliers through this agent, nothing shipped yet or not.
     *
     * @return array<int, array<string, mixed>>
     */
    private function openSupplierPurchaseOrders(OrgAgent $orgAgent): array
    {
        return DB::table('agent_supplier_purchase_orders as aspo')
            ->join('purchase_orders as po', 'po.id', 'aspo.purchase_order_id')
            ->leftJoin('suppliers as sup', 'sup.id', 'aspo.supplier_id')
            ->where('po.parent_type', 'OrgAgent')
            ->where('po.parent_id', $orgAgent->id)
            ->whereNull('po.deleted_at')
            ->whereNull('aspo.deleted_at')
            ->whereIn('aspo.state', GetAgentSupplierPerformance::OPEN_STATES)
            ->whereNotIn('aspo.delivery_state', GetAgentSupplierPerformance::CLOSED_DELIVERY_STATES)
            ->selectRaw("aspo.id, aspo.slug, aspo.reference, aspo.state, aspo.delivery_state, aspo.estimated_received_at,
                sup.code as supplier_code, sup.id as supplier_id,
                coalesce(aspo.submitted_at, aspo.date, aspo.created_at) as reference_date,
                extract(day from now() - coalesce(aspo.submitted_at, aspo.date, aspo.created_at))::int as days_old,
                extract(day from now() - coalesce(aspo.estimated_received_at, aspo.submitted_at))::int as days_late,
                aspo.estimated_received_at is null as no_eta")
            ->orderByRaw('coalesce(aspo.estimated_received_at, aspo.submitted_at)')
            ->limit(40)
            ->get()
            ->map(fn ($order) => [
                'id'            => $order->id,
                'slug'          => $order->slug,
                'reference'     => $order->reference,
                'state'         => $order->state,
                'supplier_code' => $order->supplier_code,
                'supplier_id'   => $order->supplier_id ? (int) $order->supplier_id : null,
                'date'          => $order->reference_date,
                'days_old'      => (int) $order->days_old,
                'days_late'     => $order->days_late !== null && (int) $order->days_late > 0 ? (int) $order->days_late : null,
                'no_eta'        => (bool) $order->no_eta,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function openStockDeliveries(OrgAgent $orgAgent): array
    {
        return DB::table('stock_deliveries as sd')
            ->leftJoin('suppliers as sup', 'sup.id', 'sd.supplier_id')
            ->where('sd.organisation_id', $orgAgent->organisation_id)
            ->where('sd.agent_id', $orgAgent->agent_id)
            ->whereIn('sd.state', GetAgentSupplierPerformance::OPEN_DELIVERY_STATES)
            ->whereNull('sd.deleted_at')
            ->selectRaw("sd.id, sd.slug, sd.reference, sd.state, sd.dispatched_at,
                sd.number_stock_delivery_items_except_cancelled as items,
                sup.code as supplier_code,
                extract(day from now() - sd.dispatched_at)::int as days_in_transit,
                coalesce(sd.date, sd.created_at) as reference_date,
                extract(day from now() - coalesce(sd.date, sd.created_at))::int as days_old")
            ->orderByRaw('coalesce(sd.date, sd.created_at)')
            ->limit(40)
            ->get()
            ->map(fn ($stockDelivery) => [
                'id'              => $stockDelivery->id,
                'slug'            => $stockDelivery->slug,
                'reference'       => $stockDelivery->reference,
                'state'           => $stockDelivery->state,
                'supplier_code'   => $stockDelivery->supplier_code,
                'items'           => (int) $stockDelivery->items,
                'days_in_transit' => $stockDelivery->dispatched_at !== null ? (int) $stockDelivery->days_in_transit : null,
                'date'            => $stockDelivery->reference_date,
                'days_old'        => (int) $stockDelivery->days_old,
            ])
            ->all();
    }

    public function handle(OrgAgent $orgAgent): array
    {
        $openItems = DB::table('shopping_list_items')
            ->where('organisation_id', $orgAgent->organisation_id)
            ->where('agent_id', $orgAgent->agent_id)
            ->where('state', ShoppingListItemStateEnum::OPEN->value)
            ->whereNull('deleted_at')
            ->selectRaw('count(*) as total, min(created_at) as oldest_at')
            ->first();

        $priorityBreakdown = DB::table('shopping_list_items')
            ->where('organisation_id', $orgAgent->organisation_id)
            ->where('agent_id', $orgAgent->agent_id)
            ->where('state', ShoppingListItemStateEnum::OPEN->value)
            ->whereNull('deleted_at')
            ->selectRaw('priority, count(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority');

        return [
            'cover'                        => GetAgentStockCoverBuckets::run($orgAgent),
            'order_capacity'               => GetAgentOrderCapacity::run($orgAgent),
            'suppliers'                    => GetAgentSupplierPerformance::run($orgAgent),
            'open_supplier_purchase_orders' => $this->openSupplierPurchaseOrders($orgAgent),
            'open_stock_deliveries'        => $this->openStockDeliveries($orgAgent),
            'open_items_count'             => (int) $openItems->total,
            'oldest_item_at'               => $openItems->oldest_at,
            'priority_breakdown'           => collect(ShoppingListItemPriorityEnum::cases())->map(fn ($priority) => [
                'priority' => $priority->value,
                'label'    => ShoppingListItemPriorityEnum::labels()[$priority->value],
                'count'    => $priorityBreakdown[$priority->value] ?? 0,
            ])->values(),
        ];
    }

    public function asController(Organisation $organisation, OrgAgent $orgAgent, ActionRequest $request): array
    {
        $this->orgAgent = $orgAgent;
        $this->initialisation($organisation, $request);

        return $this->handle($orgAgent);
    }

    public function htmlResponse(array $data, ActionRequest $request): Response
    {
        return Inertia::render(
            'Procurement/AgentShoppingDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs($this->orgAgent, $request->route()->originalParameters()),
                'title'       => __('Shopping'),
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-shopping-basket'],
                        'title' => __('Shopping'),
                    ],
                    'model'         => $this->orgAgent->agent->name,
                    'title'         => __('Shopping'),
                    'subNavigation' => $this->getAgentShoppingNavigation($this->orgAgent),
                ],
                'orgAgent'    => [
                    'id'       => $this->orgAgent->id,
                    'slug'     => $this->orgAgent->slug,
                    'name'     => $this->orgAgent->agent->name,
                    'currency' => $data['order_capacity']['currency'],
                ],
                'stats'       => [
                    'open_items_count'   => $data['open_items_count'],
                    'oldest_item_at'     => $data['oldest_item_at'],
                    'estimated_total'    => $data['order_capacity']['list']['value'],
                    'priority_breakdown' => $data['priority_breakdown'],
                ],
                'coverBuckets' => $data['cover']['buckets'],
                'coverTotal'   => $data['cover']['total'],
                'leadTime'     => $data['cover']['lead_time'],
                'orderCapacity' => $data['order_capacity'],
                'suppliers'     => $data['suppliers'],
                'openSupplierPurchaseOrders' => $data['open_supplier_purchase_orders'],
                'openStockDeliveries'        => $data['open_stock_deliveries'],
                'shoppingListRoute' => [
                    'name'       => 'grp.org.procurement.shopping_list.index',
                    'parameters' => [$this->orgAgent->organisation->slug],
                ],
                'stockDeliveriesRoute' => [
                    'name'       => 'grp.org.procurement.org_agents.show.stock-deliveries.index',
                    'parameters' => [$this->orgAgent->organisation->slug, $this->orgAgent->slug],
                ],
                'supplierPurchaseOrdersRoute' => [
                    'name'       => 'grp.org.procurement.org_agents.show.agent_supplier_purchase_orders.index',
                    'parameters' => [$this->orgAgent->organisation->slug, $this->orgAgent->slug],
                ],
            ]
        );
    }

    public function getBreadcrumbs(OrgAgent $orgAgent, array $routeParameters): array
    {
        return array_merge(
            ShowOrgAgent::make()->getBreadcrumbs('grp.org.procurement.org_agents.show', $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.procurement.org_agents.show.shopping.dashboard',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Shopping'),
                        'icon'  => 'fal fa-shopping-basket',
                    ],
                ],
            ]
        );
    }
}
