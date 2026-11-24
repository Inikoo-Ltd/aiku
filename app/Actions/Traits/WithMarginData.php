<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Aug 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;

/**
 * Margin/profit figures for orders, invoices and delivery notes. Internal grp screens only:
 * never include any of this in PDFs, printed documents, retina or any customer-facing
 * output. canSeeMargins() must only ever be called from grp actions — constructing the
 * check on a retina request would evaluate staff permissions against a WebUser.
 *
 * Costs are in org currency. The actual cost of a line is the sum of its picking
 * movements' org_amount, written per SKU at pick time, so multi-SKU products aggregate
 * correctly for free. A line whose pickings do not yet cover the ordered quantity tops
 * the actual cost up from the estimate and is flagged estimated. Lines not picked at all
 * fall back to an estimate from the replacement cost (lpp_per_sku, the last price actually
 * paid), because a commercial margin answers "is this priced to profit if we buy it again"
 * rather than "what did the units still in the bay happen to cost".
 * A product line with no cost basis at all is excluded from summary totals and counted,
 * never treated as zero cost: silence must not read as 100% margin.
 */
trait WithMarginData
{
    public function canSeeMargins(Shop $shop, ?int $warehouseId = null): bool
    {
        $permissions = [
            "crm.{$shop->id}.view",
            "supervisor-crm.{$shop->id}",
            "supervisor-orders.{$shop->id}",
            "shop-admin.{$shop->id}",
            "org-supervisor.{$shop->organisation_id}.accounting",
            "org-admin.{$shop->organisation_id}",
            'masters.edit',
            'masters.price_edit',
        ];

        if ($warehouseId) {
            $permissions[] = "supervisor-dispatching.$warehouseId";
        }

        return (bool) request()->user()?->authTo($permissions);
    }

    protected function actualCostSql(string $transactionIdColumn): string
    {
        return "(SELECT SUM(ABS(osm.org_amount))
            FROM delivery_note_items dni
            JOIN pickings pk ON pk.delivery_note_item_id = dni.id
            JOIN org_stock_movements osm ON osm.id = pk.org_stock_movement_id
            WHERE dni.transaction_id = $transactionIdColumn AND osm.org_amount <> 0)";
    }

    protected function estimatedCostSql(string $quantityColumn): string
    {
        $replacementCost = 'COALESCE(NULLIF(os.lpp_per_sku, 0), os.sku_value)';

        return "(SELECT SUM($replacementCost * phos.quantity)
            FROM product_has_org_stocks phos
            JOIN org_stocks os ON os.id = phos.org_stock_id
            WHERE phos.product_id = products.id AND $replacementCost > 0) * $quantityColumn";
    }

    /**
     * Commercial margin of the products selling each SKO, per shop: price converted to org
     * currency against the replacement cost of all the product's SKOs. Products with no
     * price (or the 0.01 placeholder some external shops hold) or no cost basis are left out, never shown as 100%.
     * SKOs with nothing to show are missing from the result.
     *
     * @param  list<int>  $orgStockIds
     * @return array<int, array{min: float, max: float, shops: list<array{shop: string, min: float, max: float}>}>
     */
    public function getOrgStocksMargins(array $orgStockIds, Organisation $organisation, ?int $warehouseId = null): array
    {
        if (!$orgStockIds) {
            return [];
        }

        $rows = DB::table('products')
            ->join('product_has_org_stocks as this_sko', 'this_sko.product_id', '=', 'products.id')
            ->join('shops', 'shops.id', '=', 'products.shop_id')
            ->whereIn('this_sko.org_stock_id', $orgStockIds)
            ->whereIn('products.state', [ProductStateEnum::ACTIVE->value, ProductStateEnum::DISCONTINUING->value])
            ->whereNot('shops.state', ShopStateEnum::CLOSED->value)
            ->where('products.price', '>', 0.01)
            ->selectRaw('this_sko.org_stock_id, products.shop_id, products.price, '.$this->estimatedCostSql('1').' as cost')
            ->get()
            ->filter(fn ($row) => $row->cost > 0);

        $shops = Shop::whereIn('id', $rows->pluck('shop_id')->unique())->with('currency')->get()
            ->filter(fn (Shop $shop) => $this->canSeeMargins($shop, $warehouseId))
            ->keyBy('id');

        $rates = $shops->map(fn (Shop $shop) => GetCurrencyExchange::run($shop->currency, $organisation->currency));

        return $rows->whereIn('shop_id', $shops->keys())->groupBy('org_stock_id')
            ->map(function ($orgStockRows) use ($shops, $rates) {
                $perShop = $orgStockRows->groupBy('shop_id')
                    ->map(function ($shopRows, $shopId) use ($shops, $rates) {
                        $rate    = $rates[$shopId];
                        $margins = $rate ? $shopRows->map(fn ($row) => round(100 * (1 - $row->cost / ($row->price * $rate)), 1)) : collect();

                        return ['shop' => $shops[$shopId]->name, 'min' => $margins->min(), 'max' => $margins->max()];
                    })
                    ->filter(fn ($shopMargin) => $shopMargin['min'] !== null)
                    ->sortBy('shop')
                    ->values();

                return $perShop->isEmpty() ? null : ['min' => $perShop->min('min'), 'max' => $perShop->max('max'), 'shops' => $perShop->all()];
            })
            ->filter()
            ->all();
    }

    /**
     * Resolves a line's cost in org currency. Coverage-aware: an actual cost from pickings
     * that do not cover the ordered quantity is topped up from the estimate and flagged
     * estimated, so a half-picked line never shows a confident half cost.
     *
     * @return array{cost: ?float, is_estimated: bool}
     */
    protected function resolveLineCost(mixed $actualCost, mixed $estimatedCost, mixed $pickedQuantity = null, mixed $orderedQuantity = null): array
    {
        if ($actualCost === null) {
            return [
                'cost'         => $estimatedCost === null ? null : (float) $estimatedCost,
                'is_estimated' => $estimatedCost !== null,
            ];
        }

        $cost = (float) $actualCost;

        if ($pickedQuantity !== null && $orderedQuantity !== null && (float) $orderedQuantity > 0 && (float) $pickedQuantity < (float) $orderedQuantity) {
            $unpickedFraction = ((float) $orderedQuantity - (float) $pickedQuantity) / (float) $orderedQuantity;
            if ($estimatedCost !== null) {
                $cost += (float) $estimatedCost * $unpickedFraction;
            }

            return ['cost' => $cost, 'is_estimated' => true];
        }

        return ['cost' => $cost, 'is_estimated' => false];
    }

    /**
     * @return array{margin_pct: ?float, profit_amount: ?float, margin_is_estimated: bool, margin_no_cost: bool}|null
     */
    protected function marginFields(?string $modelType, mixed $netAmount, mixed $orgNetAmount, mixed $actualCost, mixed $estimatedCost, mixed $pickedQuantity = null, mixed $orderedQuantity = null): ?array
    {
        if ($modelType !== 'Product') {
            return null;
        }

        ['cost' => $cost, 'is_estimated' => $isEstimated] = $this->resolveLineCost($actualCost, $estimatedCost, $pickedQuantity, $orderedQuantity);

        if ($cost === null) {
            return [
                'margin_pct'          => null,
                'profit_amount'       => null,
                'margin_is_estimated' => false,
                'margin_no_cost'      => true,
            ];
        }

        $orgNet = (float) $orgNetAmount;
        $pct    = $orgNet != 0.0 ? round(100 * ($orgNet - $cost) / $orgNet, 1) : null;

        return [
            'margin_pct'          => $pct,
            'profit_amount'       => $pct === null ? null : round((float) $netAmount * ($orgNet - $cost) / $orgNet, 2),
            'margin_is_estimated' => $isEstimated,
            'margin_no_cost'      => false,
        ];
    }

    /**
     * Aggregates margin over the lines that have a cost basis; lines without one are
     * excluded from both revenue and cost (not treated as free) and counted so the UI can
     * warn. Returns null when nothing can be costed: no number is better than a fake one.
     *
     * @return array{profit_amount: float, margin_pct: float, before_discounts: array{margin_pct: float, profit_amount: float}|null, break_even_pct: float, is_below_break_even: bool, is_estimated: bool, lines_without_cost: int, currency_code: string}|null
     */
    public function getMarginSummary(Order|Invoice|DeliveryNote $parent): ?array
    {
        if (!$this->canSeeMargins($parent->shop, $parent instanceof DeliveryNote ? $parent->warehouse_id : null)) {
            return null;
        }

        if ($parent instanceof DeliveryNote) {
            return $this->deliveryNoteMarginSummary($parent);
        }

        $table    = $parent instanceof Order ? 'transactions' : 'invoice_transactions';
        $idColumn = $parent instanceof Order ? 'order_id' : 'invoice_id';

        $actualSql = $this->actualCostSql($parent instanceof Order ? "$table.id" : "$table.transaction_id");
        if ($parent instanceof Invoice) {
            $actualSql = "($actualSql) * $table.quantity / NULLIF(margin_transactions.quantity_ordered, 0)";
        }
        $estimatedSql = $this->estimatedCostSql($parent instanceof Order ? "$table.quantity_ordered" : "$table.quantity");

        $lines = DB::table($table)
            ->leftJoin('assets', "$table.asset_id", '=', 'assets.id')
            ->leftJoin('products', function ($join) {
                $join->on('assets.model_id', '=', 'products.id')->where('assets.model_type', 'Product');
            })
            ->when($parent instanceof Invoice, function ($q) use ($table) {
                $q->leftJoin('transactions as margin_transactions', 'margin_transactions.id', '=', "$table.transaction_id");
            })
            ->where("$table.$idColumn", $parent->id)
            ->where("$table.model_type", 'Product')
            ->whereNull("$table.deleted_at")
            ->selectRaw("
                $table.net_amount as net,
                $table.org_net_amount as org_net,
                $table.gross_amount as gross,
                $table.org_exchange as org_exchange,
                ".($parent instanceof Order ? "$table.quantity_picked as picked, $table.quantity_ordered as ordered," : 'NULL as picked, NULL as ordered,')."
                $actualSql as actual_cost,
                $estimatedSql as estimated_cost
            ")
            ->get();

        return $this->aggregateMarginLines($lines, $parent->currency->code, $this->marginBreakEvenPct($parent->organisation));
    }

    private function deliveryNoteMarginSummary(DeliveryNote $deliveryNote): ?array
    {
        if ($deliveryNote->type === DeliveryNoteTypeEnum::REPLACEMENT) {
            return null;
        }

        $lines = DB::table('delivery_note_items')
            ->join('transactions', 'transactions.id', '=', 'delivery_note_items.transaction_id')
            ->where('delivery_note_items.delivery_note_id', $deliveryNote->id)
            ->selectRaw("
                transactions.net_amount * delivery_note_items.quantity_required / NULLIF(transactions.quantity_ordered, 0) as net,
                transactions.org_net_amount * delivery_note_items.quantity_required / NULLIF(transactions.quantity_ordered, 0) as org_net,
                NULL as gross, NULL as org_exchange,
                NULL as picked, NULL as ordered,
                (SELECT SUM(ABS(osm.org_amount))
                    FROM pickings pk
                    JOIN org_stock_movements osm ON osm.id = pk.org_stock_movement_id
                    WHERE pk.delivery_note_item_id = delivery_note_items.id AND osm.org_amount <> 0) as actual_cost,
                NULL as estimated_cost
            ")
            ->get();

        return $this->aggregateMarginLines($lines, $deliveryNote->shop->currency->code, $this->marginBreakEvenPct($deliveryNote->organisation));
    }

    public function marginBreakEvenPct($organisation): float
    {
        return (float) \Illuminate\Support\Arr::get($organisation->settings ?? [], 'margins.break_even_pct', 30);
    }

    private function aggregateMarginLines(iterable $lines, string $currencyCode, float $breakEvenPct = 0.0): ?array
    {
        $net              = 0.0;
        $orgNet           = 0.0;
        $gross            = 0.0;
        $orgGross         = 0.0;
        $grossComplete    = true;
        $cost             = 0.0;
        $isEstimated      = false;
        $linesWithoutCost = 0;

        foreach ($lines as $line) {
            ['cost' => $lineCost, 'is_estimated' => $lineEstimated] = $this->resolveLineCost($line->actual_cost, $line->estimated_cost, $line->picked, $line->ordered);

            if ($lineCost === null || $line->org_net === null) {
                $linesWithoutCost++;
                continue;
            }

            $net    += (float) $line->net;
            $orgNet += (float) $line->org_net;
            $cost   += $lineCost;
            $isEstimated = $isEstimated || $lineEstimated;

            $exchange = (float) ($line->org_exchange ?? 0) ?: ((float) $line->net != 0.0 ? (float) $line->org_net / (float) $line->net : 0.0);
            if ($line->gross !== null && $exchange > 0) {
                $gross    += (float) $line->gross;
                $orgGross += (float) $line->gross * $exchange;
            } else {
                $grossComplete = false;
            }
        }

        if ($orgNet == 0.0) {
            return null;
        }

        $marginPct = round(100 * ($orgNet - $cost) / $orgNet, 1);

        $beforeDiscounts = null;
        if ($grossComplete && $orgGross > 0 && round($orgGross, 2) != round($orgNet, 2)) {
            $beforeDiscounts = [
                'margin_pct'    => round(100 * ($orgGross - $cost) / $orgGross, 1),
                'profit_amount' => round($gross * ($orgGross - $cost) / $orgGross, 2),
            ];
        }

        return [
            'profit_amount'        => round($net * ($orgNet - $cost) / $orgNet, 2),
            'margin_pct'           => $marginPct,
            'before_discounts'     => $beforeDiscounts,
            'break_even_pct'       => $breakEvenPct,
            'is_below_break_even'  => $marginPct < $breakEvenPct,
            'margin_status'        => match (true) {
                $marginPct < $breakEvenPct      => 'danger',
                $marginPct < $breakEvenPct + 10 => 'warning',
                default                         => 'ok',
            },
            'is_estimated'         => $isEstimated,
            'lines_without_cost'   => $linesWithoutCost,
            'currency_code'        => $currencyCode,
        ];
    }
}
