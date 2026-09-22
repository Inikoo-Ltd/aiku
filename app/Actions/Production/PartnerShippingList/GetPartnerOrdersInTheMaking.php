<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 18 Sep 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPartnerOrdersInTheMaking
{
    use AsObject;

    /**
     * One row per partner that has a goods out bay: what they asked for, how far it got and
     * what already sits in their bay, which is what an order can be raised for.
     *
     * Derived, never stored. A line is ready when its stock sits in the partner's bay or is
     * available on the normal shelves; the bay is used first, shelf stock goes to pre-picked
     * lines before anybody else, and a line still being made waits for its job order. This is
     * the single authority on what is ready: the block shows it, the order is created from it.
     *
     * Raising the order does not empty the bay, picking does. Until the order is picked its
     * quantities are taken off the bay and the shelves first, otherwise the same stock would be ordered twice.
     *
     * @return array<int, array<string, mixed>>
     */
    public function handle(Organisation $seller): array
    {
        $partners = OrgPartner::where('organisation_id', $seller->id)
            ->whereNotNull('goods_out_location_id')
            ->with(['partner', 'goodsOutLocation'])
            ->get()
            ->keyBy('partner_id');

        if ($partners->isEmpty()) {
            return [];
        }

        $lines = DB::table('partner_shopping_list_items')
            ->join('stocks', 'stocks.id', 'partner_shopping_list_items.stock_id')
            ->leftJoin('job_orders', 'job_orders.id', 'partner_shopping_list_items.job_order_id')
            ->where('partner_shopping_list_items.state', ShoppingListItemStateEnum::OPEN)
            ->where('partner_shopping_list_items.partner_organisation_id', $seller->id)
            ->whereIn('partner_shopping_list_items.organisation_id', $partners->keys())
            ->whereNull('partner_shopping_list_items.deleted_at')
            ->orderByRaw('partner_shopping_list_items.pre_picked_at is null')
            ->orderByRaw('partner_shopping_list_items.job_order_id is null')
            ->orderBy('partner_shopping_list_items.created_at')
            ->orderBy('partner_shopping_list_items.id')
            ->get([
                'partner_shopping_list_items.id',
                'partner_shopping_list_items.organisation_id as buyer_id',
                'partner_shopping_list_items.stock_id',
                'partner_shopping_list_items.quantity',
                'partner_shopping_list_items.pre_picked_at',
                'partner_shopping_list_items.job_order_id',
                'job_orders.reference as job_order_reference',
                'job_orders.slug as job_order_slug',
                DB::raw("(job_orders.id is not null and job_orders.state in ('in_process', 'submitted', 'confirmed')) as is_being_made"),
                'stocks.code as stock_code',
                'stocks.name as stock_name',
                DB::raw(PartnerShoppingListItem::pricePerSkoSql().' as price_per_sko'),
            ])
            ->groupBy('buyer_id');

        $awaitingPicking = DB::table('partner_shopping_list_items')
            ->join('transactions', 'transactions.id', 'partner_shopping_list_items.transaction_id')
            ->join('orders', 'orders.id', 'transactions.order_id')
            ->where('partner_shopping_list_items.state', ShoppingListItemStateEnum::ORDERED)
            ->where('partner_shopping_list_items.partner_organisation_id', $seller->id)
            ->whereIn('partner_shopping_list_items.organisation_id', $partners->keys())
            ->whereNull('partner_shopping_list_items.deleted_at')
            ->whereNull('transactions.deleted_at')
            ->whereIn('orders.state', [
                OrderStateEnum::CREATING,
                OrderStateEnum::SUBMITTED,
                OrderStateEnum::IN_WAREHOUSE,
                OrderStateEnum::HANDLING,
                OrderStateEnum::HANDLING_BLOCKED,
            ])
            ->get([
                'partner_shopping_list_items.organisation_id as buyer_id',
                'partner_shopping_list_items.stock_id',
                'partner_shopping_list_items.quantity',
                'transactions.net_amount',
                'orders.reference as order_reference',
            ])
            ->groupBy('buyer_id');

        $sellerOrgStocks = DB::table('org_stocks')
            ->where('organisation_id', $seller->id)
            ->whereIn('stock_id', $lines->flatten(1)->pluck('stock_id')->merge($awaitingPicking->flatten(1)->pluck('stock_id'))->unique())
            ->get(['id', 'stock_id', 'quantity_available'])
            ->keyBy('stock_id');

        $onTheShelves = $sellerOrgStocks->map(fn ($orgStock) => max(0, (float) $orgStock->quantity_available))->all();

        $orders    = [];
        $allocated = [];
        foreach ($partners as $buyerId => $partner) {
            $inTheBay = DB::table('location_org_stocks')
                ->where('location_id', $partner->goods_out_location_id)
                ->where('quantity', '>', 0)
                ->pluck('quantity', 'org_stock_id')
                ->map(fn ($quantity) => (float) $quantity)
                ->all();

            $quantityInTheBay = round(array_sum($inTheBay), 3);
            $ordered          = $awaitingPicking->get($buyerId, collect());
            $orderedFromBay   = 0.0;
            foreach ($ordered as $orderedLine) {
                $orgStockId = $sellerOrgStocks->get($orderedLine->stock_id)?->id;
                $fromTheBay = min((float) $orderedLine->quantity, $inTheBay[$orgStockId] ?? 0);
                if ($fromTheBay > 0) {
                    $inTheBay[$orgStockId] -= $fromTheBay;
                    $orderedFromBay        += $fromTheBay;
                }
                if (isset($onTheShelves[$orderedLine->stock_id])) {
                    $onTheShelves[$orderedLine->stock_id] = max(0, $onTheShelves[$orderedLine->stock_id] - ((float) $orderedLine->quantity - $fromTheBay));
                }
            }

            $orders[$buyerId] = [
                'org_partner_id'      => $partner->id,
                'partner_code'        => $partner->partner->code,
                'partner_name'        => $partner->partner->name,
                'location_code'       => $partner->goodsOutLocation->code,
                'location_slug'       => $partner->goodsOutLocation->slug,
                'ordered'             => ['quantity' => round((float) $ordered->sum('quantity'), 3), 'amount' => round((float) $ordered->sum('net_amount'), 2)],
                'order_references'    => $ordered->pluck('order_reference')->unique()->values()->all(),
                'quantity_in_the_bay' => round($quantityInTheBay - $orderedFromBay, 3),
            ];

            foreach ($lines->get($buyerId, []) as $line) {
                $orgStockId = $sellerOrgStocks->get($line->stock_id)?->id;
                $fromTheBay = round(min((float) $line->quantity, $inTheBay[$orgStockId] ?? 0), 3);
                if ($fromTheBay > 0) {
                    $inTheBay[$orgStockId] -= $fromTheBay;
                }
                $allocated[] = ['line' => $line, 'in_the_bay' => $fromTheBay, 'on_the_shelves' => 0.0];
            }
        }

        foreach ([true, false] as $prePickedTurn) {
            foreach ($allocated as &$allocation) {
                $line = $allocation['line'];
                if ((bool) $line->pre_picked_at !== $prePickedTurn || $line->is_being_made) {
                    continue;
                }
                $allocation['on_the_shelves']    = round(min((float) $line->quantity - $allocation['in_the_bay'], $onTheShelves[$line->stock_id] ?? 0), 3);
                $onTheShelves[$line->stock_id] = ($onTheShelves[$line->stock_id] ?? 0) - $allocation['on_the_shelves'];
            }
            unset($allocation);
        }

        $lanes = ['in_the_bay', 'on_the_shelves', 'being_made', 'requested'];
        foreach ($allocated as $allocation) {
            $line    = $allocation['line'];
            $price   = (float) $line->price_per_sko;
            $ready   = round($allocation['in_the_bay'] + $allocation['on_the_shelves'], 3);
            $waiting = round((float) $line->quantity - $ready, 3);
            $order   = &$orders[$line->buyer_id];

            foreach ($lanes as $lane) {
                $order[$lane] ??= ['quantity' => 0.0, 'amount' => 0.0];
            }

            $quantities = [
                'in_the_bay'                                    => $allocation['in_the_bay'],
                'on_the_shelves'                                => $allocation['on_the_shelves'],
                $line->is_being_made ? 'being_made' : 'requested' => $waiting,
            ];
            foreach ($quantities as $lane => $quantity) {
                $order[$lane]['quantity'] += $quantity;
                $order[$lane]['amount']   += $quantity * $price;
            }

            if ($ready > 0) {
                $order['lines'][] = ['id' => $line->id, 'quantity' => $ready];
            }
            if ($waiting > 0 && $line->is_being_made) {
                $order['job_orders'][$line->job_order_id] = ['reference' => $line->job_order_reference, 'slug' => $line->job_order_slug];
            }

            $order['items'][$line->stock_id] ??= ['code' => $line->stock_code, 'name' => $line->stock_name, 'in_the_bay' => 0.0, 'on_the_shelves' => 0.0, 'waiting' => 0.0, 'amount' => 0.0];
            $order['items'][$line->stock_id]['in_the_bay']     += $allocation['in_the_bay'];
            $order['items'][$line->stock_id]['on_the_shelves'] += $allocation['on_the_shelves'];
            $order['items'][$line->stock_id]['waiting']        += $waiting;
            $order['items'][$line->stock_id]['amount']         += $ready * $price;
            unset($order);
        }

        foreach ($orders as &$order) {
            foreach ($lanes as $lane) {
                $order[$lane] = ['quantity' => round($order[$lane]['quantity'] ?? 0, 3), 'amount' => round($order[$lane]['amount'] ?? 0, 2)];
            }
            $order['ready'] = [
                'quantity' => round($order['in_the_bay']['quantity'] + $order['on_the_shelves']['quantity'], 3),
                'amount'   => round($order['in_the_bay']['amount'] + $order['on_the_shelves']['amount'], 2),
            ];
            $order['quantity_in_the_bay_not_requested'] = round(max(0, $order['quantity_in_the_bay'] - $order['in_the_bay']['quantity']), 3);
            $order['lines']                             = $order['lines'] ?? [];
            $order['job_orders']                        = array_values($order['job_orders'] ?? []);
            $order['items']                             = collect($order['items'] ?? [])->sortByDesc('amount')->values()->all();
        }
        unset($order);

        return array_values($orders);

    }
}
