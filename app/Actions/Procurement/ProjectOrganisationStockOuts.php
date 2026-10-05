<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement;

use App\Actions\Goods\UI\ShowGoodsDashboard;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Plays the next eight weeks forward for the same SKOs the stock out history counts (active and
 * discontinuing, never on demand ones): what is on hand,
 * less each day's forecast demand, plus what open purchase orders and stock deliveries bring on their
 * expected day; late ones are not counted until they have a new date. A SKO with less than one left is out of stock that day, as in the stock out history,
 * and loses its average daily sales of the six full months before, as the history's lost revenue
 * does. The range replays every SKO with its demand at the low and the high end of its own forecast
 * (10th and 90th percentile). Per day and per source it goes into
 * organisation_procurement_stats.stock_out_projection; per SKO, the revenue it loses over its lead
 * time and a month of reordering if nothing more is ordered goes into org_stock_stats.projected_lost_revenue.
 */
class ProjectOrganisationStockOuts
{
    use AsAction;

    public string $commandSignature = 'hydrate:organisation-stock-out-projection {organisation? : organisation slug}';

    public const int DAYS = 56;

    private const float BAND_Z = 1.2816;

    private const int REORDER_DAYS = 30;

    private const int SALES_RATE_MONTHS = 6;

    public function handle(Organisation $organisation, ?Carbon $today = null): void
    {
        $today   = ($today ?? today())->copy()->startOfDay();
        $buckets = GetOrganisationStockCoverBuckets::make();
        $skos    = DB::table('org_stocks')
            ->leftJoinLateral($buckets->primarySupplierProduct(), 'sp')
            ->leftJoin('org_stock_stats', 'org_stock_stats.org_stock_id', 'org_stocks.id')
            ->where('org_stocks.organisation_id', $organisation->id)
            ->whereIn('org_stocks.state', [OrgStockStateEnum::ACTIVE->value, OrgStockStateEnum::DISCONTINUING->value])
            ->whereNull('org_stocks.deleted_at')
            ->whereRaw('coalesce(org_stocks.is_fresh, false) = false')
            ->whereRaw('coalesce(org_stocks.is_on_demand, false) = false')
            ->where(fn ($query) => $query->where('org_stocks.quantity_available', '>', 0)->orWhere(fn ($query) => $buckets->whereCountsAsStockOut($query)))
            ->select('org_stocks.id', 'org_stocks.quantity_available', 'org_stocks.packed_in', 'org_stock_stats.predicted_daily_usage', 'org_stock_stats.demand_variability', 'org_stock_stats.forecast_source')
            ->selectRaw("org_stock_stats.demand_forecast->'weeks' as weeks, org_stock_stats.demand_forecast->>'from' as forecast_from")
            ->selectRaw($buckets->sourceExpression().' as source')
            ->selectRaw($buckets->leadTimeExpression().' as lead_time_days')
            ->get();

        $ids       = $skos->pluck('id')->all();
        $rates     = $this->dailySalesValues($ids, $today);
        $arrivals  = $this->arrivals($ids, $skos->pluck('packed_in', 'id')->all(), $today);
        $sources   = [];
        $projected = [];

        foreach ($skos as $sko) {
            [$demand, $variance] = $this->dailyDemand($sko, $today);
            $spread              = $demand > 0 ? self::BAND_Z * sqrt($variance) / $demand : 0.0;
            $rate                = $rates[$sko->id] ?? 0.0;
            $scenarios           = [
                $this->outOfStockDays((float) $sko->quantity_available, $demand, $arrivals[$sko->id] ?? []),
                $this->outOfStockDays((float) $sko->quantity_available, $demand * max(0.0, 1 - $spread), $arrivals[$sko->id] ?? []),
                $this->outOfStockDays((float) $sko->quantity_available, $demand * (1 + $spread), $arrivals[$sko->id] ?? []),
            ];

            foreach (['all', $sko->source] as $source) {
                $sources[$source] ??= array_fill(0, self::DAYS, [0, 0, 0, 0.0]);
                for ($day = 0; $day < self::DAYS; $day++) {
                    $sources[$source][$day][0] += $scenarios[0][$day];
                    $sources[$source][$day][1] += $scenarios[1][$day];
                    $sources[$source][$day][2] += $scenarios[2][$day];
                    $sources[$source][$day][3] += $scenarios[0][$day] * $rate;
                }
            }

            $horizon               = min(self::DAYS, (int) $sko->lead_time_days + self::REORDER_DAYS);
            $projected[$sko->id]   = round(array_sum(array_slice($scenarios[0], 0, $horizon)) * $rate, 2);
        }

        $organisation->procurementStats()->update([
            'stock_out_projection'             => json_encode([
                'from'    => $today->copy()->addDay()->toDateString(),
                'sources' => array_map(fn (array $days) => array_map(fn (array $day) => [$day[0], $day[1], $day[2], round($day[3], 2)], $days), $sources),
            ]),
            'stock_out_projection_hydrated_at' => now(),
        ]);

        $this->storeProjectedLostRevenue($projected);
    }

    /**
     * Expected units a day and their daily variance: the TimesFM weeks when the forecast in use came
     * from them, otherwise the live daily rate with its spread.
     *
     * @return array{0: float, 1: float}
     */
    private function dailyDemand(object $sko, Carbon $today): array
    {
        $weeks = $sko->weeks ? json_decode($sko->weeks, true) : null;
        if ($sko->forecast_source === 'timesfm' && is_array($weeks) && $weeks && $sko->forecast_from && Carbon::parse($sko->forecast_from)->gte($today->copy()->subDay())) {
            $days = count($weeks) * 7;

            return [array_sum(array_column($weeks, 0)) / $days, array_sum(array_column($weeks, 1)) / $days];
        }

        $rate  = (float) $sko->predicted_daily_usage;
        $sigma = $rate * (float) $sko->demand_variability;

        return [$rate, $sigma ** 2];
    }

    /**
     * @param  array<int, float>  $arrivals  day (1 = tomorrow) => SKOs arriving
     *
     * @return list<int> 1 for each of the next days the SKO has less than one left
     */
    private function outOfStockDays(float $onHand, float $dailyDemand, array $arrivals): array
    {
        $stock = $onHand;
        $out   = [];
        for ($day = 1; $day <= self::DAYS; $day++) {
            $stock = max(0.0, $stock - $dailyDemand) + ($arrivals[$day] ?? 0.0);
            $out[] = $stock < 1 ? 1 : 0;
        }

        return $out;
    }

    /**
     * SKOs arriving each day from open stock deliveries (in units, so divided by the SKO's pack) and
     * open purchase orders not on a delivery yet. Lines already past their expected arrival are left
     * out until they get a new date: counted as arriving tomorrow, they brought 131 of aw's 204 agent
     * SKOs out of stock back overnight (2 Oct 2026).
     *
     * @param  array<int, int>  $orgStockIds
     * @param  array<int, mixed>  $packedIn
     *
     * @return array<int, array<int, float>> org stock id => day => SKOs
     */
    private function arrivals(array $orgStockIds, array $packedIn, Carbon $today): array
    {
        if (!$orgStockIds) {
            return [];
        }

        $goods    = ShowGoodsDashboard::make();
        $arrivals = [];
        foreach (array_merge($goods->stockDeliveryInboundLines($orgStockIds), $goods->purchaseOrderInboundLines($orgStockIds)) as $line) {
            $day = $line['eta'] && !($line['is_late'] ?? false) ? max(1, (int) $today->diffInDays(Carbon::parse($line['eta']))) : null;
            if ($day === null || $day > self::DAYS) {
                continue;
            }
            $skos = str_starts_with($line['document'], 'sd:') ? $line['quantity'] / ((float) ($packedIn[$line['org_stock_id']] ?? 1) ?: 1.0) : $line['quantity'];

            $arrivals[$line['org_stock_id']][$day] = ($arrivals[$line['org_stock_id']][$day] ?? 0.0) + $skos;
        }

        return $arrivals;
    }

    /**
     * Each SKO's average sales a day in the organisation's currency over the six full months before,
     * as the stock out history values a day out of stock.
     *
     * @param  array<int, int>  $orgStockIds
     *
     * @return array<int, float>
     */
    private function dailySalesValues(array $orgStockIds, Carbon $today): array
    {
        if (!$orgStockIds) {
            return [];
        }

        $windowEnd   = $today->copy()->startOfMonth();
        $windowStart = $windowEnd->copy()->subMonths(self::SALES_RATE_MONTHS);
        $days        = $windowStart->diffInDays($windowEnd);

        return DB::table('org_stock_time_series as series')
            ->join('org_stock_time_series_records as records', 'records.org_stock_time_series_id', 'series.id')
            ->whereIn('series.org_stock_id', $orgStockIds)
            ->where('series.frequency', TimeSeriesFrequencyEnum::MONTHLY->value)
            ->where('records.frequency', TimeSeriesFrequencyEnum::MONTHLY->singleLetter())
            ->where('records.from', '>=', $windowStart->toDateString())
            ->where('records.from', '<', $windowEnd->toDateString())
            ->groupBy('series.org_stock_id')
            ->selectRaw('series.org_stock_id, sum(records.sales_org_currency_external) as sales')
            ->pluck('sales', 'org_stock_id')
            ->map(fn ($sales) => (float) $sales / $days)
            ->all();
    }

    /**
     * @param  array<int, float>  $projected  org stock id => projected lost revenue
     */
    private function storeProjectedLostRevenue(array $projected): void
    {
        foreach (array_chunk($projected, 1000, true) as $chunk) {
            $values   = implode(', ', array_fill(0, count($chunk), '(?::int, ?::numeric)'));
            $bindings = [];
            foreach ($chunk as $orgStockId => $lost) {
                array_push($bindings, $orgStockId, $lost);
            }

            DB::update(
                "update org_stock_stats set projected_lost_revenue = v.lost from (values $values) as v(org_stock_id, lost) where org_stock_stats.org_stock_id = v.org_stock_id",
                $bindings
            );
        }
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $organisations = Organisation::where('type', OrganisationTypeEnum::SHOP->value)
            ->when($command->argument('organisation'), fn ($query, $slug) => $query->where('slug', $slug))
            ->get();

        foreach ($organisations as $organisation) {
            $this->handle($organisation);
            $command->info("Projected stock outs for $organisation->slug");
        }

        return 0;
    }
}
