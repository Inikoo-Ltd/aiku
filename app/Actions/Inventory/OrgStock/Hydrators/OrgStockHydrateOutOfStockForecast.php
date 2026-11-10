<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Actions\Procurement\GetOrganisationStockCoverBuckets;
use App\Actions\Procurement\OrgAgent\GetAgentLeadTimes;
use App\Actions\Procurement\OrgPartner\GetPartnerLeadTime;
use App\Actions\Procurement\OrgPartner\GetPartnerStockCoverBuckets;
use App\Actions\Procurement\OrgSupplier\GetSupplierLeadTime;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementFlowEnum;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
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
 *     (delivery_note_items.date is only set on Aurora-fetched rows) plus what production and
 *     consumption took out (a hub's components leave that way, not on delivery notes), each day
 *     far above the stock's usual selling day of the last year cut down to three times the median
 *     one, so a pallet order or a big production run is not a level of demand. Count ONLY days the stock
 *     was actually on the shelf (running balance from org_stock_movements enough for one order of
 *     its biggest selling product, at most one whole SKO: a few loose units left of a pack cannot
 *     fill a wholesale order, HELP-3842). An item that
 *     was out of stock 90% of the window still gets its true selling rate.
 *  2. Pick a model for that series: Croston with the Syntetos-Boylan correction for
 *     intermittent demand (most days zero), Holt's damped-trend smoothing on weekly rates for
 *     steady movers. Both produce a demand-per-in-stock-day.
 *  3. A stock on the shelf four weeks of the window without selling has no demand. If there is
 *     no local signal at all, borrow: the same stock in sister organisations (damped, customer
 *     sales only) → the median stock of the same family in this organisation (heavily damped).
 *     Neither the item's own older history nor a seasonality factor is blended in: both were
 *     backtested on prod dispatches (Sep 2026) and made per-SKU forecasts worse, not better.
 *     The exception is an item that was hardly on the shelf all window: there is no local
 *     signal to blend with, so its own in-stock selling rate over the year before it ran out comes
 *     before borrowing, or an item that sold steadily until it ran out reads as barely wanted
 *     (HELP-4067), however long ago it ran out within two years (HELP-4505). Partner organisations
 *     do not count there, and an item that had not sold for six months when it ran out (left
 *     unsold on the shelf, then counted to zero) does not use it. The same own-year rate is the floor of an item on the shelf less than 70% of the
 *     window: the weeks it was running down sell little, and the model read that tail as the
 *     demand, so an item that ran out after selling 3 a day forecast under 1 a day (HELP-4093).
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

    private const int CAP_YEAR_DAYS = 365;

    private const int MINIMUM_IN_STOCK_DAYS = 28;

    private const int CONSUMPTION_RECENT_DAYS = 28;

    private const int MINIMUM_SELLING_DAYS = 3;

    private const int HISTORY_LAST_SALE_WITHIN_DAYS = 180;

    private const int HISTORY_LOOKBACK_DAYS = 730;

    private const float ONE_OFF_MEDIAN_MULTIPLE = 3.0;

    /** @var array<int, int> */
    private array $partnerLeadTimeDays = [];

    /** @var array<int, int> */
    private array $agentLeadTimeDays = [];

    /** @var array<int, int> */
    private array $orgSupplierLeadTimeDays = [];

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
     * rate, plus a safety buffer sized by demand variability, minus what is on the shelf, what is
     * coming (the cover buckets' coming SKOs: deliveries still to put away and submitted purchase
     * orders not on a delivery yet), draft purchase orders and partner shopping list lines — rounded up to the
     * supplier's pack size. A SKO that would run out within three lead times (the stock cover
     * buckets' danger edge) with what is held and coming orders at least a month of sales, the
     * same floor a rescue order uses, unless it needs nothing yet and that month would take it past
     * its overstock edge.
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

        $comingSkos = (float) DB::table('org_stocks')
            ->where('id', $orgStock->id)
            ->selectRaw(GetOrganisationStockCoverBuckets::comingSkosExpression('org_stocks').' as coming')
            ->value('coming');

        $onDraftUnits = (float) DB::table('purchase_order_transactions')
            ->join('purchase_orders', 'purchase_orders.id', 'purchase_order_transactions.purchase_order_id')
            ->where('purchase_order_transactions.org_stock_id', $orgStock->id)
            ->whereNull('purchase_order_transactions.deleted_at')
            ->whereNull('purchase_orders.deleted_at')
            ->where('purchase_orders.state', PurchaseOrderStateEnum::IN_PROCESS->value)
            ->sum(DB::raw('coalesce(purchase_order_transactions.quantity_ordered, 0) - coalesce(purchase_order_transactions.quantity_cancelled, 0)'));

        $heldAndComing = (float) $orgStock->quantity_available
            + $comingSkos
            + $onDraftUnits / $packedIn
            + $this->onPartnerShoppingLists($orgStock);

        $need = $dailyUsage * ($leadTimeDays + $reviewDays) + $safety - $heldAndComing;

        $coverDays     = $heldAndComing / $dailyUsage;
        $overstockDays = (int) (Arr::get($orgStock->stock?->stockFamily?->data ?? [], 'stock_cover.overstock_days') ?: GetOrganisationStockCoverBuckets::EXCESS_DAYS);

        if ($coverDays <= 3 * $leadTimeDays && ($need > 0 || $coverDays + GetPartnerStockCoverBuckets::MINIMUM_COVER_DAYS <= $overstockDays)) {
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
     * supplier product, then the agent it is bought through or its supplier (the lead time the
     * buying pages show), then the manufacturing hub partner that makes it, then 14 days.
     */
    private function leadTimeDays(OrgStock $orgStock, ?SupplierProduct $supplierProduct): int
    {
        $days = $orgStock->measured_lead_time_days
            ?? $orgStock->estimated_lead_time_days
            ?? $supplierProduct?->measured_lead_time_days
            ?? $supplierProduct?->estimated_lead_time_days
            ?? $this->buyingLeadTimeDays($orgStock, $supplierProduct);

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

    private function buyingLeadTimeDays(OrgStock $orgStock, ?SupplierProduct $supplierProduct): ?int
    {
        if (!$supplierProduct) {
            return null;
        }

        $agentId = $supplierProduct->supplier?->agent_id;
        if ($agentId && $orgAgent = OrgAgent::where('organisation_id', $orgStock->organisation_id)->where('agent_id', $agentId)->first()) {
            return $this->agentLeadTimeDays[$orgAgent->id] ??= GetAgentLeadTimes::run($orgAgent)['agent']['days'];
        }

        $orgSupplier = OrgSupplier::where('organisation_id', $orgStock->organisation_id)->where('supplier_id', $supplierProduct->supplier_id)->first();

        return $orgSupplier ? ($this->orgSupplierLeadTimeDays[$orgSupplier->id] ??= GetSupplierLeadTime::run($orgSupplier)['days']) : null;
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
        $consumption = $this->consumptionPerDay($orgStock);
        [$rate, $sigma, $source, $inStockShare] = $this->salesPerDay($orgStock, mayBorrow: $consumption <= 0);

        if ($consumption > 0) {
            return [($rate ?? 0.0) + $consumption, $sigma, $source ?? 'consumption', $inStockShare];
        }

        return [$rate, $sigma, $source, $inStockShare];
    }

    /**
     * What production and consumption take out per calendar day, net of returns: the lower of the
     * last four weeks and the window, so a kit no longer made is not read as steady use.
     * Components go in batches as soon as they arrive and are rarely on the shelf, so a rate per
     * in-stock day would read every batch as a sold-out shelf. Backtested on production data
     * (forecast on 20 Aug 2026, checked against the 42 days after): better than the window alone,
     * the year or their mean in every organisation; adding the year was barely better overall and
     * would hold a new component to a year it was not used for.
     */
    private function consumptionPerDay(OrgStock $orgStock): float
    {
        $recently = now()->subDays(self::CONSUMPTION_RECENT_DAYS)->startOfDay();
        $window   = now()->subDays(self::WINDOW)->startOfDay();

        $consumed = DB::table('org_stock_movements')
            ->where('org_stock_id', $orgStock->id)
            ->where('date', '>=', $window)
            ->where(fn ($query) => $query->whereIn('type', [OrgStockMovementTypeEnum::CONSUMPTION->value, OrgStockMovementTypeEnum::RETURN_CONSUMPTION->value])
                ->orWhere(fn ($query) => $query->where('type', OrgStockMovementTypeEnum::PRODUCTION->value)->where('flow', OrgStockMovementFlowEnum::OUT->value)))
            ->selectRaw('-coalesce(sum(quantity) filter (where date >= ?), 0) as recently, -coalesce(sum(quantity), 0) as in_window', [$recently])
            ->first();

        return max(0.0, min((float) $consumed->recently / self::CONSUMPTION_RECENT_DAYS, (float) $consumed->in_window / self::WINDOW));
    }

    /**
     * @return array{0: float|null, 1: float|null, 2: string|null, 3: float} [sales per in-stock day, daily sigma, source, share of the window in stock]
     */
    private function salesPerDay(OrgStock $orgStock, bool $mayBorrow): array
    {
        $windowFrom = now()->subDays(self::WINDOW)->startOfDay();
        $yearUsage  = $this->withoutOneOffs($this->dispatchedByDay([$orgStock->id], now()->subDays(self::CAP_YEAR_DAYS)->startOfDay())[$orgStock->id] ?? []);
        $windowUsage = array_filter($yearUsage, fn (string $day) => $day >= $windowFrom->toDateString(), ARRAY_FILTER_USE_KEY);

        [$series, $dayCount] = $this->inStockSeries($orgStock, $windowFrom, $windowUsage);
        $inStockShare = $dayCount ? count($series) / $dayCount : 0.0;

        if (array_sum($series) <= 0) {
            if (count($series) >= self::MINIMUM_IN_STOCK_DAYS || !$mayBorrow) {
                return [null, null, null, $inStockShare];
            }
            if ($usage = $this->usageFromOwnYear($orgStock)) {
                return [...$usage, 'history', $inStockShare];
            }
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

        if ($inStockShare < self::TIMESFM_MINIMUM_IN_STOCK_SHARE && ($usage = $this->usageFromOwnYear($orgStock)) && $usage[0] > $rate) {
            [$rate, $sigma] = $usage;
            $source         = 'history';
        }

        $cap = max(array_sum($windowUsage) / self::WINDOW, array_sum($yearUsage) / self::CAP_YEAR_DAYS) * self::IN_STOCK_RATE_CAP_MULTIPLE;
        if ($rate > $cap) {
            $sigma = $sigma !== null ? $sigma * $cap / $rate : null;
            $rate  = $cap;
        }

        return [$rate, $sigma, $source, $inStockShare];
    }

    /**
     * What was dispatched from each stock each day since $from, optionally leaving out what went
     * to partner organisations.
     *
     * @param  array<int, int>  $orgStockIds
     *
     * @return array<int, array<string, float>> org stock id => day => SKOs dispatched
     */
    private function dispatchedByDay(array $orgStockIds, Carbon $from, bool $withoutPartners = false): array
    {
        $dispatched = [];
        DB::table('delivery_note_items')
            ->whereIn('org_stock_id', $orgStockIds)
            ->where('quantity_dispatched', '>', 0)
            ->where('created_at', '>=', $from)
            ->when($withoutPartners, fn ($query) => $query->whereNotExists($this->toPartnerOrganisation(...)))
            ->selectRaw('org_stock_id, date(created_at)::text as day, sum(quantity_dispatched)::float as dispatched')
            ->groupBy('org_stock_id', 'day')
            ->get()
            ->each(function ($row) use (&$dispatched) {
                $dispatched[$row->org_stock_id][$row->day] = (float) $row->dispatched;
            });

        return $dispatched;
    }

    /**
     * A day far above the stock's usual selling day over the period (a pallet order, a partner
     * restocking, one big production run) is cut down to three times the median selling day, so
     * one order does not read as a level of demand. Under three selling days there is no usual day.
     *
     * @param  array<string, float>  $usage
     *
     * @return array<string, float>
     */
    private function withoutOneOffs(array $usage): array
    {
        $selling = array_values(array_filter($usage, fn (float $used) => $used > 0));
        if (count($selling) < self::MINIMUM_SELLING_DAYS) {
            return $usage;
        }

        $ceiling = self::ONE_OFF_MEDIAN_MULTIPLE * $this->median($selling);

        return array_map(fn (float $used) => min($used, $ceiling), $usage);
    }

    /**
     * @param  list<float>  $values
     */
    private function median(array $values): float
    {
        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    /**
     * Usage on the days the stock was on the shelf since $from, keyed by day, and the number of
     * days in the period.
     *
     * @param  array<string, float>  $usageByDay
     *
     * @return array{0: array<string, float>, 1: int}
     */
    private function inStockSeries(OrgStock $orgStock, Carbon $from, array $usageByDay, ?Carbon $until = null): array
    {
        $series = [];
        $days   = $this->inStockDays($orgStock->id, $from, (float) $orgStock->quantity_available, $this->sellableQuantity($orgStock), $until);
        foreach ($days as $day => $inStock) {
            if ($inStock) {
                $series[$day] = $usageByDay[$day] ?? 0.0;
            }
        }

        return [$series, count($days)];
    }

    /**
     * What the item sold per day on the shelf over its last year, when it was on the shelf long
     * enough for that to mean something and was still selling at the end of it. The year of an
     * item out of stock ends the day it ran out, so one out of stock all year still reads as the
     * item it was, not as unwanted (HELP-4505); one that ran out over two years ago has none.
     *
     * @return array{0: float, 1: float|null}|null [demand per in-stock day, daily sigma]
     */
    private function usageFromOwnYear(OrgStock $orgStock): ?array
    {
        $until = (float) $orgStock->quantity_available > 0 ? now() : $this->ranOutAt($orgStock);
        if (!$until || $until->lt(now()->subDays(self::HISTORY_LOOKBACK_DAYS))) {
            return null;
        }

        $yearFrom = $until->copy()->subDays(self::CAP_YEAR_DAYS)->startOfDay();
        $usage    = array_filter(
            $this->withoutOneOffs($this->dispatchedByDay([$orgStock->id], $yearFrom, withoutPartners: true)[$orgStock->id] ?? []),
            fn (string $day) => $day <= $until->toDateString(),
            ARRAY_FILTER_USE_KEY
        );
        if (!array_filter($usage)) {
            return null;
        }

        [$series] = $this->inStockSeries($orgStock, $yearFrom, $usage, $until);
        $sellingDays = array_filter($series);

        if (count($series) < self::MINIMUM_IN_STOCK_DAYS
            || count($sellingDays) < self::MINIMUM_SELLING_DAYS
            || max(array_keys($sellingDays)) < $until->copy()->subDays(self::HISTORY_LAST_SALE_WITHIN_DAYS)->toDateString()) {
            return null;
        }

        $rate = min(array_sum($series) / count($series), array_sum($usage) / self::CAP_YEAR_DAYS * self::IN_STOCK_RATE_CAP_MULTIPLE);

        return [$rate, $this->standardDeviation(array_values($series))];
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
    private function inStockDays(int $orgStockId, Carbon $from, float $fallbackSeed, float $sellableQuantity, ?Carbon $until = null): array
    {
        $until ??= now();

        $lastRunningByDay = DB::table('org_stock_movements')
            ->where('org_stock_id', $orgStockId)
            ->where('date', '>=', $from)
            ->where('date', '<=', $until->copy()->endOfDay())
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
        for ($day = $from->copy(); $day->lte($until); $day->addDay()) {
            $key = $day->toDateString();
            if (array_key_exists($key, $lastRunningByDay)) {
                $balance = $lastRunningByDay[$key];
            }
            $days[$key] = $balance > 0 && $balance >= $sellableQuantity;
        }

        return $days;
    }

    /**
     * The day the running balance last fell below one sellable order: the first movement after the
     * last one that left enough on the shelf.
     */
    private function ranOutAt(OrgStock $orgStock): ?Carbon
    {
        $lastInStock = DB::table('org_stock_movements')
            ->where('org_stock_id', $orgStock->id)
            ->whereNotNull('running_quantity_org_stock')
            ->where('running_quantity_org_stock', '>=', max(PHP_FLOAT_EPSILON, $this->sellableQuantity($orgStock)))
            ->orderByDesc('date')->orderByDesc('id')
            ->first(['id', 'date']);
        if (!$lastInStock) {
            return null;
        }

        $ranOut = DB::table('org_stock_movements')
            ->where('org_stock_id', $orgStock->id)
            ->whereNotNull('running_quantity_org_stock')
            ->where(fn ($query) => $query->where('date', '>', $lastInStock->date)
                ->orWhere(fn ($query) => $query->where('date', $lastInStock->date)->where('id', '>', $lastInStock->id)))
            ->orderBy('date')->orderBy('id')
            ->value('date');

        return $ranOut ? Carbon::parse($ranOut)->startOfDay() : now();
    }

    /**
     * No history here: borrow the demand of the SAME stock sold by sister organisations,
     * damped to half — their market is similar, not ours. What a sister ships to a partner
     * organisation is restocking, not a market, and is not borrowed (HELP-4057).
     */
    private function usageFromSiblingOrganisations(OrgStock $orgStock): ?float
    {
        $siblingIds = OrgStock::where('stock_id', $orgStock->stock_id)
            ->where('id', '!=', $orgStock->id)
            ->pluck('id')
            ->all();

        $total = array_sum($this->borrowableDailyRates($siblingIds));

        return $total > 0 ? round($total / count($siblingIds) * 0.5, 4) : null;
    }

    /**
     * Still nothing: use the per-SKU demand of the median stock of the same family in this
     * organisation, heavily damped — related products share a customer, not a demand curve, and
     * the median keeps one bulk seller from lending its rate to every other stock of the family.
     */
    private function usageFromFamily(OrgStock $orgStock): ?float
    {
        if (!$orgStock->orgStockFamily) {
            return null;
        }

        $familyStockIds = OrgStock::where('org_stock_family_id', $orgStock->org_stock_family_id)
            ->where('id', '!=', $orgStock->id)
            ->pluck('id')
            ->all();

        $rates  = $this->borrowableDailyRates($familyStockIds);
        $median = $rates ? $this->median(array_values($rates)) : 0.0;

        return $median > 0 ? round($median * 0.25, 4) : null;
    }

    /**
     * What each stock sold to customers per day over the window, its one-off days cut down
     * against its own last year.
     *
     * @param  array<int, int>  $orgStockIds
     *
     * @return array<int, float> org stock id => SKOs per day, every stock asked for
     */
    private function borrowableDailyRates(array $orgStockIds): array
    {
        if (!$orgStockIds) {
            return [];
        }

        $windowFrom = now()->subDays(self::WINDOW)->startOfDay()->toDateString();
        $usage      = $this->dispatchedByDay($orgStockIds, now()->subDays(self::CAP_YEAR_DAYS)->startOfDay(), withoutPartners: true);

        $rates = [];
        foreach ($orgStockIds as $orgStockId) {
            $inWindow           = array_filter($this->withoutOneOffs($usage[$orgStockId] ?? []), fn (string $day) => $day >= $windowFrom, ARRAY_FILTER_USE_KEY);
            $rates[$orgStockId] = array_sum($inWindow) / self::WINDOW;
        }

        return $rates;
    }

    private function toPartnerOrganisation(Builder $query): void
    {
        $query->selectRaw('1')
            ->from('delivery_notes')
            ->join('org_partners', 'org_partners.customer_id', 'delivery_notes.customer_id')
            ->whereColumn('delivery_notes.id', 'delivery_note_items.delivery_note_id');
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
