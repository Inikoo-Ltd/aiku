<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Actions\Procurement\OrgPartner\GetPartnerLeadTime;
use App\Actions\Procurement\OrgPartner\GetPartnerStockCoverBuckets;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Predicts when an org stock will run out.
 *
 * The pipeline, in order:
 *  0. Use the nightly TimesFM forecast of ForecastOrgStockDemand when it is fresh and the stock
 *     was on the shelf at least 70% of the window: the expected demand of the next six weeks,
 *     already scaled to what the organisation really dispatched. It was a third more accurate per
 *     SKO than steps 1 to 3 on production dispatches. TimesFM reads empty weeks as weeks nobody
 *     wanted it, so an item that was out of stock a lot keeps step 1, which only counts the days
 *     it could be sold, as do SKOs with too little history or no forecast that night.
 *  1. Rebuild the daily demand series over the last 91 days from delivery_note_items.created_at
 *     (delivery_note_items.date is only set on Aurora-fetched rows), counting ONLY days the stock
 *     was actually on the shelf (running balance from org_stock_movements enough for one order of
 *     its biggest selling product, at most one whole SKO: a few loose units left of a pack cannot
 *     fill a wholesale order, HELP-3842). An item that
 *     was out of stock 90% of the window still gets its true selling rate.
 *  2. Pick a model for that series: Croston with the Syntetos-Boylan correction for
 *     intermittent demand (most days zero), Holt's damped-trend smoothing on weekly rates for
 *     steady movers. Both produce a demand-per-in-stock-day.
 *  3. If there is no local signal at all, borrow: the same stock in sister organisations
 *     (damped) → other stocks of the same family in this organisation (heavily damped).
 *     Neither the item's own older history nor a seasonality factor is used: both were
 *     backtested on prod dispatches (Sep 2026) and made per-SKU forecasts worse, not better.
 *  4. Convert to days of cover, plus a pessimistic bound: solve
 *     qty = days*mu + 1.28*sigma*sqrt(days) so the P90 demand path is also on record.
 */
class OrgStockHydrateOutOfStockForecast implements ShouldBeUnique
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:org-stock-out-of-stock-forecast {organisations?*} {--s|slugs=}';

    private const int WINDOW = 91;

    private const float TIMESFM_MINIMUM_IN_STOCK_SHARE = 0.7;

    private const float IN_STOCK_RATE_CAP_MULTIPLE = 2.0;

    /** @var array<int, int> */
    private array $partnerLeadTimeDays = [];

    public function __construct()
    {
        $this->model = OrgStock::class;
    }

    public function getJobUniqueId(OrgStock $orgStock): string
    {
        return $orgStock->id;
    }

    public function handle(OrgStock $orgStock): void
    {
        $quantityAvailable = (float) $orgStock->quantity_available;

        [$dailyUsage, $sigma, $source, $inStockShare] = $this->predictedDailyUsage($orgStock);
        if ($inStockShare >= self::TIMESFM_MINIMUM_IN_STOCK_SHARE && $timesFm = $this->timesFmDailyUsage($orgStock)) {
            [$dailyUsage, $sigma, $source] = $timesFm;
        }

        if ($dailyUsage !== null) {
            $dailyUsage = round($dailyUsage, 4);
        }

        $daysOfCover = null;
        $daysPessimistic = null;
        $outAt = null;
        if ($quantityAvailable <= 0) {
            $daysOfCover     = 0;
            $daysPessimistic = 0;
            $outAt           = now()->toDateString();
        } elseif ($dailyUsage > 0) {
            $daysOfCover     = round(min($quantityAvailable / $dailyUsage, 730), 1);
            $daysPessimistic = round(min($this->pessimisticDays($quantityAvailable, $dailyUsage, $sigma), $daysOfCover), 1);
            $outAt           = now()->addDays((int) $daysOfCover)->toDateString();
        }

        $orgStock->stats->update([
            'predicted_daily_usage'      => $dailyUsage,
            'days_of_cover'              => $daysOfCover,
            'days_of_cover_pessimistic'  => $daysPessimistic,
            'predicted_out_of_stock_at'  => $outAt,
            'demand_variability'         => $dailyUsage > 0 && $sigma !== null ? round($sigma / $dailyUsage, 4) : null,
            'forecast_source'            => $dailyUsage !== null ? $source : null,
            'recommended_order_quantity' => $this->recommendedOrderQuantity($orgStock, $dailyUsage, $sigma),
        ]);
    }

    /**
     * SKOs to reorder now: enough to cover the lead time plus one review period at the forecast
     * rate, plus a safety buffer sized by demand variability, minus what is on the shelf and
     * already on order (purchase orders and partner shopping list lines) — rounded up to the
     * supplier's pack size. A SKO that would run out within three lead times (the stock cover
     * buckets' danger edge) with what is held and coming orders at least a month of sales, the
     * same floor a rescue order uses.
     */
    private function recommendedOrderQuantity(OrgStock $orgStock, ?float $dailyUsage, ?float $sigma): ?float
    {
        if (!$dailyUsage) {
            return null;
        }

        $packedIn            = max(1.0, (float) $orgStock->packed_in);
        $activeSupplierProduct = $orgStock->orgSupplierProducts
            ->first(fn ($orgSupplierProduct) => $orgSupplierProduct->pivot->status)
            ?->supplierProduct;

        $leadTimeDays = $this->leadTimeDays($orgStock, $activeSupplierProduct);
        $reviewDays   = 30;

        $safety = $sigma !== null
            ? 1.28 * $sigma * sqrt($leadTimeDays)
            : 0.2 * $dailyUsage * $leadTimeDays;

        $onOrderUnits = (float) DB::table('purchase_order_transactions')
            ->where('org_stock_id', $orgStock->id)
            ->whereNull('deleted_at')
            ->whereIn('state', ['in_process', 'submitted', 'confirmed'])
            ->sum(DB::raw('coalesce(quantity_ordered, 0) - coalesce(quantity_cancelled, 0)'));

        $heldAndComing = (float) $orgStock->quantity_available
            + $onOrderUnits / $packedIn
            + $this->onPartnerShoppingLists($orgStock);

        $need = $dailyUsage * ($leadTimeDays + $reviewDays) + $safety - $heldAndComing;

        if ($heldAndComing / $dailyUsage <= 3 * $leadTimeDays) {
            $need = max($need, $dailyUsage * GetPartnerStockCoverBuckets::MINIMUM_COVER_DAYS);
        }

        if ($need <= 0) {
            return 0;
        }

        $skosPerSupplierPack = (float) $activeSupplierProduct?->units_per_pack / $packedIn;
        if ($skosPerSupplierPack > 1) {
            $need = ceil($need / $skosPerSupplierPack) * $skosPerSupplierPack;
        }

        return round($need, 3);
    }

    /**
     * Days from ordering to booked in for this SKO: its own measured history, then its active
     * supplier product, then the manufacturing hub partner that makes it, then 14 days.
     */
    private function leadTimeDays(OrgStock $orgStock, ?SupplierProduct $supplierProduct): int
    {
        $days = $orgStock->measured_lead_time_days
            ?? $orgStock->estimated_lead_time_days
            ?? $supplierProduct?->measured_lead_time_days
            ?? $supplierProduct?->estimated_lead_time_days;

        if ($days) {
            return (int) $days;
        }

        $hubPartner = OrgPartner::where('org_partners.organisation_id', $orgStock->organisation_id)
            ->join('organisations as hubs', 'hubs.id', 'org_partners.partner_id')
            ->where('hubs.is_manufacturing_hub', true)
            ->whereExists(fn ($query) => $query->from('org_stocks as hub_org_stocks')
                ->whereColumn('hub_org_stocks.organisation_id', 'org_partners.partner_id')
                ->where('hub_org_stocks.stock_id', $orgStock->stock_id)
                ->where('hub_org_stocks.state', OrgStockStateEnum::ACTIVE->value))
            ->select('org_partners.*')
            ->first();

        if (!$hubPartner) {
            return GetPartnerLeadTime::DEFAULT_DAYS;
        }

        return $this->partnerLeadTimeDays[$hubPartner->id] ??= GetPartnerLeadTime::run($hubPartner)['days'];
    }

    /**
     * SKOs already asked of a partner and not yet on a purchase order: open lines sent to the partner,
     * and lines the partner picked into an order it has not dispatched.
     */
    private function onPartnerShoppingLists(OrgStock $orgStock): float
    {
        return (float) DB::table('partner_shopping_list_items')
            ->leftJoin('transactions', 'transactions.id', 'partner_shopping_list_items.transaction_id')
            ->leftJoin('orders', 'orders.id', 'transactions.order_id')
            ->where('partner_shopping_list_items.org_stock_id', $orgStock->id)
            ->whereNull('partner_shopping_list_items.deleted_at')
            ->where(function ($query) {
                $query->where('partner_shopping_list_items.state', ShoppingListItemStateEnum::OPEN->value)
                    ->orWhere(function ($query) {
                        $query->where('partner_shopping_list_items.state', ShoppingListItemStateEnum::ORDERED->value)
                            ->whereNull('transactions.deleted_at')
                            ->whereIn('orders.state', [OrderStateEnum::CREATING->value, OrderStateEnum::SUBMITTED->value]);
                    });
            })
            ->sum('partner_shopping_list_items.quantity');
    }

    /**
     * The next six weeks of the TimesFM forecast as a daily rate and daily spread, when it was made
     * today or yesterday.
     *
     * @return array{0: float, 1: float, 2: string}|null
     */
    private function timesFmDailyUsage(OrgStock $orgStock): ?array
    {
        $forecast = $orgStock->stats->demand_forecast;
        if (!is_array($forecast['weeks'] ?? null) || !isset($forecast['from']) || Carbon::parse($forecast['from'])->lt(now()->subDay()->startOfDay())) {
            return null;
        }

        $weeks = array_slice($forecast['weeks'], 0, 6);
        $days  = count($weeks) * 7;
        if ($days === 0) {
            return null;
        }

        return [
            array_sum(array_column($weeks, 0)) / $days,
            sqrt(array_sum(array_column($weeks, 1)) / $days),
            'timesfm',
        ];
    }

    /**
     * @return array{0: float|null, 1: float|null, 2: string|null, 3: float} [demand per in-stock day, daily sigma, source, share of the window in stock]
     */
    private function predictedDailyUsage(OrgStock $orgStock): array
    {
        $from = now()->subDays(self::WINDOW)->startOfDay();

        $dispatchedByDay = DB::table('delivery_note_items')
            ->where('org_stock_id', $orgStock->id)
            ->where('quantity_dispatched', '>', 0)
            ->where('created_at', '>=', $from)
            ->selectRaw('date(created_at) as day, sum(quantity_dispatched) as dispatched')
            ->groupBy('day')
            ->pluck('dispatched', 'day');

        $series = [];
        $days   = $this->inStockDays($orgStock->id, $from, (float) $orgStock->quantity_available, $this->sellableQuantity($orgStock));
        foreach ($days as $day => $inStock) {
            if ($inStock) {
                $series[$day] = (float) ($dispatchedByDay[$day] ?? 0);
            }
        }
        $inStockShare = $days ? count($series) / count($days) : 0.0;

        if (array_sum($series) <= 0) {
            if ($rate = $this->usageFromSiblingOrganisations($orgStock)) {
                return [$rate, null, 'siblings', $inStockShare];
            }
            if ($rate = $this->usageFromFamily($orgStock)) {
                return [$rate, null, 'family', $inStockShare];
            }

            return [null, null, null, $inStockShare];
        }

        $sigma = $this->standardDeviation(array_values($series));

        $nonZeroShare = count(array_filter($series)) / count($series);
        if ($nonZeroShare < 0.3) {
            $rate   = $this->crostonSba(array_values($series));
            $source = 'croston';
        } else {
            $rate   = $this->holtDamped($series);
            $source = 'holt';
        }

        $cap = $dispatchedByDay->sum() / self::WINDOW * self::IN_STOCK_RATE_CAP_MULTIPLE;
        if ($rate > $cap) {
            $sigma = $sigma !== null ? $sigma * $cap / $rate : null;
            $rate  = $cap;
        }

        return [$rate, $sigma, $source, $inStockShare];
    }

    /**
     * Croston's method with the Syntetos-Boylan approximation: smooth the non-zero demand
     * sizes and the gaps between them separately, then correct the bias.
     *
     * @param array<int, float> $series
     */
    private function crostonSba(array $series): float
    {
        $alpha    = 0.15;
        $size     = null;
        $interval = null;
        $gap      = 1;

        foreach ($series as $demand) {
            if ($demand <= 0) {
                $gap++;
                continue;
            }
            $size     = $size === null ? $demand : $size + $alpha * ($demand - $size);
            $interval = $interval === null ? $gap : $interval + $alpha * ($gap - $interval);
            $gap      = 1;
        }

        if (!$size || !$interval) {
            return 0;
        }

        return (1 - $alpha / 2) * $size / $interval;
    }

    /**
     * Holt's damped-trend exponential smoothing on weekly in-stock rates, projected one
     * damped-trend step ahead and returned as a daily rate.
     *
     * @param array<string, float> $series day => demand
     */
    private function holtDamped(array $series): float
    {
        $weeks = [];
        foreach ($series as $day => $demand) {
            $week = Carbon::parse($day)->format('o-W');
            $weeks[$week]['sum']  = ($weeks[$week]['sum'] ?? 0) + $demand;
            $weeks[$week]['days'] = ($weeks[$week]['days'] ?? 0) + 1;
        }
        ksort($weeks);
        $rates = array_map(fn ($week) => $week['sum'] / $week['days'], array_values($weeks));

        $alpha = 0.35;
        $beta  = 0.15;
        $phi   = 0.9;

        $level = $rates[0];
        $trend = 0.0;
        foreach (array_slice($rates, 1) as $rate) {
            $previousLevel = $level;
            $level         = $alpha * $rate + (1 - $alpha) * ($level + $phi * $trend);
            $trend         = $beta * ($level - $previousLevel) + (1 - $beta) * $phi * $trend;
        }

        return max(0, $level + $phi * $trend);
    }

    /**
     * SKOs needed on the shelf to fill one order of the biggest product selling each stock, at
     * most one whole SKO, so stock only ever sold in fractions still counts while a fraction is left.
     * A stock without selling products is missing from the result and needs one whole SKO.
     *
     * @param  iterable<int>  $orgStockIds
     */
    public static function sellableQuantities(iterable $orgStockIds): Builder
    {
        return DB::table('product_has_org_stocks')
            ->join('products', 'products.id', '=', 'product_has_org_stocks.product_id')
            ->whereIn('product_has_org_stocks.org_stock_id', $orgStockIds)
            ->whereIn('products.state', [ProductStateEnum::ACTIVE->value, ProductStateEnum::DISCONTINUING->value])
            ->where('product_has_org_stocks.quantity', '>', 0)
            ->groupBy('product_has_org_stocks.org_stock_id')
            ->selectRaw('product_has_org_stocks.org_stock_id, least(1, max(product_has_org_stocks.quantity)) as sellable_quantity');
    }

    private function sellableQuantity(OrgStock $orgStock): float
    {
        return (float) (self::sellableQuantities([$orgStock->id])->first()?->sellable_quantity ?? 1);
    }

    /**
     * Which days of the window the stock was actually on the shelf, rebuilt from the
     * movements' running balance.
     *
     * @return array<string, bool> day (Y-m-d) => was in stock
     */
    private function inStockDays(int $orgStockId, Carbon $from, float $fallbackSeed, float $sellableQuantity): array
    {
        $lastRunningByDay = DB::table('org_stock_movements')
            ->where('org_stock_id', $orgStockId)
            ->where('date', '>=', $from)
            ->whereNotNull('running_quantity_org_stock')
            ->selectRaw('date(date) as day, (array_agg(running_quantity_org_stock order by date desc, id desc))[1] as balance')
            ->groupBy('day')
            ->pluck('balance', 'day')
            ->map(fn ($balance) => (float) $balance)
            ->all();

        $seed = DB::table('org_stock_movements')
            ->where('org_stock_id', $orgStockId)
            ->where('date', '<', $from)
            ->whereNotNull('running_quantity_org_stock')
            ->orderByDesc('date')->orderByDesc('id')
            ->value('running_quantity_org_stock');

        $balance = $seed !== null ? (float) $seed : $fallbackSeed;

        $days = [];
        for ($day = $from->copy(); $day->lte(now()); $day->addDay()) {
            $key = $day->toDateString();
            if (array_key_exists($key, $lastRunningByDay)) {
                $balance = $lastRunningByDay[$key];
            }
            $days[$key] = $balance > 0 && $balance >= $sellableQuantity;
        }

        return $days;
    }

    /**
     * No history here: borrow the demand of the SAME stock sold by sister organisations,
     * damped to half — their market is similar, not ours.
     */
    private function usageFromSiblingOrganisations(OrgStock $orgStock): ?float
    {
        $siblingIds = OrgStock::where('stock_id', $orgStock->stock_id)
            ->where('id', '!=', $orgStock->id)
            ->pluck('id');

        if ($siblingIds->isEmpty()) {
            return null;
        }

        $dispatched = (float) DB::table('delivery_note_items')
            ->whereIn('org_stock_id', $siblingIds)
            ->where('quantity_dispatched', '>', 0)
            ->where('created_at', '>=', now()->subDays(self::WINDOW))
            ->sum('quantity_dispatched');

        if ($dispatched <= 0) {
            return null;
        }

        return round($dispatched / self::WINDOW / $siblingIds->count() * 0.5, 4);
    }

    /**
     * Still nothing: use the average per-SKU demand of the same stock family in this
     * organisation, heavily damped — related products share a customer, not a demand curve.
     */
    private function usageFromFamily(OrgStock $orgStock): ?float
    {
        if (!$orgStock->orgStockFamily) {
            return null;
        }

        $familyStockIds = OrgStock::where('org_stock_family_id', $orgStock->org_stock_family_id)
            ->where('id', '!=', $orgStock->id)
            ->pluck('id');

        if ($familyStockIds->isEmpty()) {
            return null;
        }

        $dispatched = (float) DB::table('delivery_note_items')
            ->whereIn('org_stock_id', $familyStockIds)
            ->where('quantity_dispatched', '>', 0)
            ->where('created_at', '>=', now()->subDays(self::WINDOW))
            ->sum('quantity_dispatched');

        if ($dispatched <= 0) {
            return null;
        }

        return round($dispatched / self::WINDOW / $familyStockIds->count() * 0.25, 4);
    }

    /**
     * Days until the P90 demand path empties the shelf: solve qty = d*mu + z*sigma*sqrt(d).
     */
    private function pessimisticDays(float $quantity, float $mu, ?float $sigma): float
    {
        if (!$sigma) {
            return min($quantity / $mu, 730);
        }

        $z    = 1.28;
        $sqrt = (-$z * $sigma + sqrt($z * $z * $sigma * $sigma + 4 * $mu * $quantity)) / (2 * $mu);

        return min(max($sqrt * $sqrt, 0), 730);
    }

    /**
     * @param array<int, float> $values
     */
    private function standardDeviation(array $values): ?float
    {
        $count = count($values);
        if ($count < 7) {
            return null;
        }

        $mean     = array_sum($values) / $count;
        $variance = array_sum(array_map(fn ($value) => ($value - $mean) ** 2, $values)) / $count;

        return sqrt($variance);
    }
}
