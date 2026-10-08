<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\OrgAgent;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetAgentSupplierPerformance
{
    use AsObject;

    public const OPEN_STATES = [
        PurchaseOrderStateEnum::SUBMITTED->value,
        PurchaseOrderStateEnum::CONFIRMED->value,
    ];

    public const CLOSED_DELIVERY_STATES = [
        PurchaseOrderDeliveryStateEnum::RECEIVED->value,
        PurchaseOrderDeliveryStateEnum::CHECKED->value,
        PurchaseOrderDeliveryStateEnum::PLACED->value,
        PurchaseOrderDeliveryStateEnum::CANCELLED->value,
        PurchaseOrderDeliveryStateEnum::NOT_RECEIVED->value,
    ];

    public const OPEN_DELIVERY_STATES = [
        StockDeliveryStateEnum::IN_PROCESS->value,
        StockDeliveryStateEnum::CONFIRMED->value,
        StockDeliveryStateEnum::READY_TO_SHIP->value,
        StockDeliveryStateEnum::DISPATCHED->value,
        StockDeliveryStateEnum::RECEIVED->value,
        StockDeliveryStateEnum::CHECKED->value,
        StockDeliveryStateEnum::BOOKING_IN->value,
    ];

    /**
     * The agent is one counterparty but many clocks. This is the per-sub-supplier answer to
     * "who is holding us up": their own lead time, their open orders through the agent, and how
     * late the worst of them is.
     *
     * @return array<int, array<string, mixed>>
     */
    public function handle(OrgAgent $orgAgent): array
    {
        $leadTimes = collect(GetAgentLeadTimes::run($orgAgent)['suppliers'])->keyBy('supplier_id');

        $orders     = $this->openOrders($orgAgent);
        $deliveries = $this->openDeliveries($orgAgent);
        $listLines  = $this->openListLines($orgAgent);

        return $leadTimes->map(function (array $leadTime) use ($orders, $deliveries, $listLines) {
            $order = $orders->get($leadTime['supplier_id']);

            return [
                ...$leadTime,
                'open_orders'      => (int) ($order->open_orders ?? 0),
                'late_orders'      => (int) ($order->late_orders ?? 0),
                'worst_days_late'  => $order?->worst_days_late !== null ? (int) $order->worst_days_late : null,
                'no_eta_orders'    => (int) ($order->no_eta_orders ?? 0),
                'open_deliveries'  => (int) ($deliveries->get($leadTime['supplier_id'])->total ?? 0),
                'list_lines'       => (int) ($listLines->get($leadTime['supplier_id'])->total ?? 0),
            ];
        })->sortByDesc(fn (array $supplier) => [$supplier['worst_days_late'] ?? -1, $supplier['open_orders']])
            ->values()
            ->all();
    }

    private function openOrders(OrgAgent $orgAgent)
    {
        return DB::table('purchase_orders as po')
            ->where('po.organisation_id', $orgAgent->organisation_id)
            ->where('po.agent_id', $orgAgent->agent_id)
            ->where('po.parent_type', 'OrgSupplier')
            ->whereNull('po.deleted_at')
            ->whereIn('po.state', self::OPEN_STATES)
            ->whereNotIn('po.delivery_state', self::CLOSED_DELIVERY_STATES)
            ->whereRaw("(po.data -> 'housekeeping') is null")
            ->groupBy('po.supplier_id')
            ->selectRaw("po.supplier_id,
                count(*) as open_orders,
                count(*) filter (where coalesce(po.estimated_received_at, po.submitted_at) < now()) as late_orders,
                count(*) filter (where po.estimated_received_at is null) as no_eta_orders,
                max(extract(day from now() - coalesce(po.estimated_received_at, po.submitted_at))::int)
                    filter (where coalesce(po.estimated_received_at, po.submitted_at) < now()) as worst_days_late")
            ->get()
            ->keyBy('supplier_id');
    }

    private function openDeliveries(OrgAgent $orgAgent)
    {
        return DB::table('stock_deliveries')
            ->where('organisation_id', $orgAgent->organisation_id)
            ->where('agent_id', $orgAgent->agent_id)
            ->whereIn('state', self::OPEN_DELIVERY_STATES)
            ->whereNull('deleted_at')
            ->groupBy('supplier_id')
            ->selectRaw('supplier_id, count(*) as total')
            ->get()
            ->keyBy('supplier_id');
    }

    private function openListLines(OrgAgent $orgAgent)
    {
        return DB::table('shopping_list_items')
            ->where('organisation_id', $orgAgent->organisation_id)
            ->where('agent_id', $orgAgent->agent_id)
            ->where('state', ShoppingListItemStateEnum::OPEN->value)
            ->whereNull('deleted_at')
            ->groupBy('supplier_id')
            ->selectRaw('supplier_id, count(*) as total')
            ->get()
            ->keyBy('supplier_id');
    }
}
