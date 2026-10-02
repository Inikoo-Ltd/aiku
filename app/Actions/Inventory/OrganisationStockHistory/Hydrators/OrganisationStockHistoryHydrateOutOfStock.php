<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrganisationStockHistory\Hydrators;

use App\Actions\Inventory\GroupStockHistory\Hydrators\GroupStockHistoryHydrateFromOrgStockHistories;
use App\Actions\Procurement\GetOrganisationStockCoverBuckets;
use App\Actions\Traits\WithStockHistoryArchiveRead;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Inventory\OrganisationStockHistory;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A SKO is out of stock on a day when it has less than one unit in its locations, or when it was
 * alive that day but had no location at all (those never get an org stock history row). Fresh SKOs
 * are made only for orders already placed and never kept on the shelf, so they are left out entirely.
 * An empty SKO is only a stock out when it feeds a product on sale today and had received stock by
 * that day; otherwise it is left out of the day's SKOs too.
 *
 * The estimated lost revenue is what those SKOs would have sold that day: each one's average daily
 * sales over the full months before, so a stock out never lowers its own rate. Discontinued SKOs,
 * and on demand SKOs that customers can still order, are not lost sales.
 *
 * Aurora SKOs were all created in Aiku on 31 Dec 2024, so SKOs without a location only count from then.
 */
class OrganisationStockHistoryHydrateOutOfStock
{
    use AsAction;
    use WithStockHistoryArchiveRead;

    public const int SALES_RATE_MONTHS = 6;

    public string $commandSignature = 'hydrate:organisation_stock_histories_out_of_stock {organisation?} {--from= : first date, Y-m-d}';

    public function asCommand(Command $command): int
    {
        $ids = OrganisationStockHistory::query()
            ->when($command->argument('organisation'), fn ($query, $slug) => $query->where('organisation_id', Organisation::where('slug', $slug)->firstOrFail()->id))
            ->when($command->option('from'), fn ($query, $from) => $query->where('date', '>=', $from))
            ->orderByDesc('date')
            ->pluck('id');

        $command->withProgressBar($ids, fn (int $id) => $this->handle($id));
        $command->newLine();

        return 0;
    }

    public function handle(int $organisationStockHistoryId): void
    {
        $organisationStockHistory = OrganisationStockHistory::find($organisationStockHistoryId);
        if (!$organisationStockHistory) {
            return;
        }

        $date = Carbon::parse($organisationStockHistory->date);

        $quantities = DB::connection($this->stockHistoryDayConnection($organisationStockHistory) ?? 'aiku_no_sticky')
            ->table('org_stock_histories')
            ->where('organisation_stock_history_id', $organisationStockHistory->id)
            ->pluck('quantity_in_locations', 'org_stock_id')
            ->all();

        $quantities = array_diff_key($quantities, array_flip($this->freshOrgStockIds($organisationStockHistory->organisation_id)));

        $withoutLocation = array_values(array_diff($this->aliveOrgStockIds($organisationStockHistory->organisation_id, $date), array_keys($quantities)));
        $emptyOrgStocks  = array_merge(array_keys(array_filter($quantities, fn ($quantity) => $quantity < 1)), $withoutLocation);
        $outOfStock      = $this->stockOutOrgStockIds($emptyOrgStocks, $date);
        $countedIds      = array_diff(array_merge(array_keys($quantities), $withoutLocation), array_diff($emptyOrgStocks, $outOfStock));
        $sources         = $this->sourceByOrgStockId($organisationStockHistory->organisation_id);
        $sourceOf        = fn (int $orgStockId) => $sources[$orgStockId] ?? 'none';
        $countedBySource = array_count_values(array_map($sourceOf, $countedIds));
        $outBySource     = collect($outOfStock)->groupBy($sourceOf);

        $sourceRows = collect(array_keys(GetOrganisationStockCoverBuckets::SOURCES))->map(fn (string $source) => [
            'organisation_stock_history_id'       => $organisationStockHistory->id,
            'organisation_id'                     => $organisationStockHistory->organisation_id,
            'date'                                => $date->toDateString(),
            'source'                              => $source,
            'number_org_stocks'                   => $countedBySource[$source] ?? 0,
            'number_out_of_stock_org_stocks'      => count($outBySource->get($source, [])),
            'estimated_lost_revenue_org_currency' => $this->estimatedLostRevenue($outBySource->get($source, collect())->all(), $date),
            'created_at'                          => now(),
            'updated_at'                          => now(),
        ]);

        DB::table('organisation_stock_history_sources')->upsert(
            $sourceRows->all(),
            ['organisation_stock_history_id', 'source'],
            ['number_org_stocks', 'number_out_of_stock_org_stocks', 'estimated_lost_revenue_org_currency', 'updated_at']
        );

        $numberOrgStocks = count($countedIds);

        $organisationStockHistory->update([
            'number_org_stocks'                   => $numberOrgStocks,
            'number_out_of_stock_org_stocks'      => count($outOfStock),
            'percentage_out_of_stock'             => $numberOrgStocks ? round(count($outOfStock) / $numberOrgStocks * 100, 2) : 0,
            'estimated_lost_revenue_org_currency' => round($sourceRows->sum('estimated_lost_revenue_org_currency'), 2),
        ]);

        GroupStockHistoryHydrateFromOrgStockHistories::run($organisationStockHistory->group_stock_history_id);
    }

    /**
     * @return array<int, int>
     */
    public function aliveOrgStockIds(int $organisationId, Carbon $date): array
    {
        $nextDay = $date->copy()->addDay()->startOfDay();

        return DB::connection('aiku_no_sticky')->table('org_stocks')
            ->where('organisation_id', $organisationId)
            ->where('created_at', '<', $nextDay)
            ->where('is_fresh', false)
            ->whereNull('deleted_at')
            ->where(fn ($query) => $query
                ->whereIn('state', [OrgStockStateEnum::ACTIVE->value, OrgStockStateEnum::DISCONTINUING->value])
                ->orWhere('discontinued_in_organisation_at', '>=', $nextDay))
            ->pluck('id')
            ->all();
    }

    /**
     * @param array<int, int> $orgStockIds
     * @return array<int, int>
     */
    public function stockOutOrgStockIds(array $orgStockIds, Carbon $date): array
    {
        if ($orgStockIds === []) {
            return [];
        }

        return GetOrganisationStockCoverBuckets::make()
            ->whereCountsAsStockOut(DB::connection('aiku_no_sticky')->table('org_stocks')->whereIn('org_stocks.id', $orgStockIds), $date->copy()->addDay()->startOfDay())
            ->pluck('org_stocks.id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function sourceByOrgStockId(int $organisationId): array
    {
        $buckets = GetOrganisationStockCoverBuckets::make();

        return DB::connection('aiku_no_sticky')->table('org_stocks')
            ->leftJoinLateral($buckets->primarySupplierProduct(), 'sp')
            ->where('org_stocks.organisation_id', $organisationId)
            ->selectRaw('org_stocks.id, '.$buckets->sourceExpression().' as source')
            ->pluck('source', 'id')
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function freshOrgStockIds(int $organisationId): array
    {
        return DB::connection('aiku_no_sticky')->table('org_stocks')
            ->where('organisation_id', $organisationId)
            ->where('is_fresh', true)
            ->pluck('id')
            ->all();
    }

    /**
     * @param array<int, int> $orgStockIds
     */
    public function estimatedLostRevenue(array $orgStockIds, Carbon $date): float
    {
        if ($orgStockIds === []) {
            return 0;
        }

        $windowEnd   = $date->copy()->startOfMonth();
        $windowStart = $windowEnd->copy()->subMonths(self::SALES_RATE_MONTHS);

        $sales = DB::connection('aiku_no_sticky')->table('org_stock_time_series as series')
            ->join('org_stocks', 'org_stocks.id', 'series.org_stock_id')
            ->join('org_stock_time_series_records as records', 'records.org_stock_time_series_id', 'series.id')
            ->whereIn('series.org_stock_id', $orgStockIds)
            ->where('series.frequency', TimeSeriesFrequencyEnum::MONTHLY->value)
            ->where('records.frequency', TimeSeriesFrequencyEnum::MONTHLY->singleLetter())
            ->where('records.from', '>=', $windowStart->toDateString())
            ->where('records.from', '<', $windowEnd->toDateString())
            ->where('org_stocks.state', '!=', OrgStockStateEnum::DISCONTINUED->value)
            ->whereRaw('coalesce(org_stocks.is_on_demand, false) = false')
            ->sum('records.sales_org_currency_external');

        return round((float)$sales / $windowStart->diffInDays($windowEnd), 2);
    }
}
