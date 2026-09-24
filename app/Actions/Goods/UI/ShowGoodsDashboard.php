<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 03:14:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\UI;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Actions\Procurement\GetOrganisationStockCoverBuckets;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
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
 * One row per group stock with its stock, condition and state in every organisation that carries it,
 * the group sales of the last 90 days and the KPIs of whatever the filters leave in view.
 *
 * The whole catalogue takes about a second to compute, so it is built once, cached for a few
 * minutes and filtered, sorted and paged in memory; KPIs and rows then always come from the same
 * figures. DiscontinueOrgStocks forgets the cache so a status change shows on the next load.
 */
class ShowGoodsDashboard extends OrgAction
{
    use AsAction;
    use WithInertia;

    private const int PER_PAGE = 50;

    private const int CACHE_MINUTES = 10;

    private const int SALES_DAYS = 90;

    public const array CONDITIONS = ['oos', 'oos_no_po', 'low', 'low_no_po', 'over', 'dead', 'off', 'sell', 'hold', 'ok'];

    private const array SORTS = ['code', 'sales', 'trend', 'cover'];

    public static function cacheKey(int $groupId): string
    {
        return 'product-command-control:'.$groupId;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo([GroupPermissionsEnum::SUPPLY_CHAIN->value, GroupPermissionsEnum::SUPPLY_CHAIN_EDIT->value]);

        return $request->user()->authTo('goods.view');
    }

    public function rules(): array
    {
        return [
            'organisation' => ['sometimes', 'nullable', 'string'],
            'family'       => ['sometimes', 'nullable', 'string'],
            'condition'    => ['sometimes', 'nullable', Rule::in(self::CONDITIONS)],
            'state'        => ['sometimes', 'nullable', Rule::in(OrgStockStateEnum::values())],
            'search'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort'         => ['sometimes', 'nullable', Rule::in(array_merge(self::SORTS, array_map(fn ($sort) => '-'.$sort, self::SORTS)))],
            'page'         => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->validatedData);
    }

    public function handle(array $filters): array
    {
        // ponytail: the whole catalogue lives in one gzipped cache entry (~1.5MB); move to a table if the catalogue grows tenfold
        $dataset = json_decode(gzuncompress(Cache::remember(
            self::cacheKey($this->group->id),
            now()->addMinutes(self::CACHE_MINUTES),
            fn () => gzcompress(json_encode($this->buildDataset()))
        )), true);

        $organisation = Arr::get($filters, 'organisation');
        $condition    = Arr::get($filters, 'condition');
        $sort         = Arr::get($filters, 'sort') ?: '-sales';

        $inView = array_values(array_filter($dataset['rows'], fn (array $row) => $this->matches($row, $filters)));
        $rows   = $condition ? array_values(array_filter($inView, fn (array $row) => $this->hasCondition($row, $condition, $organisation))) : $inView;
        $this->sort($rows, $sort);

        $total    = count($rows);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page     = min(max(1, (int) Arr::get($filters, 'page', 1)), $lastPage);

        return [
            'built_at'      => $dataset['built_at'],
            'organisations' => $dataset['organisations'],
            'families'      => $dataset['families'],
            'missing_rates' => $dataset['missing_rates'],
            'kpis'          => $this->kpis($inView, $organisation),
            'rows'          => array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'pagination'    => [
                'page'      => $page,
                'last_page' => $lastPage,
                'total'     => $total,
                'per_page'  => self::PER_PAGE,
            ],
            'filters'       => [
                'organisation' => $organisation,
                'family'       => Arr::get($filters, 'family'),
                'condition'    => $condition,
                'state'        => Arr::get($filters, 'state'),
                'search'       => Arr::get($filters, 'search'),
                'sort'         => $sort,
            ],
        ];
    }

    private function buildDataset(): array
    {
        $organisations = Organisation::where('group_id', $this->group->id)
            ->whereHas('orgStocks', fn ($query) => $query->where('state', OrgStockStateEnum::ACTIVE->value))
            ->with(['currency', 'warehouses'])
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $rates = $organisations->mapWithKeys(fn (Organisation $organisation) => [
            $organisation->id => GetCurrencyExchange::run($organisation->currency, $this->group->currency),
        ]);

        $rows = $this->orgStockCells($organisations->keys()->all())
            ->groupBy('stock_id')
            ->map(fn (Collection $cells) => $this->row($cells, $organisations, $rates))
            ->values();

        return [
            'built_at'      => now()->toIso8601String(),
            'organisations' => $organisations->map(fn (Organisation $organisation) => [
                'code' => $organisation->code,
                'name' => $organisation->name,
            ])->values()->all(),
            'families'      => $rows->pluck('family_code')->filter()->unique()->sort()->values()->all(),
            'missing_rates' => $organisations->filter(fn (Organisation $organisation) => $rates->get($organisation->id) === null)->pluck('code')->values()->all(),
            'rows'          => $rows->all(),
        ];
    }

    private function orgStockCells(array $organisationIds): Collection
    {
        $covers = GetOrganisationStockCoverBuckets::make();
        $since  = now()->subDays(self::SALES_DAYS)->startOfDay();

        $sales = DB::table('org_stock_time_series')
            ->join('org_stock_time_series_records', function ($join) use ($since) {
                $join->on('org_stock_time_series_records.org_stock_time_series_id', 'org_stock_time_series.id')
                    ->where('org_stock_time_series_records.frequency', TimeSeriesFrequencyEnum::DAILY->singleLetter())
                    ->where('org_stock_time_series_records.from', '>=', $since->copy()->subDays(self::SALES_DAYS));
            })
            ->where('org_stock_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->groupBy('org_stock_time_series.org_stock_id')
            ->select('org_stock_time_series.org_stock_id')
            ->selectRaw('sum(org_stock_time_series_records.sales_grp_currency_external) filter (where org_stock_time_series_records.from >= ?) as sales', [$since])
            ->selectRaw('sum(org_stock_time_series_records.sales_grp_currency_external) filter (where org_stock_time_series_records.from < ?) as sales_previous', [$since]);

        $isForSale = DB::table('product_has_org_stocks')
            ->join('products', 'products.id', 'product_has_org_stocks.product_id')
            ->join('shops', 'shops.id', 'products.shop_id')
            ->whereColumn('product_has_org_stocks.org_stock_id', 'org_stocks.id')
            ->where('products.is_for_sale', true)
            ->whereNull('products.deleted_at')
            ->where('shops.state', 'open')
            ->selectRaw('1');

        return DB::table('org_stocks')
            ->join('stocks', 'stocks.id', 'org_stocks.stock_id')
            ->leftJoin('stock_families', 'stock_families.id', 'stocks.stock_family_id')
            ->leftJoin('org_stock_stats', 'org_stock_stats.org_stock_id', 'org_stocks.id')
            ->leftJoinLateral($covers->primarySupplierProduct(), 'sp')
            ->leftJoinSub($sales, 'sales', 'sales.org_stock_id', 'org_stocks.id')
            ->whereIn('org_stocks.organisation_id', $organisationIds)
            ->whereNull('org_stocks.deleted_at')
            ->whereIn('org_stocks.state', [OrgStockStateEnum::ACTIVE->value, OrgStockStateEnum::DISCONTINUING->value, OrgStockStateEnum::SUSPENDED->value])
            ->select([
                'org_stocks.id',
                'org_stocks.stock_id',
                'org_stocks.organisation_id',
                'org_stocks.slug',
                'org_stocks.state',
                'org_stocks.is_on_demand',
                'org_stocks.quantity_available',
                'stocks.code',
                'stocks.name',
                'stock_families.code as family_code',
                'org_stock_stats.stock_value',
                'org_stock_stats.stock_commercial_value',
                'org_stock_stats.predicted_daily_usage',
                'org_stock_stats.on_the_way_po_count',
                'sales.sales',
                'sales.sales_previous',
            ])
            ->selectRaw($covers->bucketExpression().' as bucket')
            ->selectRaw('exists ('.$isForSale->toSql().') as is_for_sale', $isForSale->getBindings())
            ->get();
    }

    private function row(Collection $cells, Collection $organisations, Collection $rates): array
    {
        $first         = $cells->first();
        $sales         = (float) $cells->sum('sales');
        $salesPrevious = (float) $cells->sum('sales_previous');
        $dailyUsage    = (float) $cells->sum('predicted_daily_usage');
        $available     = (float) $cells->sum(fn ($cell) => max(0, (float) $cell->quantity_available));
        $actionCell    = $cells->sortBy('organisation_id')->first();
        $actionOrg     = $organisations->get($actionCell->organisation_id);

        return [
            'id'             => $first->stock_id,
            'code'           => $first->code,
            'name'           => $first->name,
            'family_code'    => $first->family_code,
            'sales'          => round($sales, 2),
            'trend'          => $salesPrevious > 0 ? round(($sales - $salesPrevious) / $salesPrevious * 100) : null,
            'cover_weeks'    => $dailyUsage > 0 ? round($available / $dailyUsage / 7, 1) : null,
            'state'          => $cells->countBy('state')->sortDesc()->keys()->first(),
            'organisations'  => $cells->mapWithKeys(function ($cell) use ($organisations, $rates) {
                $rate = $rates->get($cell->organisation_id);

                return [
                    $organisations->get($cell->organisation_id)->code => [
                        'state'            => $cell->state,
                        'available'        => (float) $cell->quantity_available,
                        'condition'        => $this->condition($cell),
                        'has_po'           => (int) $cell->on_the_way_po_count > 0,
                        'stock_value'      => $rate === null ? null : round((float) $cell->stock_value * $rate, 2),
                        'commercial_value' => $rate === null ? null : round((float) $cell->stock_commercial_value * $rate, 2),
                    ],
                ];
            })->all(),
            'action'         => [
                'org_stock_id' => $actionCell->id,
                'organisation' => $actionOrg->slug,
                'warehouse'    => $actionOrg->warehouses->first()?->slug,
            ],
        ];
    }

    private function condition(object $cell): string
    {
        return match (true) {
            $cell->state === OrgStockStateEnum::DISCONTINUING->value => 'sell',
            $cell->state === OrgStockStateEnum::SUSPENDED->value     => 'hold',
            (bool) $cell->is_on_demand                               => 'ok',
            $cell->bucket === 'out'                                  => 'oos',
            !$cell->is_for_sale                                      => 'off',
            in_array($cell->bucket, ['w1', 'w2'])                    => 'low',
            $cell->bucket === 'excess'                               => 'over',
            $cell->bucket === 'dead'                                 => 'dead',
            default                                                  => 'ok',
        };
    }

    private function scopedCells(array $row, ?string $organisation): array
    {
        return $organisation ? Arr::only($row['organisations'], [$organisation]) : $row['organisations'];
    }

    private function matches(array $row, array $filters): bool
    {
        $organisation = Arr::get($filters, 'organisation');
        $family       = Arr::get($filters, 'family');
        $state        = Arr::get($filters, 'state');
        $search       = trim((string) Arr::get($filters, 'search'));

        return (!$organisation || isset($row['organisations'][$organisation]))
            && (!$family || $row['family_code'] === $family)
            && (!$state || $row['state'] === $state)
            && ($search === '' || Str::contains($row['code'].' '.$row['name'].' '.$row['family_code'], $search, ignoreCase: true));
    }

    private function hasCondition(array $row, string $condition, ?string $organisation): bool
    {
        foreach ($this->scopedCells($row, $organisation) as $cell) {
            $matches = match ($condition) {
                'oos_no_po' => $cell['condition'] === 'oos' && !$cell['has_po'],
                'low_no_po' => $cell['condition'] === 'low' && !$cell['has_po'],
                default     => $cell['condition'] === $condition,
            };
            if ($matches) {
                return true;
            }
        }

        return false;
    }

    private function sort(array &$rows, string $sort): void
    {
        $descending = str_starts_with($sort, '-');
        $field      = ['code' => 'code', 'sales' => 'sales', 'trend' => 'trend', 'cover' => 'cover_weeks'][ltrim($sort, '-')];

        usort($rows, function (array $a, array $b) use ($field, $descending) {
            if ($a[$field] === null || $b[$field] === null) {
                return ($a[$field] === null) <=> ($b[$field] === null);
            }

            $comparison = $field === 'code' ? strnatcasecmp($a[$field], $b[$field]) : $a[$field] <=> $b[$field];

            return $descending ? -$comparison : $comparison;
        });
    }

    private function kpis(array $rows, ?string $organisation): array
    {
        $kpis = [
            'stock_value'             => 0.0,
            'commercial_value'        => 0.0,
            'out_of_stock'            => 0,
            'out_of_stock_without_po' => 0,
            'low'                     => 0,
            'low_without_po'          => 0,
            'overstock'               => 0,
            'overstock_value'         => 0.0,
            'offline'                 => 0,
            'offline_value'           => 0.0,
        ];

        foreach ($rows as $row) {
            $flags = [];
            foreach ($this->scopedCells($row, $organisation) as $cell) {
                $kpis['stock_value']      += $cell['stock_value'] ?? 0;
                $kpis['commercial_value'] += $cell['commercial_value'] ?? 0;

                $flags[$cell['condition']] = true;
                if (!$cell['has_po']) {
                    $flags[$cell['condition'].'_no_po'] = true;
                }
                if ($cell['condition'] === 'over') {
                    $kpis['overstock_value'] += $cell['stock_value'] ?? 0;
                }
                if ($cell['condition'] === 'off') {
                    $kpis['offline_value'] += $cell['stock_value'] ?? 0;
                }
            }

            $kpis['out_of_stock']            += (int) isset($flags['oos']);
            $kpis['out_of_stock_without_po'] += (int) isset($flags['oos_no_po']);
            $kpis['low']                     += (int) isset($flags['low']);
            $kpis['low_without_po']          += (int) isset($flags['low_no_po']);
            $kpis['overstock']               += (int) isset($flags['over']);
            $kpis['offline']                 += (int) isset($flags['off']);
        }

        return $kpis;
    }

    public function htmlResponse(array $data, ActionRequest $request): Response
    {
        return Inertia::render(
            'Goods/ProductCommandControl',
            array_merge($data, [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Product Command & Control'),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-sliders-h'],
                        'title' => __('Product Command & Control'),
                    ],
                    'title' => __('Product Command & Control'),
                ],
                'currency_code' => $this->group->currency->code,
                'can_edit'      => $this->canEdit,
            ])
        );
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'grp.goods.dashboard',
                        ],
                        'label' => __('Goods'),
                    ],
                ],
            ]
        );
    }
}
