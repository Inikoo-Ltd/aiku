<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 03:14:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\UI;

use App\Actions\Inventory\OrgStock\DiscontinueOrgStocks;
use App\Actions\OrgAction;
use App\Actions\Procurement\GetOrganisationStockCoverBuckets;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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
 * the group sales of a selectable period and the KPIs of whatever the filters leave in view.
 *
 * The whole catalogue takes a few seconds to compute, so it is built once, cached for a few minutes
 * and filtered, sorted and paged in memory; KPIs and rows then always come from the same figures.
 * DiscontinueOrgStocks forgets the cache so a status change shows on the next load. Editing rights
 * depend on the requesting user and are never cached.
 */
class ShowGoodsDashboard extends OrgAction
{
    use AsAction;
    use WithInertia;

    private const int PER_PAGE = 50;

    private const int CACHE_MINUTES = 10;

    public const array DELIVERY_DAYS_TO_ARRIVE = [
        StockDeliveryStateEnum::CONFIRMED->value     => 21,
        StockDeliveryStateEnum::READY_TO_SHIP->value => 14,
        StockDeliveryStateEnum::DISPATCHED->value    => 7,
        StockDeliveryStateEnum::RECEIVED->value      => 3,
        StockDeliveryStateEnum::CHECKED->value       => 2,
        StockDeliveryStateEnum::BOOKING_IN->value    => 1,
    ];

    public const int DEFAULT_LEAD_TIME_DAYS = 14;

    private const int ID_CHUNK_SIZE = 10000;

    public const array PERIODS = ['30d', '90d', 'quarter', 'year'];

    public const string DEFAULT_PERIOD = '90d';

    public const array CONDITIONS = ['oos', 'oos_no_po', 'ns', 'low', 'low_no_po', 'over', 'dead', 'off', 'sell', 'hold', 'ret', 'ok'];

    public const array SORTS = ['code', 'sales', 'trend', 'cover'];

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
            'period'       => ['sometimes', 'nullable', Rule::in(self::PERIODS)],
            'page'         => ['sometimes', 'nullable', 'integer', 'min:1'],
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

    public function handle(array $filters): array
    {
        ['dataset' => $dataset, 'period' => $period, 'inView' => $inView, 'filtered' => $rows] = $this->rowsForFilters($filters);

        $organisation = Arr::get($filters, 'organisation');
        $total        = count($rows);
        $lastPage     = max(1, (int) ceil($total / self::PER_PAGE));
        $page         = min(max(1, (int) Arr::get($filters, 'page', 1)), $lastPage);
        $pageRows     = array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        [$editableOrganisations, $canChangeGroup] = $this->editingRights();

        $organisationsMeta = collect($dataset['organisations']);
        foreach ($pageRows as &$row) {
            $row['action'] = $this->rowAction($row, $organisationsMeta, $editableOrganisations, $canChangeGroup);
        }
        unset($row);

        return [
            'built_at'      => $dataset['built_at'],
            'organisations' => $dataset['organisations'],
            'families'      => $dataset['families'],
            'missing_rates' => $dataset['missing_rates'],
            'rates'         => $dataset['rates'],
            'period'        => $period,
            'periods'       => self::PERIODS,
            'kpis'          => $this->kpis($inView, $organisation),
            'rows'          => $pageRows,
            'pagination'    => [
                'page'      => $page,
                'last_page' => $lastPage,
                'total'     => $total,
                'per_page'  => self::PER_PAGE,
            ],
            'filters'       => [
                'organisation' => $organisation,
                'family'       => Arr::get($filters, 'family'),
                'condition'    => Arr::get($filters, 'condition'),
                'state'        => Arr::get($filters, 'state'),
                'search'       => Arr::get($filters, 'search'),
                'sort'         => Arr::get($filters, 'sort') ?: '-sales',
                'period'       => $period,
            ],
            'editable_organisations' => $editableOrganisations,
            'can_change_group'       => $canChangeGroup,
        ];
    }

    /**
     * All rows matching the filters and period, unpaginated and with no per-row action, for the
     * export action to stream. The action must set $this->group itself via initialisationFromGroup.
     *
     * @return array{organisations: array, rows: array}
     */
    public function exportData(array $filters): array
    {
        $result = $this->rowsForFilters($filters);

        return ['organisations' => $result['dataset']['organisations'], 'rows' => $result['filtered']];
    }

    /**
     * @return array{dataset: array, period: string, inView: array, filtered: array}
     */
    private function rowsForFilters(array $filters): array
    {
        $dataset = $this->dataset();
        $period  = Arr::get($filters, 'period') ?: self::DEFAULT_PERIOD;
        $rows    = $this->flattenPeriod($dataset['rows'], $period);

        $inView = array_values(array_filter($rows, fn (array $row) => $this->matches($row, $filters)));

        $condition    = Arr::get($filters, 'condition');
        $organisation = Arr::get($filters, 'organisation');
        $filtered     = $condition ? array_values(array_filter($inView, fn (array $row) => $this->hasCondition($row, $condition, $organisation))) : $inView;

        $this->sort($filtered, Arr::get($filters, 'sort') ?: '-sales');

        return compact('dataset', 'period', 'inView', 'filtered');
    }

    /**
     * The decoded, cached catalogue this page is built from: one row per group stock with its
     * conditions, sales and organisation cells. ShowGoodsAnalysis reuses this instead of duplicating
     * the query so exceptions, urgent actions and promotion candidates always agree with this page.
     *
     * ponytail: the whole catalogue lives in one gzipped cache entry; move to a table if it grows tenfold
     */
    public function dataset(): array
    {
        return json_decode(gzuncompress(Cache::remember(
            self::cacheKey($this->group->id),
            now()->addMinutes(self::CACHE_MINUTES),
            fn () => gzcompress(json_encode($this->buildDataset()))
        )), true);
    }

    private function flattenPeriod(array $rows, string $period): array
    {
        return array_map(function (array $row) use ($period) {
            $row['sales'] = $row['sales_by_period'][$period]['amount'];
            $row['trend'] = $row['sales_by_period'][$period]['trend'];
            unset($row['sales_by_period']);

            return $row;
        }, $rows);
    }

    /**
     * @return array{0: array<int, string>, 1: bool}
     */
    private function editingRights(): array
    {
        $user = Auth::user();

        if (!$user) {
            return [[], false];
        }

        $canChangeGroup = DiscontinueOrgStocks::canChangeGroupStatus($user);

        $editable = Organisation::where('group_id', $this->group->id)
            ->get(['id', 'code'])
            ->filter(fn (Organisation $organisation) => DiscontinueOrgStocks::canChangeStatus($user, $organisation))
            ->pluck('code')
            ->values()
            ->all();

        return [$editable, $canChangeGroup];
    }

    private function rowAction(array $row, Collection $organisationsMeta, array $editableOrganisations, bool $canChangeGroup): ?array
    {
        foreach ($organisationsMeta as $organisation) {
            $cell = $row['organisations'][$organisation['code']] ?? null;
            if (!$cell) {
                continue;
            }
            if ($canChangeGroup || in_array($organisation['code'], $editableOrganisations, true)) {
                return [
                    'org_stock_id' => $cell['org_stock_id'],
                    'organisation' => $organisation['slug'],
                    'warehouse'    => $organisation['warehouse_slug'],
                ];
            }
        }

        return null;
    }

    private function buildDataset(): array
    {
        $organisations = Organisation::where('group_id', $this->group->id)
            ->whereHas('orgStocks', fn ($query) => $query->whereIn('state', [
                OrgStockStateEnum::ACTIVE->value,
                OrgStockStateEnum::DISCONTINUING->value,
                OrgStockStateEnum::SUSPENDED->value,
                OrgStockStateEnum::DISCONTINUED->value,
            ]))
            ->with(['currency', 'warehouses'])
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $rates = $this->currencyRates($organisations, $this->group->currency);

        $cells   = $this->orgStockCells($organisations->keys()->all());
        $inbound = $this->inboundByOrgStock($cells->pluck('id')->all());

        $cellsByStock = [];
        foreach ($cells as $cell) {
            $cell->inbound_quantity = $inbound[$cell->id]['quantity'] ?? 0.0;
            $cell->next_expected_at = $inbound[$cell->id]['eta'] ?? null;
            $cellsByStock[$cell->stock_id][] = $cell;
        }

        $organisationsSorted = $organisations->sortBy('id')->values();

        $rows = collect($cellsByStock)
            ->map(fn (array $stockCells) => $this->row($stockCells, $organisationsSorted, $rates))
            ->values();

        return [
            'built_at'      => now()->toIso8601String(),
            'organisations' => $organisations->map(fn (Organisation $organisation) => [
                'code'           => $organisation->code,
                'name'           => $organisation->name,
                'slug'           => $organisation->slug,
                'warehouse_slug' => $organisation->warehouses->first()?->slug,
            ])->values()->all(),
            'families'      => $rows->pluck('family_code')->filter()->unique()->sort()->values()->all(),
            'missing_rates' => $organisations->filter(fn (Organisation $organisation) => ($rates->get($organisation->id)['rate'] ?? null) === null)
                ->pluck('code')->values()->all(),
            'rates'         => $organisations->map(fn (Organisation $organisation) => array_merge(
                ['organisation' => $organisation->code, 'currency' => $organisation->currency->code],
                $rates->get($organisation->id)
            ))->values()->all(),
            'rows'          => $rows->all(),
        ];
    }

    /**
     * @return Collection<int, array{rate: float|null, date: string|null, source: string|null}>
     */
    private function currencyRates(Collection $organisations, Currency $groupCurrency): Collection
    {
        $pivotCode   = config('app.currency_exchange.pivot');
        $currencyIds = $organisations->pluck('currency_id')->push($groupCurrency->id)->unique()->filter()->values();

        $latest = DB::table('currency_exchanges')
            ->whereIn('currency_id', $currencyIds)
            ->orderByDesc('date')
            ->get()
            ->unique('currency_id')
            ->keyBy('currency_id');

        $groupExchange = $groupCurrency->code === $pivotCode ? 1.0 : (float) ($latest->get($groupCurrency->id)->exchange ?? 0);

        return $organisations->mapWithKeys(function (Organisation $organisation) use ($latest, $pivotCode, $groupExchange, $groupCurrency) {
            if ($organisation->currency->code === $groupCurrency->code) {
                return [$organisation->id => ['rate' => 1.0, 'date' => null, 'source' => null]];
            }

            $row         = $latest->get($organisation->currency_id);
            $orgExchange = $organisation->currency->code === $pivotCode ? 1.0 : (float) ($row->exchange ?? 0);

            if (!$orgExchange || !$groupExchange) {
                return [$organisation->id => ['rate' => null, 'date' => null, 'source' => null]];
            }

            return [$organisation->id => [
                'rate'   => round($groupExchange / $orgExchange, 6),
                'date'   => $row->date ?? null,
                'source' => $row->source ?? null,
            ]];
        });
    }

    private function salesSubquery(): Builder
    {
        $now         = now();
        $d30         = $now->copy()->subDays(30)->startOfDay();
        $d30Prev     = $now->copy()->subDays(60)->startOfDay();
        $d90         = $now->copy()->subDays(90)->startOfDay();
        $d90Prev     = $now->copy()->subDays(180)->startOfDay();
        $dYear       = $now->copy()->subDays(365)->startOfDay();
        $dYearPrev   = $now->copy()->subDays(730)->startOfDay();
        $d180        = $now->copy()->subDays(180)->startOfDay();
        $quarterStart = $now->copy()->startOfQuarter()->startOfDay();
        $daysElapsed  = (int) $quarterStart->diffInDays($now->copy()->startOfDay()) + 1;
        $quarterPrevStart = $quarterStart->copy()->subQuarterNoOverflow();
        $quarterPrevEnd   = $quarterPrevStart->copy()->addDays($daysElapsed);

        return DB::table('org_stock_time_series')
            ->join('org_stock_time_series_records as r', function ($join) use ($dYearPrev) {
                $join->on('r.org_stock_time_series_id', 'org_stock_time_series.id')
                    ->where('r.frequency', TimeSeriesFrequencyEnum::DAILY->singleLetter())
                    ->where('r.from', '>=', $dYearPrev);
            })
            ->where('org_stock_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->groupBy('org_stock_time_series.org_stock_id')
            ->select('org_stock_time_series.org_stock_id')
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external) filter (where r.from >= ?), 0) as sales_30d', [$d30])
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external) filter (where r.from >= ? and r.from < ?), 0) as sales_30d_prev', [$d30Prev, $d30])
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external) filter (where r.from >= ?), 0) as sales_90d', [$d90])
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external) filter (where r.from >= ? and r.from < ?), 0) as sales_90d_prev', [$d90Prev, $d90])
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external) filter (where r.from >= ?), 0) as sales_year', [$dYear])
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external) filter (where r.from >= ? and r.from < ?), 0) as sales_year_prev', [$dYearPrev, $dYear])
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external) filter (where r.from >= ?), 0) as sales_quarter', [$quarterStart])
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external) filter (where r.from >= ? and r.from < ?), 0) as sales_quarter_prev', [$quarterPrevStart, $quarterPrevEnd])
            ->selectRaw('coalesce(sum(r.sales_grp_currency_external) filter (where r.from >= ?), 0) as sales_180d', [$d180]);
    }

    private function orgStockCells(array $organisationIds): Collection
    {
        $covers = GetOrganisationStockCoverBuckets::make();
        $sales  = $this->salesSubquery();

        $isForSale = DB::table('product_has_org_stocks')
            ->join('products', 'products.id', 'product_has_org_stocks.product_id')
            ->join('shops', 'shops.id', 'products.shop_id')
            ->whereColumn('product_has_org_stocks.org_stock_id', 'org_stocks.id')
            ->where('products.is_for_sale', true)
            ->whereNull('products.deleted_at')
            ->where('shops.state', 'open')
            ->selectRaw('1');

        $isRawMaterial = DB::table('raw_materials')
            ->whereColumn('raw_materials.org_stock_id', 'org_stocks.id')
            ->whereNull('raw_materials.deleted_at')
            ->selectRaw('1');

        return DB::table('org_stocks')
            ->join('stocks', 'stocks.id', 'org_stocks.stock_id')
            ->leftJoin('stock_families', 'stock_families.id', 'stocks.stock_family_id')
            ->leftJoin('org_stock_stats', 'org_stock_stats.org_stock_id', 'org_stocks.id')
            ->leftJoinLateral($covers->primarySupplierProduct(), 'sp')
            ->leftJoinSub($sales, 'sales', 'sales.org_stock_id', 'org_stocks.id')
            ->whereIn('org_stocks.organisation_id', $organisationIds)
            ->whereNull('org_stocks.deleted_at')
            ->whereIn('org_stocks.state', [
                OrgStockStateEnum::ACTIVE->value,
                OrgStockStateEnum::DISCONTINUING->value,
                OrgStockStateEnum::SUSPENDED->value,
                OrgStockStateEnum::DISCONTINUED->value,
            ])
            ->select([
                'org_stocks.id',
                'org_stocks.stock_id',
                'org_stocks.organisation_id',
                'org_stocks.slug',
                'org_stocks.state',
                'org_stocks.is_on_demand',
                'org_stocks.quantity_available',
                'org_stocks.quantity_in_locations',
                'stocks.slug',
                'stocks.code',
                'stocks.name',
                'stock_families.code as family_code',
                'org_stock_stats.stock_value',
                'org_stock_stats.stock_commercial_value',
                'org_stock_stats.predicted_daily_usage',
                'sales.sales_30d',
                'sales.sales_30d_prev',
                'sales.sales_90d',
                'sales.sales_90d_prev',
                'sales.sales_quarter',
                'sales.sales_quarter_prev',
                'sales.sales_year',
                'sales.sales_year_prev',
                'sales.sales_180d',
            ])
            ->selectRaw($covers->bucketExpression().' as bucket')
            ->selectRaw('exists ('.$isForSale->toSql().') as is_for_sale', $isForSale->getBindings())
            ->selectRaw('exists ('.$isRawMaterial->toSql().') as is_raw_material', $isRawMaterial->getBindings())
            ->get();
    }

    /**
     * @param  array<int, int>  $orgStockIds
     * @return array<int, array{quantity: float, eta: string|null}>
     */
    private function inboundByOrgStock(array $orgStockIds): array
    {
        if (!$orgStockIds) {
            return [];
        }

        $inbound = [];
        foreach (array_merge($this->stockDeliveryInboundLines($orgStockIds), $this->purchaseOrderInboundLines($orgStockIds)) as $line) {
            $id = $line['org_stock_id'];
            $inbound[$id]['quantity'] = ($inbound[$id]['quantity'] ?? 0.0) + $line['quantity'];
            if ($line['eta'] !== null && (!isset($inbound[$id]['eta']) || $line['eta'] < $inbound[$id]['eta'])) {
                $inbound[$id]['eta'] = $line['eta'];
            }
        }

        return $inbound;
    }

    /**
     * @param  array<int, int>  $orgStockIds
     * @return array<int, array{org_stock_id: int, quantity: float, eta: string}>
     */
    private function stockDeliveryInboundLines(array $orgStockIds): array
    {
        $lines = [];

        foreach (array_chunk($orgStockIds, self::ID_CHUNK_SIZE) as $chunk) {
            $lines = array_merge($lines, DB::table('stock_delivery_items')
                ->join('stock_deliveries', 'stock_deliveries.id', 'stock_delivery_items.stock_delivery_id')
                ->whereIn('stock_delivery_items.org_stock_id', $chunk)
                ->whereNull('stock_delivery_items.deleted_at')
                ->whereNull('stock_deliveries.deleted_at')
                ->whereIn('stock_deliveries.state', array_keys(self::DELIVERY_DAYS_TO_ARRIVE))
                ->select([
                    'stock_delivery_items.org_stock_id',
                    'stock_deliveries.state',
                    DB::raw('(stock_delivery_items.unit_quantity - coalesce(stock_delivery_items.unit_quantity_placed, 0)) as quantity'),
                ])
                ->get()
                ->filter(fn ($row) => $row->quantity > 0)
                ->map(fn ($row) => [
                    'org_stock_id' => $row->org_stock_id,
                    'quantity'     => (float) $row->quantity,
                    'eta'          => now()->addDays(self::DELIVERY_DAYS_TO_ARRIVE[$row->state])->toDateString(),
                ])
                ->all());
        }

        return $lines;
    }

    /**
     * PO lines are dropped once their purchase order already has a stock delivery, either through
     * the (often empty, for agent deliveries) pivot or a stock delivery sharing its reference in the
     * same organisation, so nothing already counted above is counted twice.
     *
     * @param  array<int, int>  $orgStockIds
     * @return array<int, array{org_stock_id: int, quantity: float, eta: string|null}>
     */
    private function purchaseOrderInboundLines(array $orgStockIds): array
    {
        $lines = [];

        foreach (array_chunk($orgStockIds, self::ID_CHUNK_SIZE) as $chunk) {
            $lines = array_merge($lines, DB::table('purchase_order_transactions')
                ->join('purchase_orders', 'purchase_orders.id', 'purchase_order_transactions.purchase_order_id')
                ->join('org_stocks', 'org_stocks.id', 'purchase_order_transactions.org_stock_id')
                ->whereIn('purchase_order_transactions.org_stock_id', $chunk)
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
                ->whereNotIn('purchase_orders.id', function ($query) {
                    $query->select('purchase_order_stock_delivery.purchase_order_id')
                        ->from('purchase_order_stock_delivery')
                        ->join('stock_deliveries', 'stock_deliveries.id', 'purchase_order_stock_delivery.stock_delivery_id')
                        ->whereNull('stock_deliveries.deleted_at')
                        ->whereNotIn('stock_deliveries.state', [
                            StockDeliveryStateEnum::IN_PROCESS->value,
                            StockDeliveryStateEnum::CANCELLED->value,
                            StockDeliveryStateEnum::NOT_RECEIVED->value,
                        ]);
                })
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('stock_deliveries as sd')
                        ->whereColumn('sd.organisation_id', 'purchase_orders.organisation_id')
                        ->whereColumn('sd.reference', 'purchase_orders.reference')
                        ->whereNull('sd.deleted_at')
                        ->whereNotIn('sd.state', [
                            StockDeliveryStateEnum::IN_PROCESS->value,
                            StockDeliveryStateEnum::CANCELLED->value,
                            StockDeliveryStateEnum::NOT_RECEIVED->value,
                        ]);
                })
                ->select([
                    'purchase_order_transactions.org_stock_id',
                    'purchase_orders.submitted_at',
                    'purchase_orders.estimated_received_at',
                    'org_stocks.measured_lead_time_days',
                    DB::raw('(coalesce(purchase_order_transactions.quantity_ordered, 0) - coalesce(purchase_order_transactions.quantity_cancelled, 0)) as quantity'),
                ])
                ->get()
                ->filter(fn ($row) => $row->quantity > 0)
                ->map(fn ($row) => [
                    'org_stock_id' => $row->org_stock_id,
                    'quantity'     => (float) $row->quantity,
                    'eta'          => $this->purchaseOrderEta($row),
                ])
                ->all());
        }

        return $lines;
    }

    private function purchaseOrderEta(object $row): ?string
    {
        $eta = $row->estimated_received_at
            ? \Illuminate\Support\Carbon::parse($row->estimated_received_at)
            : ($row->submitted_at
                ? \Illuminate\Support\Carbon::parse($row->submitted_at)->addDays((int) ($row->measured_lead_time_days ?? self::DEFAULT_LEAD_TIME_DAYS))
                : null);

        if (!$eta) {
            return null;
        }

        return $eta->max(now()->addDay())->toDateString();
    }

    private function periodColumn(string $period): string
    {
        return match ($period) {
            '30d'     => 'sales_30d',
            '90d'     => 'sales_90d',
            'quarter' => 'sales_quarter',
            'year'    => 'sales_year',
        };
    }

    /**
     * Plain arrays and foreach throughout: this runs once per stock in the whole catalogue
     * (tens of thousands of times), where Collection closures measurably added seconds.
     *
     * @param  array<int, object>  $cells
     */
    private function row(array $cells, Collection $organisationsSorted, Collection $rates): array
    {
        $first = $cells[0];

        [$state, $allRetired] = $this->groupState($cells);

        $sums = [];
        foreach (self::PERIODS as $period) {
            $column          = $this->periodColumn($period);
            $prevColumn      = $column.'_prev';
            $sums[$column]     = 0.0;
            $sums[$prevColumn] = 0.0;
        }
        $dailyUsage = 0.0;
        $available  = 0.0;

        $cellsByOrgId = [];
        foreach ($cells as $cell) {
            foreach (self::PERIODS as $period) {
                $column     = $this->periodColumn($period);
                $prevColumn = $column.'_prev';
                $sums[$column]     += (float) $cell->{$column};
                $sums[$prevColumn] += (float) $cell->{$prevColumn};
            }
            $dailyUsage += (float) $cell->predicted_daily_usage;
            $available  += max(0.0, (float) $cell->quantity_available);
            $cellsByOrgId[$cell->organisation_id] = $cell;
        }

        $salesByPeriod = [];
        foreach (self::PERIODS as $period) {
            $column   = $this->periodColumn($period);
            $current  = $sums[$column];
            $previous = $sums[$column.'_prev'];
            $salesByPeriod[$period] = [
                'amount' => round($current, 2),
                'trend'  => $previous > 0 ? round(($current - $previous) / $previous * 100) : null,
            ];
        }

        $organisationsOut = [];
        foreach ($organisationsSorted as $organisation) {
            $cell = $cellsByOrgId[$organisation->id] ?? null;
            if (!$cell) {
                continue;
            }

            $rate = $rates->get($organisation->id)['rate'] ?? null;

            $organisationsOut[$organisation->code] = [
                'org_stock_id'     => $cell->id,
                'state'            => $cell->state,
                'on_hand'          => (float) $cell->quantity_in_locations,
                'available'        => (float) $cell->quantity_available,
                'allocated'        => max(0.0, (float) $cell->quantity_in_locations - (float) $cell->quantity_available),
                'inbound'          => (float) $cell->inbound_quantity,
                'next_expected_at' => $cell->next_expected_at,
                'has_po'           => (float) $cell->inbound_quantity > 0,
                'condition'        => $this->condition($cell),
                'stock_value'      => $rate === null ? null : round((float) $cell->stock_value * $rate, 2),
                'commercial_value' => $rate === null ? null : round((float) $cell->stock_commercial_value * $rate, 2),
            ];
        }

        return [
            'id'              => $first->stock_id,
            'slug'            => $first->slug,
            'code'            => $first->code,
            'name'            => $first->name,
            'family_code'     => $first->family_code,
            'sales_by_period' => $salesByPeriod,
            'cover_weeks'     => $dailyUsage > 0 ? round($available / $dailyUsage / 7, 1) : null,
            'state'           => $state,
            'all_retired'     => $allRetired,
            'organisations'   => $organisationsOut,
        ];
    }

    /**
     * @param  array<int, object>  $cells
     * @return array{0: string, 1: bool}
     */
    private function groupState(array $cells): array
    {
        $priority = [
            OrgStockStateEnum::ACTIVE->value        => 0,
            OrgStockStateEnum::SUSPENDED->value     => 1,
            OrgStockStateEnum::DISCONTINUING->value => 2,
            OrgStockStateEnum::DISCONTINUED->value  => 3,
        ];

        $counts = [];
        foreach ($cells as $cell) {
            if ($cell->state === OrgStockStateEnum::DISCONTINUED->value) {
                continue;
            }
            $counts[$cell->state] = ($counts[$cell->state] ?? 0) + 1;
        }

        if (!$counts) {
            return [OrgStockStateEnum::DISCONTINUED->value, true];
        }

        $max        = max($counts);
        $candidates = array_keys($counts, $max, true);
        usort($candidates, fn ($a, $b) => ($priority[$a] ?? 99) <=> ($priority[$b] ?? 99));

        return [$candidates[0], false];
    }

    private function condition(object $cell): string
    {
        if ($cell->state === OrgStockStateEnum::DISCONTINUING->value) {
            return 'sell';
        }
        if ($cell->state === OrgStockStateEnum::SUSPENDED->value) {
            return 'hold';
        }
        if ($cell->state === OrgStockStateEnum::DISCONTINUED->value) {
            return 'ret';
        }
        if ((bool) $cell->is_on_demand) {
            return 'ok';
        }

        if ((float) $cell->quantity_available <= 0) {
            return ((float) $cell->sales_180d > 0 || (float) $cell->inbound_quantity > 0) ? 'oos' : 'ns';
        }

        if (!$cell->is_for_sale && !$cell->is_raw_material) {
            return 'off';
        }

        return match (true) {
            in_array($cell->bucket, ['w1', 'w2']) => 'low',
            $cell->bucket === 'excess'            => 'over',
            $cell->bucket === 'dead'              => 'dead',
            default                               => 'ok',
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

        if ($row['all_retired'] && $state !== OrgStockStateEnum::DISCONTINUED->value && $search === '') {
            return false;
        }

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
                if (in_array($cell['condition'], ['ns', 'ret'], true)) {
                    continue;
                }

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
