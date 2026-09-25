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
 * Goods already turned into a stock delivery are counted there; a purchase order line is only
 * counted while no stock delivery past its draft holds that same org stock, so nothing is listed
 * twice and the rest of a partly delivered order stays visible.
 *
 * The date is only ever one staff typed: the delivery's estimated receiving date, else its
 * purchase orders'. Nothing is guessed, so goods without a typed date are listed with no date.
 */
class GetProductIncomingStock
{
    use AsObject;

    private const array INCOMING_STOCK_DELIVERY_STATES = [
        StockDeliveryStateEnum::CONFIRMED,
        StockDeliveryStateEnum::READY_TO_SHIP,
        StockDeliveryStateEnum::DISPATCHED,
        StockDeliveryStateEnum::RECEIVED,
        StockDeliveryStateEnum::CHECKED,
        StockDeliveryStateEnum::BOOKING_IN,
    ];

    private const string PURCHASE_ORDER_TYPED_DATE = "coalesce(purchase_orders.estimated_received_at::date, nullif(purchase_orders.data->>'estimated_receiving_date', '')::date)";

    /**
     * @return array<int, array{type: string, reference: string, slug: string, org_stock_id: int, org_stock_code: string, org_stock_name: string, state: string, state_label: string, quantity: float, eta: string|null, organisation_slug: string}>
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
     * The earliest date each product should be back on the shelf, worked out for a whole
     * page of products at once. Products with nothing on its way are left out.
     *
     * @param  array<int, int> $productIds
     * @return array<int, string>
     */
    public function earliestEtaByProduct(array $productIds): array
    {
        if (!$productIds) {
            return [];
        }

        $orgStockIdsByProduct = DB::table('product_has_org_stocks')
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'org_stock_id'])
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->pluck('org_stock_id')->all());

        $orgStockIds = $orgStockIdsByProduct->flatten()->unique()->values()->all();

        if (!$orgStockIds) {
            return [];
        }

        $earliestEtaByOrgStock = collect(array_merge(
            $this->stockDeliveryLines($orgStockIds),
            $this->purchaseOrderLines($orgStockIds)
        ))
            ->filter(fn ($line) => $line['eta'])
            ->groupBy('org_stock_id')
            ->map(fn ($lines) => $lines->min('eta'));

        return $orgStockIdsByProduct
            ->map(fn ($productOrgStockIds) => $earliestEtaByOrgStock->only($productOrgStockIds)->min())
            ->filter()
            ->all();
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
            ->whereIn('stock_deliveries.state', self::INCOMING_STOCK_DELIVERY_STATES)
            ->select([
                'stock_deliveries.reference',
                'stock_deliveries.slug',
                'stock_deliveries.state',
                DB::raw("coalesce(
                    nullif(stock_deliveries.data->>'estimated_receiving_date', '')::date,
                    (select min(".self::PURCHASE_ORDER_TYPED_DATE.")
                        from purchase_order_stock_delivery
                        join purchase_orders on purchase_orders.id = purchase_order_stock_delivery.purchase_order_id
                        where purchase_order_stock_delivery.stock_delivery_id = stock_deliveries.id)
                ) as typed_eta"),
                'org_stocks.id as org_stock_id',
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
                'org_stock_id'      => $row->org_stock_id,
                'org_stock_code'    => $row->org_stock_code,
                'org_stock_name'    => $row->org_stock_name,
                'state'             => $row->state,
                'state_label'       => StockDeliveryStateEnum::labels()[$row->state],
                'quantity'          => (float) $row->quantity,
                'eta'               => $this->eta($row->typed_eta),
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
                    ->join('stock_delivery_items', 'stock_delivery_items.stock_delivery_id', 'stock_deliveries.id')
                    ->whereColumn('purchase_order_stock_delivery.purchase_order_id', 'purchase_orders.id')
                    ->whereColumn('stock_delivery_items.org_stock_id', 'purchase_order_transactions.org_stock_id')
                    ->whereNull('stock_delivery_items.deleted_at')
                    ->whereNull('stock_deliveries.deleted_at')
                    ->whereNotIn('stock_deliveries.state', [
                        StockDeliveryStateEnum::IN_PROCESS->value,
                        StockDeliveryStateEnum::CANCELLED->value,
                        StockDeliveryStateEnum::NOT_RECEIVED->value,
                    ]);
            })
            ->select([
                'purchase_orders.reference',
                'purchase_orders.slug',
                'purchase_orders.delivery_state',
                DB::raw(self::PURCHASE_ORDER_TYPED_DATE.' as typed_eta'),
                'org_stocks.id as org_stock_id',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'organisations.slug as organisation_slug',
                DB::raw('(coalesce(purchase_order_transactions.quantity_ordered, 0) - coalesce(purchase_order_transactions.quantity_cancelled, 0)) as quantity'),
            ])
            ->get()
            ->filter(fn ($row) => $row->quantity > 0)
            ->map(fn ($row) => [
                'type'              => 'purchase_order',
                'reference'         => $row->reference,
                'slug'              => $row->slug,
                'org_stock_id'      => $row->org_stock_id,
                'org_stock_code'    => $row->org_stock_code,
                'org_stock_name'    => $row->org_stock_name,
                'state'             => $row->delivery_state,
                'state_label'       => PurchaseOrderDeliveryStateEnum::labels()[$row->delivery_state],
                'quantity'          => (float) $row->quantity,
                'eta'               => $this->eta($row->typed_eta),
                'organisation_slug' => $row->organisation_slug,
            ])
            ->values()
            ->all();
    }

    /**
     * A typed date already past is reported as tomorrow: the goods are late, not gone.
     */
    private function eta(?string $typedEta): ?string
    {
        if (!$typedEta) {
            return null;
        }

        return Carbon::parse($typedEta)->max(now()->addDay())->toDateString();
    }
}
