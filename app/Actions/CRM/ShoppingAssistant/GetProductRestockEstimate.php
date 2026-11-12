<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Models\Catalogue\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * When an out of stock product is expected back, from what is on order for its stock. A date is
 * only given when it is reliable: a stock delivery on its way with an expected receiving date still
 * ahead, or an open purchase order line submitted in the last RECENT_MONTHS months whose order has
 * an expected arrival still ahead. On 12 Nov 2026 only 30-60% of recent open orders had a date,
 * hundreds of orders from 2021-2023 were still open and a third of the dates were already past, so
 * anything else is "on order, no reliable date" or "not on order", and the customer is offered an
 * answer from the purchasing team instead.
 *
 * A product made of several stocks is expected when the last of them arrives; if one of them has
 * no reliable date, the product has none.
 *
 * @return array{status: 'expected'|'on_order_no_date'|'not_on_order', date: ?CarbonImmutable}
 */
class GetProductRestockEstimate
{
    use AsAction;

    public const int RECENT_MONTHS = 9;

    private const array OPEN_ORDER_STATES = ['submitted', 'confirmed'];

    private const array ON_ITS_WAY_DELIVERY_STATES = ['confirmed', 'ready_to_ship', 'dispatched'];

    private const array FINISHED_LINE_DELIVERY_STATES = ['received', 'checked', 'settled', 'placed', 'cancelled', 'not_received'];

    public function handle(Product $product): array
    {
        $orgStockIds = DB::table('product_has_org_stocks')->where('product_id', $product->id)->pluck('org_stock_id');
        if ($orgStockIds->isEmpty()) {
            return ['status' => 'not_on_order', 'date' => null];
        }

        $today    = now()->startOfDay();
        $dates    = [];
        $onOrder  = false;

        foreach ($orgStockIds as $orgStockId) {
            $delivery = DB::table('stock_delivery_items')
                ->join('stock_deliveries', 'stock_deliveries.id', '=', 'stock_delivery_items.stock_delivery_id')
                ->where('stock_delivery_items.org_stock_id', $orgStockId)
                ->whereIn('stock_deliveries.state', self::ON_ITS_WAY_DELIVERY_STATES)
                ->whereNull('stock_deliveries.deleted_at')
                ->selectRaw("min(nullif(stock_deliveries.data->>'estimated_receiving_date', '')::date) filter (where nullif(stock_deliveries.data->>'estimated_receiving_date', '')::date >= ?) as expected, count(*) as lines", [$today->toDateString()])
                ->first();

            $order = DB::table('purchase_order_transactions')
                ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_transactions.purchase_order_id')
                ->where('purchase_order_transactions.org_stock_id', $orgStockId)
                ->whereIn('purchase_orders.state', self::OPEN_ORDER_STATES)
                ->where('purchase_orders.submitted_at', '>=', now()->subMonths(self::RECENT_MONTHS))
                ->whereNull('purchase_orders.deleted_at')
                ->where(fn ($query) => $query->whereNull('purchase_order_transactions.delivery_state')->orWhereNotIn('purchase_order_transactions.delivery_state', self::FINISHED_LINE_DELIVERY_STATES))
                ->selectRaw('min(purchase_orders.estimated_received_at::date) filter (where purchase_orders.estimated_received_at >= ?) as expected, count(*) as lines', [$today])
                ->first();

            $onOrder = $onOrder || (int) $delivery->lines > 0 || (int) $order->lines > 0;
            $expected = collect([$delivery->expected, $order->expected])->filter()->min();

            if (!$expected) {
                return ['status' => $onOrder ? 'on_order_no_date' : 'not_on_order', 'date' => null];
            }

            $dates[] = CarbonImmutable::parse($expected);
        }

        return ['status' => 'expected', 'date' => collect($dates)->max()];
    }
}
