<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgSupplier\GetSupplierOrderCapacity;
use App\Actions\Procurement\OrgSupplier\GetSupplierStockCoverBuckets;
use App\Actions\Procurement\OrgSupplier\WithOrgSupplierSubNavigation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemPriorityEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgSupplier;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowSupplierShoppingDashboard extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithOrgSupplierSubNavigation;

    private OrgSupplier $orgSupplier;

    /**
     * Purchase orders this supplier still owes us, worst delay first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function latePurchaseOrders(OrgSupplier $orgSupplier): array
    {
        return DB::table('purchase_orders')
            ->where('parent_type', 'OrgSupplier')
            ->where('parent_id', $orgSupplier->id)
            ->whereIn('state', [PurchaseOrderStateEnum::SUBMITTED->value, PurchaseOrderStateEnum::CONFIRMED->value])
            ->whereNotIn('delivery_state', [
                PurchaseOrderDeliveryStateEnum::RECEIVED->value,
                PurchaseOrderDeliveryStateEnum::CHECKED->value,
                PurchaseOrderDeliveryStateEnum::PLACED->value,
                PurchaseOrderDeliveryStateEnum::CANCELLED->value,
                PurchaseOrderDeliveryStateEnum::NOT_RECEIVED->value,
            ])
            ->whereNull('deleted_at')
            ->whereRaw('coalesce(estimated_received_at, submitted_at) < now()')
            ->selectRaw("id, slug, reference, state,
                extract(day from now() - coalesce(estimated_received_at, submitted_at))::int as days_late,
                estimated_received_at is null as no_eta")
            ->orderByRaw('coalesce(estimated_received_at, submitted_at)')
            ->limit(20)
            ->get()
            ->map(fn ($purchaseOrder) => [
                'id'        => $purchaseOrder->id,
                'slug'      => $purchaseOrder->slug,
                'reference' => $purchaseOrder->reference,
                'state'     => $purchaseOrder->state,
                'days_late' => (int) $purchaseOrder->days_late,
                'no_eta'    => (bool) $purchaseOrder->no_eta,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function openStockDeliveries(OrgSupplier $orgSupplier): array
    {
        return DB::table('stock_deliveries')
            ->where('parent_type', 'OrgSupplier')
            ->where('parent_id', $orgSupplier->id)
            ->whereIn('state', [
                StockDeliveryStateEnum::IN_PROCESS->value,
                StockDeliveryStateEnum::CONFIRMED->value,
                StockDeliveryStateEnum::READY_TO_SHIP->value,
                StockDeliveryStateEnum::DISPATCHED->value,
                StockDeliveryStateEnum::RECEIVED->value,
                StockDeliveryStateEnum::CHECKED->value,
                StockDeliveryStateEnum::BOOKING_IN->value,
            ])
            ->whereNull('deleted_at')
            ->selectRaw("id, slug, reference, state, dispatched_at, number_stock_delivery_items_except_cancelled as items,
                extract(day from now() - dispatched_at)::int as days_in_transit,
                coalesce(date, created_at) as reference_date,
                extract(day from now() - coalesce(date, created_at))::int as days_old")
            ->orderByRaw('coalesce(date, created_at)')
            ->limit(30)
            ->get()
            ->map(fn ($stockDelivery) => [
                'id'              => $stockDelivery->id,
                'slug'            => $stockDelivery->slug,
                'reference'       => $stockDelivery->reference,
                'state'           => $stockDelivery->state,
                'items'           => (int) $stockDelivery->items,
                'days_in_transit' => $stockDelivery->dispatched_at !== null ? (int) $stockDelivery->days_in_transit : null,
                'date'            => $stockDelivery->reference_date,
                'days_old'        => (int) $stockDelivery->days_old,
            ])
            ->all();
    }

    public function handle(OrgSupplier $orgSupplier): array
    {
        $orderCapacity = GetSupplierOrderCapacity::run($orgSupplier);

        $openItems = DB::table('shopping_list_items as sli')
            ->join('org_supplier_products as p', 'p.id', 'sli.org_supplier_product_id')
            ->where('p.org_supplier_id', $orgSupplier->id)
            ->where('sli.state', ShoppingListItemStateEnum::OPEN->value)
            ->whereNull('sli.deleted_at');

        $priorityBreakdown = (clone $openItems)
            ->selectRaw('sli.priority, count(*) as total')
            ->groupBy('sli.priority')
            ->pluck('total', 'priority');

        return [
            'cover'                 => GetSupplierStockCoverBuckets::run($orgSupplier),
            'order_capacity'        => $orderCapacity,
            'late_purchase_orders'  => $this->latePurchaseOrders($orgSupplier),
            'open_stock_deliveries' => $this->openStockDeliveries($orgSupplier),
            'open_items_count'      => $orderCapacity['list']['lines'],
            'oldest_item_at'        => (clone $openItems)->min('sli.created_at'),
            'estimated_total'       => $orderCapacity['list']['value'],
            'priority_breakdown'    => collect(ShoppingListItemPriorityEnum::cases())->map(fn ($priority) => [
                'priority' => $priority->value,
                'label'    => ShoppingListItemPriorityEnum::labels()[$priority->value],
                'count'    => $priorityBreakdown[$priority->value] ?? 0,
            ])->values(),
        ];
    }

    public function asController(Organisation $organisation, OrgSupplier $orgSupplier, ActionRequest $request): array
    {
        abort_if($orgSupplier->org_agent_id, 404);

        $this->orgSupplier = $orgSupplier;
        $this->initialisation($organisation, $request);

        return $this->handle($orgSupplier);
    }

    public function htmlResponse(array $data, ActionRequest $request): Response
    {
        $routeParameters = [$this->orgSupplier->organisation->slug, $this->orgSupplier->slug];

        return Inertia::render(
            'Procurement/SupplierShoppingDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs($this->orgSupplier, $request->route()->originalParameters()),
                'title'       => __('Shopping'),
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-shopping-basket'],
                        'title' => __('Shopping'),
                    ],
                    'model'         => $this->orgSupplier->supplier->name,
                    'title'         => __('Shopping'),
                    'subNavigation' => $this->getOrgSupplierNavigation($this->orgSupplier),
                ],
                'orgSupplier' => [
                    'id'       => $this->orgSupplier->id,
                    'slug'     => $this->orgSupplier->slug,
                    'name'     => $this->orgSupplier->supplier->name,
                    'currency' => $this->orgSupplier->supplier->currency->code,
                ],
                'productsRoute' => [
                    'name'       => 'grp.org.procurement.org_suppliers.show.supplier_products.index',
                    'parameters' => $routeParameters,
                ],
                'shoppingListRoute' => [
                    'name'       => 'grp.org.procurement.org_suppliers.show.shopping_list.index',
                    'parameters' => $routeParameters,
                ],
                'stockDeliveriesRoute' => [
                    'name'       => 'grp.org.procurement.org_suppliers.show.stock_deliveries.index',
                    'parameters' => $routeParameters,
                ],
                'stats'               => [
                    'open_items_count'   => $data['open_items_count'],
                    'oldest_item_at'     => $data['oldest_item_at'],
                    'estimated_total'    => $data['estimated_total'],
                    'priority_breakdown' => $data['priority_breakdown'],
                ],
                'coverBuckets'        => $data['cover']['buckets'],
                'coverTotal'          => $data['cover']['total'],
                'leadTime'            => $data['cover']['lead_time'],
                'orderCapacity'       => $data['order_capacity'],
                'latePurchaseOrders'  => $data['late_purchase_orders'],
                'openStockDeliveries' => $data['open_stock_deliveries'],
            ]
        );
    }

    public function getBreadcrumbs(OrgSupplier $orgSupplier, array $routeParameters): array
    {
        return array_merge(
            ShowOrgSupplier::make()->getBreadcrumbs('grp.org.procurement.org_suppliers.show', $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.procurement.org_suppliers.show.shopping.dashboard',
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
