<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\GetOrganisationStockCoverBuckets;
use App\Actions\UI\WithInertia;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The deeper sales, stock, demand, exception and promotion view behind the Command View: sales by
 * calendar month, quarter or year with current/previous/last-year comparisons by family, product and
 * organisation, stock value and out-of-stock trend, inbound and overdue purchase orders, the numbers
 * behind the understock/overstock labels, status history and read-only promotion candidates.
 *
 * The heavy per-organisation-per-stock sales matrix and the sales-over-time and stock-trend series are
 * built once per group and granularity, cached (gzipped) for a while, then filtered and grouped in
 * memory per request, exactly like ShowGoodsDashboard. Exceptions, urgent actions and promotion
 * candidates reuse that Command View dataset instead of recomputing conditions.
 */
class ShowGoodsAnalysis extends OrgAction
{
    use AsAction;
    use WithInertia;

    private const int CACHE_MINUTES = 20;

    public const array GRANULARITIES = ['month', 'quarter', 'year'];

    public const string DEFAULT_GRANULARITY = 'month';

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('goods.view');
    }

    public function rules(): array
    {
        return [
            'organisation' => ['sometimes', 'nullable', 'string'],
            'family'       => ['sometimes', 'nullable', 'string'],
            'search'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'granularity'  => ['sometimes', 'nullable', Rule::in(self::GRANULARITIES)],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->validatedData);
    }

    public function forGroup(Group $group): static
    {
        $this->group = $group;

        return $this;
    }

    public static function cacheKey(int $groupId, string $granularity): string
    {
        return 'goods-analysis:'.$groupId.':'.$granularity;
    }

    public function handle(array $filters): array
    {
        $granularity  = Arr::get($filters, 'granularity') ?: self::DEFAULT_GRANULARITY;
        $organisation = Arr::get($filters, 'organisation');
        $family       = Arr::get($filters, 'family');
        $search       = trim((string) Arr::get($filters, 'search'));

        $dataset = $this->dataset($granularity);
        $rows    = $this->filterRows($dataset['rows'], $organisation, $family, $search);
        $product = $search !== '' ? $this->singleProduct($rows) : null;

        $commandDataset = ShowGoodsDashboard::make()->forGroup($this->group)->dataset();
        $commandRows    = $this->filterCommandRows($commandDataset['rows'], $organisation, $family, $search);

        return [
            'built_at'             => $dataset['built_at'],
            'granularity'          => $granularity,
            'granularities'        => self::GRANULARITIES,
            'organisations'        => $commandDataset['organisations'],
            'families'             => $commandDataset['families'],
            'filters'              => [
                'organisation' => $organisation,
                'family'       => $family,
                'search'       => Arr::get($filters, 'search'),
                'granularity'  => $granularity,
            ],
            'product'              => $product,
            'summary'              => $this->summary($rows),
            'series'               => $this->seriesForChart($dataset['series'], $organisation, $family, $product, $granularity),
            'families_table'       => $this->groupTable($rows, 'family_code'),
            'organisations_table'  => $this->groupTable($rows, 'organisation_code'),
            'products_table'       => $this->productTable($rows),
            'stock_trend'          => $this->stockTrendForChart($dataset['stock_trend'], $organisation),
            ...$this->commandInsights($commandRows, $organisation),
            'status_history'       => $this->statusHistory($organisation, $family, $search),
            'currency_code'        => $this->group->currency->code,
        ];
    }

    // ponytail: whole matrix cached per granularity, filtered in memory per request, same shape as ShowGoodsDashboard
    private function dataset(string $granularity): array
    {
        return json_decode(gzuncompress(Cache::remember(
            self::cacheKey($this->group->id, $granularity),
            now()->addMinutes(self::CACHE_MINUTES),
            fn () => gzcompress(json_encode($this->buildDataset($granularity)))
        )), true);
    }

    private function buildDataset(string $granularity): array
    {
        return [
            'built_at'    => now()->toIso8601String(),
            'rows'        => $this->salesRows($granularity)->map(fn ($row) => (array) $row)->all(),
            'series'      => $this->seriesRows($granularity)->map(fn ($row) => (array) $row)->all(),
            'stock_trend' => $this->stockTrendRows($granularity)->map(fn ($row) => (array) $row)->all(),
        ];
    }

    /**
     * @return array{currentStart: Carbon, previousStart: Carbon, previousEnd: Carbon, lastYearStart: Carbon, lastYearEnd: Carbon}
     */
    private function periodBounds(string $granularity, Carbon $now): array
    {
        $currentStart = match ($granularity) {
            'month'   => $now->copy()->startOfMonth(),
            'quarter' => $now->copy()->startOfQuarter(),
            'year'    => $now->copy()->startOfYear(),
        };
        $daysElapsed = (int) $currentStart->diffInDays($now) + 1;

        $previousStart = match ($granularity) {
            'month'   => $currentStart->copy()->subMonthNoOverflow(),
            'quarter' => $currentStart->copy()->subQuarterNoOverflow(),
            'year'    => $currentStart->copy()->subYearNoOverflow(),
        };
        $previousEnd = $previousStart->copy()->addDays($daysElapsed);

        $lastYearStart = $currentStart->copy()->subYearNoOverflow();
        $lastYearEnd   = $lastYearStart->copy()->addDays($daysElapsed);

        return compact('currentStart', 'previousStart', 'previousEnd', 'lastYearStart', 'lastYearEnd');
    }

    private function seriesLookbackStart(string $granularity): Carbon
    {
        return match ($granularity) {
            'month'   => now()->copy()->subMonthsNoOverflow(23)->startOfMonth(),
            'quarter' => now()->copy()->subQuartersNoOverflow(11)->startOfQuarter(),
            'year'    => now()->copy()->subYearsNoOverflow(4)->startOfYear(),
        };
    }

    /**
     * One row per organisation/stock with calendar current, previous and same-period-last-year sales,
     * all already in the group reporting currency. Only combinations with a monthly record inside the
     * lookback window are returned; everything else is genuinely zero and irrelevant to a sales table.
     */
    private function salesRows(string $granularity): Collection
    {
        ['currentStart' => $currentStart, 'previousStart' => $previousStart, 'previousEnd' => $previousEnd, 'lastYearStart' => $lastYearStart, 'lastYearEnd' => $lastYearEnd] = $this->periodBounds($granularity, now());

        return DB::table('org_stock_time_series')
            ->join('org_stock_time_series_records as r', function ($join) use ($lastYearStart) {
                $join->on('r.org_stock_time_series_id', 'org_stock_time_series.id')
                    ->where('r.frequency', TimeSeriesFrequencyEnum::MONTHLY->singleLetter())
                    ->where('r.from', '>=', $lastYearStart);
            })
            ->join('org_stocks', 'org_stocks.id', 'org_stock_time_series.org_stock_id')
            ->join('organisations', 'organisations.id', 'org_stocks.organisation_id')
            ->join('stocks', 'stocks.id', 'org_stocks.stock_id')
            ->leftJoin('stock_families', 'stock_families.id', 'stocks.stock_family_id')
            ->where('org_stock_time_series.frequency', TimeSeriesFrequencyEnum::MONTHLY->value)
            ->where('organisations.group_id', $this->group->id)
            ->whereNull('org_stocks.deleted_at')
            ->groupBy('organisations.code', 'stock_families.code', 'stocks.id', 'stocks.code', 'stocks.name')
            ->select([
                'organisations.code as organisation_code',
                'stock_families.code as family_code',
                'stocks.id as stock_id',
                'stocks.code as stock_code',
                'stocks.name as stock_name',
            ])
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external) filter (where r.from >= ?), 0) as current', [$currentStart])
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external) filter (where r.from >= ? and r.from < ?), 0) as previous', [$previousStart, $previousEnd])
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external) filter (where r.from >= ? and r.from < ?), 0) as last_year', [$lastYearStart, $lastYearEnd])
            ->get();
    }

    /**
     * Sales per organisation and family bucketed by calendar period, for the sales-over-time chart.
     * Grouped in SQL by date_trunc, so the yearly and quarterly views come straight out of the monthly
     * records rather than reading the sparse (empty on prod) yearly/quarterly partitions.
     */
    private function seriesRows(string $granularity): Collection
    {
        $lookbackStart = $this->seriesLookbackStart($granularity);

        return DB::table('org_stock_time_series')
            ->join('org_stock_time_series_records as r', function ($join) use ($lookbackStart) {
                $join->on('r.org_stock_time_series_id', 'org_stock_time_series.id')
                    ->where('r.frequency', TimeSeriesFrequencyEnum::MONTHLY->singleLetter())
                    ->where('r.from', '>=', $lookbackStart);
            })
            ->join('org_stocks', 'org_stocks.id', 'org_stock_time_series.org_stock_id')
            ->join('organisations', 'organisations.id', 'org_stocks.organisation_id')
            ->join('stocks', 'stocks.id', 'org_stocks.stock_id')
            ->leftJoin('stock_families', 'stock_families.id', 'stocks.stock_family_id')
            ->where('org_stock_time_series.frequency', TimeSeriesFrequencyEnum::MONTHLY->value)
            ->where('organisations.group_id', $this->group->id)
            ->whereNull('org_stocks.deleted_at')
            ->groupBy('organisations.code', 'stock_families.code', DB::raw("date_trunc('{$granularity}', r.\"from\")"))
            ->select(['organisations.code as organisation_code', 'stock_families.code as family_code'])
            ->selectRaw("date_trunc('{$granularity}', r.\"from\") as period")
            ->selectRaw('sum(r.sales_grp_currency_external) as sales')
            ->orderBy('period')
            ->get();
    }

    /**
     * Stock value (landed cost, group currency) and % out of stock per organisation, from the daily
     * organisation_stock_histories snapshot table, sampled at its own is_month/is_year flags so the
     * query only ever touches a handful of rows per organisation rather than the full daily history.
     */
    private function stockTrendRows(string $granularity): Collection
    {
        $lookbackStart = $this->seriesLookbackStart($granularity);

        $query = DB::table('organisation_stock_histories')
            ->join('organisations', 'organisations.id', 'organisation_stock_histories.organisation_id')
            ->where('organisation_stock_histories.group_id', $this->group->id)
            ->where('organisation_stock_histories.date', '>=', $lookbackStart);

        match ($granularity) {
            'month'   => $query->where('organisation_stock_histories.is_month', true),
            'quarter' => $query->where('organisation_stock_histories.is_month', true)
                ->whereRaw('extract(month from organisation_stock_histories.date) in (3,6,9,12)'),
            'year'    => $query->where('organisation_stock_histories.is_year', true),
        };

        return $query
            ->orderBy('organisation_stock_histories.date')
            ->select([
                'organisations.code as organisation_code',
                'organisation_stock_histories.date',
                'organisation_stock_histories.grp_stock_lpp_value as stock_value',
                'organisation_stock_histories.percentage_out_of_stock',
            ])
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function filterRows(array $rows, ?string $organisation, ?string $family, string $search): array
    {
        return array_values(array_filter($rows, function (array $row) use ($organisation, $family, $search) {
            return (!$organisation || $row['organisation_code'] === $organisation)
                && (!$family || $row['family_code'] === $family)
                && ($search === '' || Str::contains($row['stock_code'].' '.$row['stock_name'].' '.$row['family_code'], $search, ignoreCase: true));
        }));
    }

    private function filterCommandRows(array $rows, ?string $organisation, ?string $family, string $search): array
    {
        return array_values(array_filter($rows, function (array $row) use ($organisation, $family, $search) {
            return (!$organisation || isset($row['organisations'][$organisation]))
                && (!$family || $row['family_code'] === $family)
                && ($search === '' || Str::contains($row['code'].' '.$row['name'].' '.$row['family_code'], $search, ignoreCase: true));
        }));
    }

    /**
     * A search that narrows the filtered rows to a single group stock switches the whole page into
     * that product's own analysis, per the brief.
     */
    private function singleProduct(array $rows): ?array
    {
        $stockIds = array_unique(array_column($rows, 'stock_id'));
        if (count($stockIds) !== 1) {
            return null;
        }

        $first = $rows[0];

        return [
            'id'   => $first['stock_id'],
            'code' => $first['stock_code'],
            'name' => $first['stock_name'],
        ];
    }

    private function summary(array $rows): array
    {
        $current  = round(array_sum(array_column($rows, 'current')), 2);
        $previous = round(array_sum(array_column($rows, 'previous')), 2);
        $lastYear = round(array_sum(array_column($rows, 'last_year')), 2);

        return [
            'current'             => $current,
            'previous'            => $previous,
            'last_year'           => $lastYear,
            'change_vs_previous'  => $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null,
            'change_vs_last_year' => $lastYear > 0 ? round(($current - $lastYear) / $lastYear * 100, 1) : null,
        ];
    }

    /**
     * Top 20 families or organisations by current sales, with previous and last-year comparisons.
     */
    private function groupTable(array $rows, string $key): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $value = $row[$key] ?? null;
            if ($value === null) {
                continue;
            }
            $groups[$value]['current']   = ($groups[$value]['current'] ?? 0) + $row['current'];
            $groups[$value]['previous']  = ($groups[$value]['previous'] ?? 0) + $row['previous'];
            $groups[$value]['last_year'] = ($groups[$value]['last_year'] ?? 0) + $row['last_year'];
        }

        $table = [];
        foreach ($groups as $value => $sums) {
            $table[] = [
                'key'                 => $value,
                'current'             => round($sums['current'], 2),
                'previous'            => round($sums['previous'], 2),
                'last_year'           => round($sums['last_year'], 2),
                'change_vs_previous'  => $sums['previous'] > 0 ? round(($sums['current'] - $sums['previous']) / $sums['previous'] * 100, 1) : null,
                'change_vs_last_year' => $sums['last_year'] > 0 ? round(($sums['current'] - $sums['last_year']) / $sums['last_year'] * 100, 1) : null,
            ];
        }

        usort($table, fn ($a, $b) => $b['current'] <=> $a['current']);

        return array_slice($table, 0, 20);
    }

    /**
     * Top 20 risers and top 20 fallers by change % versus the previous period; products with no
     * previous-period sales are left out, a % change against zero is not a usable ranking.
     */
    private function productTable(array $rows): array
    {
        $products = [];
        foreach ($rows as $row) {
            $id                        = $row['stock_id'];
            $products[$id]['code']     = $row['stock_code'];
            $products[$id]['name']     = $row['stock_name'];
            $products[$id]['current']  = ($products[$id]['current'] ?? 0) + $row['current'];
            $products[$id]['previous'] = ($products[$id]['previous'] ?? 0) + $row['previous'];
        }

        $list = [];
        foreach ($products as $id => $product) {
            if ($product['previous'] <= 0) {
                continue;
            }
            $list[] = [
                'id'       => $id,
                'code'     => $product['code'],
                'name'     => $product['name'],
                'current'  => round($product['current'], 2),
                'previous' => round($product['previous'], 2),
                'change'   => round(($product['current'] - $product['previous']) / $product['previous'] * 100, 1),
            ];
        }

        usort($list, fn ($a, $b) => $b['change'] <=> $a['change']);

        return [
            'risers'  => array_slice($list, 0, 20),
            'fallers' => array_slice(array_reverse($list), 0, 20),
        ];
    }

    private function seriesForChart(array $seriesRows, ?string $organisation, ?string $family, ?array $product, string $granularity): array
    {
        if ($product) {
            return $this->productSeries($product['id'], $granularity);
        }

        $totals = [];
        foreach ($seriesRows as $row) {
            if ($organisation && $row['organisation_code'] !== $organisation) {
                continue;
            }
            if ($family && $row['family_code'] !== $family) {
                continue;
            }
            $period = $row['period'];
            $totals[$period][$row['organisation_code']] = ($totals[$period][$row['organisation_code']] ?? 0) + (float) $row['sales'];
        }

        return $this->pointsFromTotals($totals);
    }

    /**
     * Live per organisation monthly/quarterly/yearly sales for one stock; cheap enough (a handful of
     * org_stocks) that it does not need its own cache entry.
     */
    private function productSeries(int $stockId, string $granularity): array
    {
        $lookbackStart = $this->seriesLookbackStart($granularity);

        $rows = DB::table('org_stock_time_series')
            ->join('org_stock_time_series_records as r', function ($join) use ($lookbackStart) {
                $join->on('r.org_stock_time_series_id', 'org_stock_time_series.id')
                    ->where('r.frequency', TimeSeriesFrequencyEnum::MONTHLY->singleLetter())
                    ->where('r.from', '>=', $lookbackStart);
            })
            ->join('org_stocks', 'org_stocks.id', 'org_stock_time_series.org_stock_id')
            ->join('organisations', 'organisations.id', 'org_stocks.organisation_id')
            ->where('org_stock_time_series.frequency', TimeSeriesFrequencyEnum::MONTHLY->value)
            ->where('org_stocks.stock_id', $stockId)
            ->groupBy('organisations.code', DB::raw("date_trunc('{$granularity}', r.\"from\")"))
            ->select(['organisations.code as organisation_code'])
            ->selectRaw("date_trunc('{$granularity}', r.\"from\") as period")
            ->selectRaw('sum(r.sales_grp_currency_external) as sales')
            ->orderBy('period')
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            $totals[$row->period][$row->organisation_code] = ($totals[$row->period][$row->organisation_code] ?? 0) + (float) $row->sales;
        }

        return $this->pointsFromTotals($totals);
    }

    private function stockTrendForChart(array $stockTrendRows, ?string $organisation): array
    {
        $totals = [];
        foreach ($stockTrendRows as $row) {
            if ($organisation && $row['organisation_code'] !== $organisation) {
                continue;
            }
            $date                                        = substr((string) $row['date'], 0, 10);
            $totals[$date][$row['organisation_code']]     = [
                'stock_value'             => (float) $row['stock_value'],
                'percentage_out_of_stock' => $row['percentage_out_of_stock'] === null ? null : (float) $row['percentage_out_of_stock'],
            ];
        }

        ksort($totals);

        $points = [];
        foreach ($totals as $date => $byOrganisation) {
            $points[] = ['period' => $date, 'by_organisation' => $byOrganisation];
        }

        return $points;
    }

    private function pointsFromTotals(array $totals): array
    {
        ksort($totals);

        $points = [];
        foreach ($totals as $period => $byOrganisation) {
            $points[] = ['period' => substr((string) $period, 0, 10), 'by_organisation' => $byOrganisation];
        }

        return $points;
    }

    /**
     * @param  array<int, int>  $orgStockIds
     * @return array<int, array<string, float|null>>
     */
    private function exceptionCalculationDetails(array $orgStockIds): array
    {
        if (!$orgStockIds) {
            return [];
        }

        $covers = GetOrganisationStockCoverBuckets::make();
        $lead   = $covers->leadTimeExpression();

        return DB::table('org_stocks')
            ->join('stocks', 'stocks.id', 'org_stocks.stock_id')
            ->leftJoin('stock_families', 'stock_families.id', 'stocks.stock_family_id')
            ->leftJoin('org_stock_stats', 'org_stock_stats.org_stock_id', 'org_stocks.id')
            ->leftJoinLateral($covers->primarySupplierProduct(), 'sp')
            ->whereIn('org_stocks.id', $orgStockIds)
            ->select(['org_stocks.id'])
            ->selectRaw('org_stock_stats.days_of_cover')
            ->selectRaw("$lead as lead_time_days")
            ->selectRaw($covers->understockDaysExpression($lead).' as understock_days')
            ->selectRaw($covers->overstockDaysExpression().' as overstock_days')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->id => [
                'days_of_cover'   => $row->days_of_cover === null ? null : (float) $row->days_of_cover,
                'lead_time_days'  => (float) $row->lead_time_days,
                'understock_days' => (float) $row->understock_days,
                'overstock_days'  => (float) $row->overstock_days,
            ]])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function statusHistory(?string $organisation, ?string $family, string $search): array
    {
        $query = DB::table('audits')
            ->join('org_stocks', 'org_stocks.id', 'audits.auditable_id')
            ->join('organisations', 'organisations.id', 'org_stocks.organisation_id')
            ->join('stocks', 'stocks.id', 'org_stocks.stock_id')
            ->leftJoin('stock_families', 'stock_families.id', 'stocks.stock_family_id')
            ->leftJoin('users', 'users.id', 'audits.user_id')
            ->where('audits.auditable_type', 'OrgStock')
            ->where('organisations.group_id', $this->group->id)
            ->where(function ($query) {
                $query->where('audits.event', 'state_change')
                    ->orWhere(function ($query) {
                        $query->where('audits.event', 'updated')
                            ->whereRaw('audits.new_values::text like ?', ['%"state"%']);
                    });
            });

        if ($organisation) {
            $query->where('organisations.code', $organisation);
        }
        if ($family) {
            $query->where('stock_families.code', $family);
        }
        if ($search !== '') {
            $query->where(fn ($query) => $query
                ->where('stocks.code', 'ilike', "%{$search}%")
                ->orWhere('stocks.name', 'ilike', "%{$search}%"));
        }

        return $query
            ->orderByDesc('audits.created_at')
            ->limit(50)
            ->select([
                'stocks.code as stock_code',
                'stocks.name as stock_name',
                'organisations.code as organisation_code',
                'audits.old_values',
                'audits.new_values',
                'audits.created_at',
                'users.username',
            ])
            ->get()
            ->map(function ($row) {
                $newValues = json_decode((string) $row->new_values, true) ?? [];
                $oldValues = json_decode((string) $row->old_values, true) ?? [];

                return [
                    'code'         => $row->stock_code,
                    'name'         => $row->stock_name,
                    'organisation' => $row->organisation_code,
                    'from'         => $oldValues['state'] ?? null,
                    'to'           => $newValues['to_state'] ?? $newValues['state'] ?? null,
                    'reason'       => $newValues['reason'] ?? null,
                    'who'          => $newValues['requested_by'] ?? $row->username,
                    'at'           => $row->created_at,
                ];
            })
            ->all();
    }

    /**
     * Everything else the page needs from the Command View dataset, built in one pass over its rows
     * and cells instead of four: exceptions (understocked/overstocked/offline, with the numbers behind
     * the label), urgent actions, read-only promotion candidates and the inbound summary. One pass
     * keeps this section within the warm-request budget on a ~42k row catalogue.
     *
     * @return array{exceptions: array, urgent_actions: array, promotion_candidates: array, inbound: array}
     */
    private function commandInsights(array $rows, ?string $organisation): array
    {
        $today = now()->toDateString();

        $exceptionBuckets = ['low' => [], 'over' => [], 'off' => []];
        $urgent           = ['oos_no_po' => [], 'low_no_po' => [], 'overdue' => [], 'off' => []];
        $candidates       = [];
        $inboundQuantity  = 0.0;
        $inboundValue     = 0.0;

        foreach ($rows as $row) {
            $promotionReasons = [];
            $promotionValue   = 0.0;
            $scoped           = false;

            foreach ($row['organisations'] as $orgCode => $cell) {
                if ($organisation && $orgCode !== $organisation) {
                    continue;
                }
                $scoped = true;

                $item = ['stock_id' => $row['id'], 'code' => $row['code'], 'name' => $row['name'], 'organisation' => $orgCode];

                $exceptionBucket = match ($cell['condition']) {
                    'low'   => 'low',
                    'over'  => 'over',
                    'off'   => 'off',
                    default => null,
                };
                if ($exceptionBucket !== null) {
                    $exceptionBuckets[$exceptionBucket][] = $item + [
                        'org_stock_id' => $cell['org_stock_id'],
                        'available'    => $cell['available'],
                        'stock_value'  => $cell['stock_value'],
                    ];
                }

                if ($cell['condition'] === 'oos' && !$cell['has_po']) {
                    $urgent['oos_no_po'][] = $item;
                }
                if ($cell['condition'] === 'low' && !$cell['has_po']) {
                    $urgent['low_no_po'][] = $item;
                }
                if ($cell['condition'] === 'off') {
                    $urgent['off'][] = $item + ['stock_value' => $cell['stock_value']];
                }
                if ($cell['inbound'] > 0 && $cell['next_expected_at'] !== null && $cell['next_expected_at'] < $today) {
                    $urgent['overdue'][] = $item + [
                        'inbound'          => $cell['inbound'],
                        'next_expected_at' => $cell['next_expected_at'],
                        'days_overdue'     => now()->diffInDays($cell['next_expected_at']),
                    ];
                }

                $promotionValue += $cell['stock_value'] ?? 0;
                if ($cell['condition'] === 'over') {
                    $promotionReasons['overstocked'] = true;
                }
                if ($cell['condition'] === 'dead') {
                    $promotionReasons['dead_stock'] = true;
                }

                if ($cell['inbound'] > 0) {
                    $inboundQuantity += $cell['inbound'];
                    if (($cell['on_hand'] ?? 0) > 0 && $cell['stock_value'] !== null) {
                        $inboundValue += ($cell['stock_value'] / $cell['on_hand']) * $cell['inbound'];
                    }
                }
            }

            if (!$scoped) {
                continue;
            }
            if ($row['state'] === OrgStockStateEnum::DISCONTINUING->value) {
                $promotionReasons['sell_through'] = true;
            }
            if ($promotionReasons) {
                $candidates[] = [
                    'stock_id'    => $row['id'],
                    'code'        => $row['code'],
                    'name'        => $row['name'],
                    'reasons'     => array_keys($promotionReasons),
                    'stock_value' => round($promotionValue, 2),
                ];
            }
        }

        foreach ($exceptionBuckets as $key => $items) {
            usort($items, fn ($a, $b) => ($b['stock_value'] ?? 0) <=> ($a['stock_value'] ?? 0));
            $exceptionBuckets[$key] = array_slice($items, 0, 50);
        }
        $orgStockIds = collect($exceptionBuckets)->flatten(1)->pluck('org_stock_id')->unique()->values()->all();
        $details     = $this->exceptionCalculationDetails($orgStockIds);
        foreach ($exceptionBuckets as $key => $items) {
            $exceptionBuckets[$key] = array_map(fn (array $item) => array_merge($item, $details[$item['org_stock_id']] ?? [
                'days_of_cover'   => null,
                'lead_time_days'  => null,
                'understock_days' => null,
                'overstock_days'  => null,
            ]), $items);
        }

        usort($candidates, fn ($a, $b) => $b['stock_value'] <=> $a['stock_value']);
        usort($urgent['overdue'], fn ($a, $b) => $b['days_overdue'] <=> $a['days_overdue']);

        return [
            'exceptions' => [
                'understocked' => $exceptionBuckets['low'],
                'overstocked'  => $exceptionBuckets['over'],
                'offline'      => $exceptionBuckets['off'],
            ],
            'urgent_actions' => [
                'out_of_stock_no_po' => array_slice($urgent['oos_no_po'], 0, 20),
                'understocked_no_po' => array_slice($urgent['low_no_po'], 0, 20),
                'overdue_inbound'    => array_slice($urgent['overdue'], 0, 20),
                'offline_with_stock' => array_slice($urgent['off'], 0, 20),
                'counts'             => array_map('count', $urgent),
            ],
            'promotion_candidates' => array_slice($candidates, 0, 50),
            'inbound' => [
                'open_quantity'     => round($inboundQuantity, 1),
                'open_value_approx' => round($inboundValue, 2),
                'overdue'           => array_slice($urgent['overdue'], 0, 50),
                'overdue_count'     => count($urgent['overdue']),
            ],
        ];
    }

    public function htmlResponse(array $data, ActionRequest $request): Response
    {
        return Inertia::render(
            'Goods/ProductAnalysis',
            array_merge($data, [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Product Analysis'),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-chart-line'],
                        'title' => __('Product Analysis'),
                    ],
                    'title' => __('Product Analysis'),
                ],
            ])
        );
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowGoodsDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'grp.goods.analysis',
                        ],
                        'label' => __('Analysis'),
                    ],
                ],
            ]
        );
    }
}
