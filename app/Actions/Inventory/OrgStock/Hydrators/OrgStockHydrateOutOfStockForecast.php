<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Inventory\OrgStock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Predicts when an org stock will run out.
 *
 * The pipeline, in order:
 *  1. Rebuild the daily demand series over the last 91 days from delivery_note_items.created_at
 *     (delivery_note_items.date is only set on Aurora-fetched rows), counting ONLY days the stock
 *     was actually on the shelf (running balance > 0 from org_stock_movements). An item that
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

        [$dailyUsage, $sigma, $source] = $this->predictedDailyUsage($orgStock);

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
     * Units to reorder now: enough to cover the supplier lead time plus one review period at
     * the forecast rate, plus a safety buffer sized by demand variability, minus what is on
     * the shelf and already on order — rounded up to the supplier's pack size.
     * ponytail: lead time is a 14-day constant; observed per-supplier lead times are the upgrade
     */
    private function recommendedOrderQuantity(OrgStock $orgStock, ?float $dailyUsage, ?float $sigma): ?float
    {
        if (!$dailyUsage) {
            return null;
        }

        $leadTimeDays = 14;
        $reviewDays   = 30;

        $safety = $sigma !== null
            ? 1.28 * $sigma * sqrt($leadTimeDays)
            : 0.2 * $dailyUsage * $leadTimeDays;

        $onOrder = (float) DB::table('purchase_order_transactions')
            ->where('org_stock_id', $orgStock->id)
            ->whereIn('state', ['in_process', 'submitted', 'confirmed'])
            ->sum('quantity_ordered');

        $need = $dailyUsage * ($leadTimeDays + $reviewDays) + $safety
            - (float) $orgStock->quantity_available
            - $onOrder;

        if ($need <= 0) {
            return 0;
        }

        $pack = $orgStock->orgSupplierProducts
            ->first(fn ($orgSupplierProduct) => $orgSupplierProduct->pivot->status)
            ?->supplierProduct?->units_per_pack;

        if ($pack > 1) {
            $need = ceil($need / $pack) * $pack;
        }

        return round($need, 3);
    }

    /**
     * @return array{0: float|null, 1: float|null, 2: string|null} [demand per in-stock day, daily sigma, source]
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
        foreach ($this->inStockDays($orgStock->id, $from, (float) $orgStock->quantity_available) as $day => $inStock) {
            if ($inStock) {
                $series[$day] = (float) ($dispatchedByDay[$day] ?? 0);
            }
        }

        if (array_sum($series) <= 0) {
            if ($rate = $this->usageFromSiblingOrganisations($orgStock)) {
                return [$rate, null, 'siblings'];
            }
            if ($rate = $this->usageFromFamily($orgStock)) {
                return [$rate, null, 'family'];
            }

            return [null, null, null];
        }

        $sigma = $this->standardDeviation(array_values($series));

        $nonZeroShare = count(array_filter($series)) / count($series);
        if ($nonZeroShare < 0.3) {
            return [$this->crostonSba(array_values($series)), $sigma, 'croston'];
        }

        return [$this->holtDamped($series), $sigma, 'holt'];
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
     * Which days of the window the stock was actually on the shelf, rebuilt from the
     * movements' running balance.
     *
     * @return array<string, bool> day (Y-m-d) => was in stock
     */
    private function inStockDays(int $orgStockId, Carbon $from, float $fallbackSeed): array
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
            $days[$key] = $balance > 0;
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
