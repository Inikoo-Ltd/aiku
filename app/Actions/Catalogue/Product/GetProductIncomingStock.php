<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Sept 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Catalogue\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What is still on its way to the warehouse for a product's org stocks, and when it should land.
 *
 * Goods already turned into a stock delivery are counted there; a purchase order is only
 * counted while no live stock delivery exists for it, so nothing is listed twice.
 */
class GetProductIncomingStock
{
    use AsObject;

    /**
     * ponytail: days-to-arrive per delivery state is a constant table; swap for measured
     * per-supplier transit times once stock deliveries carry their own expected date
     */
    private const array DAYS_TO_ARRIVE = [
        StockDeliveryStateEnum::CONFIRMED->value     => 21,
        StockDeliveryStateEnum::READY_TO_SHIP->value => 14,
        StockDeliveryStateEnum::DISPATCHED->value    => 7,
        StockDeliveryStateEnum::RECEIVED->value      => 3,
        StockDeliveryStateEnum::CHECKED->value       => 2,
        StockDeliveryStateEnum::BOOKING_IN->value    => 1,
    ];

    private const int DEFAULT_LEAD_TIME_DAYS = 14;

    /**
     * @return array<int, array{type: string, reference: string, slug: string, org_stock_code: string, org_stock_name: string, state: string, state_label: string, quantity: float, eta: string|null, organisation_slug: string}>
     */
    public function handle(Product $product): array
    {
        $orgStockIds = $product->orgStocks->pluck('id')->all();

        if (!$orgStockIds) {
            return [];
        }

        $lines = array_merge(
            $this->stockDeliveryLines($orgStockIds),
            $this->purchaseOrderLines($orgStockIds)
        );

        usort($lines, fn ($a, $b) => [$a['eta'] === null, $a['eta']] <=> [$b['eta'] === null, $b['eta']]);

        return $lines;
    }

    /**
     * The earliest date any of it should be on the shelf, or null when nothing is coming.
     */
    public function earliestEta(Product $product): ?string
    {
        foreach ($this->handle($product) as $line) {
            if ($line['eta']) {
                return $line['eta'];
            }
        }

        return null;
    }

    /**
     * @param  array<int, int> $orgStockIds
     * @return array<int, array<string, mixed>>
     */
    private function stockDeliveryLines(array $orgStockIds): array
    {
        return DB::table('stock_delivery_items')
            ->join('stock_deliveries', 'stock_deliveries.id', 'stock_delivery_items.stock_delivery_id')
            ->join('org_stocks', 'org_stocks.id', 'stock_delivery_items.org_stock_id')
            ->join('organisations', 'organisations.id', 'stock_deliveries.organisation_id')
            ->whereIn('stock_delivery_items.org_stock_id', $orgStockIds)
            ->whereNull('stock_delivery_items.deleted_at')
            ->whereNull('stock_deliveries.deleted_at')
            ->whereIn('stock_deliveries.state', array_keys(self::DAYS_TO_ARRIVE))
            ->select([
                'stock_deliveries.reference',
                'stock_deliveries.slug',
                'stock_deliveries.state',
                'stock_deliveries.dispatched_at',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'organisations.slug as organisation_slug',
                DB::raw('(stock_delivery_items.unit_quantity - coalesce(stock_delivery_items.unit_quantity_placed, 0)) as quantity'),
            ])
            ->get()
            ->filter(fn ($row) => $row->quantity > 0)
            ->map(fn ($row) => [
                'type'              => 'stock_delivery',
                'reference'         => $row->reference,
                'slug'              => $row->slug,
                'org_stock_code'    => $row->org_stock_code,
                'org_stock_name'    => $row->org_stock_name,
                'state'             => $row->state,
                'state_label'       => StockDeliveryStateEnum::labels()[$row->state],
                'quantity'          => (float) $row->quantity,
                'eta'               => now()->addDays(self::DAYS_TO_ARRIVE[$row->state])->toDateString(),
                'organisation_slug' => $row->organisation_slug,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int> $orgStockIds
     * @return array<int, array<string, mixed>>
     */
    private function purchaseOrderLines(array $orgStockIds): array
    {
        return DB::table('purchase_order_transactions')
            ->join('purchase_orders', 'purchase_orders.id', 'purchase_order_transactions.purchase_order_id')
            ->join('org_stocks', 'org_stocks.id', 'purchase_order_transactions.org_stock_id')
            ->join('organisations', 'organisations.id', 'purchase_orders.organisation_id')
            ->whereIn('purchase_order_transactions.org_stock_id', $orgStockIds)
            ->whereNull('purchase_order_transactions.deleted_at')
            ->whereNull('purchase_orders.deleted_at')
            ->whereNotIn('purchase_orders.state', [
                PurchaseOrderStateEnum::IN_PROCESS->value,
                PurchaseOrderStateEnum::CANCELLED->value,
                PurchaseOrderStateEnum::NOT_RECEIVED->value,
            ])
            ->whereNotIn('purchase_orders.delivery_state', [
                PurchaseOrderDeliveryStateEnum::PLACED->value,
                PurchaseOrderDeliveryStateEnum::CANCELLED->value,
                PurchaseOrderDeliveryStateEnum::NOT_RECEIVED->value,
            ])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('purchase_order_stock_delivery')
                    ->join('stock_deliveries', 'stock_deliveries.id', 'purchase_order_stock_delivery.stock_delivery_id')
                    ->whereColumn('purchase_order_stock_delivery.purchase_order_id', 'purchase_orders.id')
                    ->whereNull('stock_deliveries.deleted_at')
                    ->whereNotIn('stock_deliveries.state', [
                        StockDeliveryStateEnum::CANCELLED->value,
                        StockDeliveryStateEnum::NOT_RECEIVED->value,
                    ]);
            })
            ->select([
                'purchase_orders.reference',
                'purchase_orders.slug',
                'purchase_orders.delivery_state',
                'purchase_orders.submitted_at',
                'purchase_orders.estimated_received_at',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'org_stocks.measured_lead_time_days',
                'organisations.slug as organisation_slug',
                DB::raw('(coalesce(purchase_order_transactions.quantity_ordered, 0) - coalesce(purchase_order_transactions.quantity_cancelled, 0)) as quantity'),
            ])
            ->get()
            ->filter(fn ($row) => $row->quantity > 0)
            ->map(fn ($row) => [
                'type'              => 'purchase_order',
                'reference'         => $row->reference,
                'slug'              => $row->slug,
                'org_stock_code'    => $row->org_stock_code,
                'org_stock_name'    => $row->org_stock_name,
                'state'             => $row->delivery_state,
                'state_label'       => PurchaseOrderDeliveryStateEnum::labels()[$row->delivery_state],
                'quantity'          => (float) $row->quantity,
                'eta'               => $this->purchaseOrderEta($row),
                'organisation_slug' => $row->organisation_slug,
            ])
            ->values()
            ->all();
    }

    /**
     * The date the office typed in, otherwise the submission date plus the lead time we have
     * measured for that stock. Anything already overdue is reported as tomorrow.
     */
    private function purchaseOrderEta(object $row): ?string
    {
        $eta = $row->estimated_received_at
            ? Carbon::parse($row->estimated_received_at)
            : ($row->submitted_at
                ? Carbon::parse($row->submitted_at)->addDays((int) ($row->measured_lead_time_days ?? self::DEFAULT_LEAD_TIME_DAYS))
                : null);

        if (!$eta) {
            return null;
        }

        return $eta->max(now()->addDay())->toDateString();
    }
}
