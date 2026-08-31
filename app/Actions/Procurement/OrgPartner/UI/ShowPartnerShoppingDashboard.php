<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 29 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\GetPartnerOrderCapacity;
use App\Actions\Procurement\OrgPartner\GetPartnerStockCoverBuckets;
use App\Actions\Procurement\OrgPartner\WithPartnerShoppingSubNavigation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemPriorityEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowPartnerShoppingDashboard extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithPartnerShoppingSubNavigation;

    private OrgPartner $orgPartner;

    /**
     * Purchase orders the partner still owes us, worst delay first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function latePurchaseOrders(OrgPartner $orgPartner): array
    {
        return DB::table('purchase_orders')
            ->where('parent_type', 'OrgPartner')
            ->where('parent_id', $orgPartner->id)
            ->whereIn('state', [PurchaseOrderStateEnum::SUBMITTED->value, PurchaseOrderStateEnum::CONFIRMED->value])
            ->whereNotIn('delivery_state', [
                PurchaseOrderDeliveryStateEnum::RECEIVED->value,
                PurchaseOrderDeliveryStateEnum::CHECKED->value,
                PurchaseOrderDeliveryStateEnum::PLACED->value,
                PurchaseOrderDeliveryStateEnum::CANCELLED->value,
                PurchaseOrderDeliveryStateEnum::NOT_RECEIVED->value,
            ])
            ->whereNull('deleted_at')
            ->selectRaw("id, slug, reference, state, delivery_state, estimated_received_at, submitted_at,
                extract(day from now() - coalesce(estimated_received_at, submitted_at))::int as days_late,
                estimated_received_at is null as no_eta")
            ->whereRaw('coalesce(estimated_received_at, submitted_at) < now()')
            ->orderByRaw('coalesce(estimated_received_at, submitted_at)')
            ->limit(20)
            ->get()
            ->map(fn ($purchaseOrder) => [
                'id'         => $purchaseOrder->id,
                'slug'       => $purchaseOrder->slug,
                'reference'  => $purchaseOrder->reference,
                'state'      => $purchaseOrder->state,
                'days_late'  => (int) $purchaseOrder->days_late,
                'no_eta'     => (bool) $purchaseOrder->no_eta,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function openStockDeliveries(OrgPartner $orgPartner): array
    {
        return DB::table('stock_deliveries')
            ->where('organisation_id', $orgPartner->organisation_id)
            ->where('partner_id', $orgPartner->partner_id)
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

    public function handle(OrgPartner $orgPartner): array
    {
        $orderCapacity  = GetPartnerOrderCapacity::run($orgPartner);
        $estimatedTotal = $orderCapacity['list']['value'];

        $priorityBreakdown = PartnerShoppingListItem::query()
            ->where('org_partner_id', $orgPartner->id)
            ->where('state', ShoppingListItemStateEnum::OPEN->value)
            ->selectRaw('priority, count(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority');


        return [
            'cover'              => GetPartnerStockCoverBuckets::run($orgPartner),
            'order_capacity'     => $orderCapacity,
            'late_purchase_orders' => $this->latePurchaseOrders($orgPartner),
            'open_stock_deliveries' => $this->openStockDeliveries($orgPartner),
            'open_items_count'   => $orgPartner->stats->number_open_shopping_list_items,
            'oldest_item_at'     => DB::table('partner_shopping_list_items')
                ->where('org_partner_id', $orgPartner->id)
                ->where('state', ShoppingListItemStateEnum::OPEN->value)
                ->whereNull('deleted_at')
                ->min('created_at'),
            'estimated_total'    => $estimatedTotal,
            'priority_breakdown' => collect(ShoppingListItemPriorityEnum::cases())->map(fn ($priority) => [
                'priority' => $priority->value,
                'label'    => ShoppingListItemPriorityEnum::labels()[$priority->value],
                'count'    => $priorityBreakdown[$priority->value] ?? 0,
            ])->values(),
        ];
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): array
    {
        $this->orgPartner = $orgPartner;
        $this->initialisation($organisation, $request);

        return $this->handle($orgPartner);
    }

    public function htmlResponse(array $data, ActionRequest $request): Response
    {
        return Inertia::render(
            'Procurement/PartnerShoppingDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs($this->orgPartner, $request->route()->originalParameters()),
                'title'       => __('Shopping'),
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-shopping-basket'],
                        'title' => __('Shopping'),
                    ],
                    'model'         => $this->orgPartner->partner->name,
                    'title'         => __('Shopping'),
                    'subNavigation' => $this->getPartnerShoppingNavigation($this->orgPartner),
                ],
                'orgPartner'  => [
                    'id'       => $this->orgPartner->id,
                    'slug'     => $this->orgPartner->partner->slug,
                    'currency' => $this->orgPartner->partner->currency->code,
                ],
                'browseRoute' => [
                    'name'       => 'grp.org.procurement.org_partners.show.browse.index',
                    'parameters' => [$this->orgPartner->organisation->slug, $this->orgPartner->id],
                ],
                'shoppingListRoute' => [
                    'name'       => 'grp.org.procurement.org_partners.show.shopping_list.index',
                    'parameters' => [$this->orgPartner->organisation->slug, $this->orgPartner->id],
                ],
                'canBrowse'         => (bool) Arr::get($this->orgPartner->partner->settings, 'procurement.shop_id'),
                'stats'             => [
                    'open_items_count'   => $data['open_items_count'],
                    'oldest_item_at'     => $data['oldest_item_at'],
                    'estimated_total'    => $data['estimated_total'],
                    'priority_breakdown' => $data['priority_breakdown'],
                ],
                'coverBuckets'      => $data['cover']['buckets'],
                'coverTotal'        => $data['cover']['total'],
                'leadTime'          => $data['cover']['lead_time'],
                'orderCapacity'     => $data['order_capacity'],
                'leadTimeRoute'     => [
                    'name'       => 'grp.org.procurement.org_partners.show.shopping.lead_time.update',
                    'parameters' => [$this->orgPartner->organisation->slug, $this->orgPartner->id],
                ],
                'latePurchaseOrders' => $data['late_purchase_orders'],
                'openStockDeliveries' => $data['open_stock_deliveries'],
                'stockDeliveriesRoute' => [
                    'name'       => 'grp.org.procurement.org_partners.show.stock-deliveries.index',
                    'parameters' => [$this->orgPartner->organisation->slug, $this->orgPartner->id],
                ],
            ]
        );
    }

    public function getBreadcrumbs(OrgPartner $orgPartner, array $routeParameters): array
    {
        return array_merge(
            ShowOrgPartner::make()->getBreadcrumbs($orgPartner, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.procurement.org_partners.show.shopping.dashboard',
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
