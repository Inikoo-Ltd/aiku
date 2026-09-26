<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 17:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\SalesAnalysis;

use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Sales of a scope (a master or shop category, a product, a trade unit, a stock...) over a period
 * compared with another, with what can explain the difference: stock outs, changes and traffic.
 * Sales come from invoice lines, leaving out invoices to our own organisations (partners).
 */
class GetSalesAnalysis
{
    use AsAction;

    private const int MAX_EVENTS = 400;

    private const array PRICE_KEYS = ['price', 'rrp'];
    private const array CONTENT_KEYS = ['name', 'description', 'description_extra', 'description_title', 'code'];
    private const array STATUS_KEYS = ['status', 'state', 'is_for_sale'];

    private SalesAnalysisScope $scope;
    private Collection $shops;
    private Collection $products;
    private array $shopIds = [];
    private bool $includePartners = false;
    private array $groupedSales = [];
    private string $unit = 'week';

    /**
     * @param array{from?: string|null, to?: string|null, compareFrom?: string|null, compareTo?: string|null, organisations?: array|string|null, shops?: array|string|null} $modelData
     */
    public function handle(SalesAnalysisScope $scope, array $modelData, bool $withDetails = true): array
    {
        if (!$scope->cacheKey) {
            return $this->analyse($scope, $modelData, $withDetails);
        }

        return Cache::remember(
            'sales-analysis:'.$scope->cacheKey.':'.md5(json_encode([Arr::only($modelData, ['from', 'to', 'compareFrom', 'compareTo', 'organisations', 'shops', 'partners']), $withDetails, now()->toDateString()])),
            now()->endOfDay(),
            fn () => $this->analyse($scope, $modelData, $withDetails)
        );
    }

    private function analyse(SalesAnalysisScope $scope, array $modelData, bool $withDetails): array
    {
        $this->scope           = $scope;
        $this->includePartners = (bool)Arr::get($modelData, 'partners');

        $to   = Carbon::parse(Arr::get($modelData, 'to') ?? now()->subDay()->toDateString())->startOfDay();
        $from = Carbon::parse(Arr::get($modelData, 'from') ?? $to->copy()->subYear()->addDay()->toDateString())->startOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        $compareTo   = Carbon::parse(Arr::get($modelData, 'compareTo') ?? $to->copy()->subYear()->toDateString())->startOfDay();
        $compareFrom = Carbon::parse(Arr::get($modelData, 'compareFrom') ?? $from->copy()->subYear()->toDateString())->startOfDay();
        if ($compareFrom->gt($compareTo)) {
            [$compareFrom, $compareTo] = [$compareTo, $compareFrom];
        }

        $frequency          = $this->frequency($from, $to);
        $this->unit         = $this->bucketUnit($frequency);
        $this->groupedSales = [];

        $allProducts = DB::table('products')
            ->whereRaw('id = any(?::int[])', [$this->intArray($scope->productIds)])
            ->whereNotNull('asset_id')
            ->select(['id', 'asset_id', 'shop_id', 'webpage_id', 'code', 'state', 'status', 'created_at'])
            ->get();

        $this->shops = DB::table('shops')
            ->whereIn('id', $allProducts->pluck('shop_id')->merge(array_keys($scope->shopNodeStates))->unique())
            ->select(['id', 'code', 'name', 'slug', 'organisation_id', 'state'])
            ->orderBy('code')
            ->get()
            ->keyBy('id');
        $organisations = DB::table('organisations')
            ->whereIn('id', $this->shops->pluck('organisation_id')->unique())
            ->select(['id', 'slug', 'code', 'name'])
            ->orderBy('id')
            ->get();

        $selectedOrganisations = $organisations->whereIn('slug', $this->slugs(Arr::get($modelData, 'organisations')))->values();
        $selectedShops         = $this->shops
            ->whereIn('slug', $this->slugs(Arr::get($modelData, 'shops')))
            ->when($selectedOrganisations->isNotEmpty(), fn (Collection $shops) => $shops->whereIn('organisation_id', $selectedOrganisations->pluck('id')))
            ->values();

        $this->shopIds = $this->shops
            ->when($selectedOrganisations->isNotEmpty(), fn (Collection $shops) => $shops->whereIn('organisation_id', $selectedOrganisations->pluck('id')))
            ->when($selectedShops->isNotEmpty(), fn (Collection $shops) => $shops->whereIn('id', $selectedShops->pluck('id')))
            ->keys()
            ->all();
        $this->products = $allProducts->whereIn('shop_id', $this->shopIds)->values();

        $productIds       = $this->products->pluck('id')->all();
        [$stockOuts, $compareStockOuts] = GetSalesAnalysisStockOuts::make()->handlePeriods($productIds, [[$from, $to], [$compareFrom, $compareTo]], $scope->amountColumn);

        return [
            'filters'         => [
                'organisations'          => $organisations->map(fn ($organisation) => [
                    'slug' => $organisation->slug,
                    'code' => $organisation->code,
                    'name' => $organisation->name,
                ])->values()->all(),
                'shops'                  => $this->shops->map(fn ($shop) => [
                    'slug'              => $shop->slug,
                    'code'              => $shop->code,
                    'name'              => $shop->name,
                    'state'             => $shop->state,
                    'organisation_slug' => $organisations->firstWhere('id', $shop->organisation_id)?->slug,
                ])->values()->all(),
                'selected_organisations' => $selectedOrganisations->pluck('slug')->all(),
                'selected_shops'         => $selectedShops->pluck('slug')->all(),
            ],
            'include_partners' => $this->includePartners,
            'period'          => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'compare_period'  => ['from' => $compareFrom->toDateString(), 'to' => $compareTo->toDateString()],
            'frequency'       => $frequency->value,
            'currency'        => $scope->currency,
            'sales'           => $this->salesSeries($frequency, $from, $to),
            'compare_sales'   => $this->salesSeries($frequency, $compareFrom, $compareTo),
            'totals'          => [
                'current'  => [
                    ...$this->salesTotals($from, $to),
                    ...$this->stockOutTotals($stockOuts),
                ],
                'previous' => [
                    ...$this->salesTotals($compareFrom, $compareTo),
                    ...$this->stockOutTotals($compareStockOuts),
                ],
            ],
            'shops'           => $this->byShop($from, $to, $compareFrom, $compareTo, $stockOuts),
            'breakdown_label' => $scope->breakdownLabel,
            'breakdown'       => $this->byBreakdown($from, $to, $compareFrom, $compareTo, $stockOuts),
            'stock_outs'      => $stockOuts,
            'skos'            => $this->stockKeepingUnitCount(),
            'traffic'         => $withDetails ? $this->traffic($frequency, $from, $to) : [],
            'events'          => $withDetails ? $this->events($from, $to) : [],
        ];
    }

    /**
     * The last 12 months against the year before, for the Overview tab.
     */
    public function teaser(SalesAnalysisScope $scope): array
    {
        $analysis = $this->handle($scope, [], withDetails: false);

        return [
            ...Arr::only($analysis, ['period', 'compare_period', 'currency', 'frequency', 'sales', 'compare_sales', 'totals', 'breakdown_label']),
            'shop_count' => count($analysis['filters']['shops']),
            'shops'     => $this->movers($analysis['shops']),
            'breakdown' => $this->movers(array_filter($analysis['breakdown'], fn ($row) => $row['id'] !== 0)),
        ];
    }

    private function movers(array $rows): array
    {
        $moved = collect($rows)->filter(fn ($row) => $row['sales'] != $row['previous_sales'])->sortBy(fn ($row) => $row['sales'] - $row['previous_sales']);

        return $moved->take(5)->merge($moved->reverse()->take(5))->unique(fn ($row) => json_encode($row))->values()->all();
    }

    /**
     * @return array<int, string>
     */
    private function slugs(array|string|null $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return array_values(array_filter(array_map('strval', $value ?? [])));
    }

    private function intArray(array $ids): string
    {
        return '{'.implode(',', array_map('intval', $ids)).'}';
    }

    private function stockKeepingUnitCount(): int
    {
        return DB::table('product_has_org_stocks')
            ->join('org_stocks', 'org_stocks.id', 'product_has_org_stocks.org_stock_id')
            ->whereRaw('product_has_org_stocks.product_id = any(?::int[])', [$this->intArray($this->products->pluck('id')->all())])
            ->whereNotIn('org_stocks.state', [OrgStockStateEnum::DISCONTINUED->value, OrgStockStateEnum::DISCONTINUING->value])
            ->distinct()
            ->count('product_has_org_stocks.org_stock_id');
    }

    private function frequency(Carbon $from, Carbon $to): TimeSeriesFrequencyEnum
    {
        $days = $from->diffInDays($to);

        return match (true) {
            $days <= 92 => TimeSeriesFrequencyEnum::DAILY,
            $days <= 731 => TimeSeriesFrequencyEnum::WEEKLY,
            default => TimeSeriesFrequencyEnum::MONTHLY,
        };
    }

    private function recordFrequencyCode(TimeSeriesFrequencyEnum $frequency): string
    {
        return match ($frequency) {
            TimeSeriesFrequencyEnum::DAILY => 'D',
            TimeSeriesFrequencyEnum::WEEKLY => 'W',
            TimeSeriesFrequencyEnum::MONTHLY => 'M',
            TimeSeriesFrequencyEnum::QUARTERLY => 'Q',
            TimeSeriesFrequencyEnum::YEARLY => 'Y',
        };
    }

    private function salesSeries(TimeSeriesFrequencyEnum $frequency, Carbon $from, Carbon $to): array
    {
        $unit  = $this->bucketUnit($frequency);
        $sales = $this->groupedSales($from, $to)->groupBy('bucket')->map(fn (Collection $rows) => $rows->sum('sales'));

        $bucket = match ($unit) {
            'day' => $from->copy(),
            'week' => $from->copy()->startOfWeek(Carbon::MONDAY),
            default => $from->copy()->startOfMonth(),
        };

        $series = [];
        while ($bucket->lte($to)) {
            $date     = $bucket->toDateString();
            $series[] = [
                'date'  => $date,
                'sales' => round($sales[$date] ?? 0, 2),
            ];
            $bucket->addUnit($unit);
        }

        return $series;
    }

    private function bucketUnit(TimeSeriesFrequencyEnum $frequency): string
    {
        return match ($frequency) {
            TimeSeriesFrequencyEnum::DAILY => 'day',
            TimeSeriesFrequencyEnum::WEEKLY => 'week',
            default => 'month',
        };
    }

    /**
     * One query per period: sales by time bucket, shop and asset, which the chart, the websites
     * and the breakdown are all summed from.
     */
    private function groupedSales(Carbon $from, Carbon $to): Collection
    {
        $key = $from->toDateString().'|'.$to->toDateString();

        return $this->groupedSales[$key] ??= $this->invoiceLines($from, $to)
            ->groupByRaw('1, 2, 3')
            ->selectRaw("date_trunc('{$this->unit}', invoice_transactions.date)::date::text as bucket, invoice_transactions.shop_id, invoice_transactions.asset_id, sum(invoice_transactions.{$this->scope->amountColumn})::float as sales")
            ->get();
    }

    private function salesTotals(Carbon $from, Carbon $to): array
    {
        $totals = $this->invoiceLines($from, $to)
            ->selectRaw(
                "coalesce(sum(invoice_transactions.{$this->scope->amountColumn}), 0)::float as sales,
                count(distinct invoice_transactions.order_id) as orders,
                count(distinct invoice_transactions.invoice_id) filter (where not invoice_transactions.is_refund) as invoices,
                count(distinct invoice_transactions.invoice_id) filter (where invoice_transactions.is_refund) as refunds,
                count(distinct invoice_transactions.customer_id) as customers"
            )
            ->first();

        return [
            'sales'     => round($totals->sales, 2),
            'orders'    => (int)$totals->orders,
            'invoices'  => (int)$totals->invoices,
            'refunds'   => (int)$totals->refunds,
            'customers' => (int)$totals->customers,
        ];
    }

    private function invoiceLines(Carbon $from, Carbon $to): Builder
    {
        return DB::table('invoice_transactions')
            ->whereRaw('invoice_transactions.asset_id = any(?::int[])', [$this->intArray($this->products->pluck('asset_id')->unique()->all())])
            ->whereNull('invoice_transactions.deleted_at')
            ->when(!$this->includePartners, fn ($query) => $query->where('invoice_transactions.is_partner', false))
            ->whereBetween('invoice_transactions.date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
    }

    private function salesBy(string $column, Carbon $from, Carbon $to): Collection
    {
        return $this->groupedSales($from, $to)
            ->groupBy($column)
            ->map(fn (Collection $rows) => (object)['key' => $rows->first()->$column, 'sales' => $rows->sum('sales')]);
    }

    private function stockOutTotals(array $stockOuts): array
    {
        $counted = collect($stockOuts)->where('cause', '!=', 'discontinued');

        return [
            'stock_outs'          => $counted->count(),
            'stock_out_days'      => $counted->sum('days'),
            'lost_sales'          => round($counted->sum('lost_sales'), 2),
            'stock_outs_no_order' => $counted->whereIn('cause', ['no_order', 'ordered_after'])->count(),
        ];
    }

    private function byShop(Carbon $from, Carbon $to, Carbon $compareFrom, Carbon $compareTo, array $stockOuts): array
    {
        $sales         = $this->salesBy('shop_id', $from, $to);
        $previousSales = $this->salesBy('shop_id', $compareFrom, $compareTo);

        $stockOutDaysByOrganisation = collect($stockOuts)->where('cause', '!=', 'discontinued')->groupBy('organisation_id')->map->sum('days');

        return collect($this->shopIds)
            ->map(function ($shopId) use ($sales, $previousSales, $stockOutDaysByOrganisation) {
                $shop     = $this->shops[$shopId];
                $row      = $sales[$shopId] ?? null;
                $previous = $previousSales[$shopId] ?? null;

                return [
                    'shop_id'         => $shopId,
                    'shop_code'       => $shop->code,
                    'shop_name'       => $shop->name,
                    'shop_state'      => $shop->state,
                    'organisation_id' => $shop->organisation_id,
                    'node_state'      => $this->scope->shopNodeStates[$shopId] ?? null,
                    'sales'           => round($row?->sales ?? 0, 2),
                    'previous_sales'  => round($previous?->sales ?? 0, 2),
                    'stock_out_days'  => $stockOutDaysByOrganisation[$shop->organisation_id] ?? 0,
                ];
            })
            ->sortBy(fn ($row) => $row['sales'] - $row['previous_sales'])
            ->values()
            ->all();
    }

    private function byBreakdown(Carbon $from, Carbon $to, Carbon $compareFrom, Carbon $compareTo, array $stockOuts): array
    {
        if (!$this->scope->breakdownLabel) {
            return [];
        }

        $rows         = $this->scope->breakdownRows;
        $keyByProduct = array_map(fn ($key) => isset($rows[$key]) ? $key : 0, $this->scope->breakdownKeyByProduct);
        $keyByAsset   = $this->products->mapWithKeys(fn ($product) => [$product->asset_id => $keyByProduct[$product->id] ?? 0]);
        $sumByKey     = fn (Collection $rows) => $rows
            ->groupBy(fn ($row) => $keyByAsset[$row->key] ?? 0)
            ->map(fn (Collection $group) => $group->sum('sales'));

        $sales         = $sumByKey($this->salesBy('asset_id', $from, $to));
        $previousSales = $sumByKey($this->salesBy('asset_id', $compareFrom, $compareTo));

        $productsByKey = $this->products->groupBy(fn ($product) => $keyByProduct[$product->id] ?? 0);

        $keysByOrgStock = DB::table('product_has_org_stocks')
            ->whereRaw('product_id = any(?::int[])', [$this->intArray($this->products->pluck('id')->all())])
            ->get(['product_id', 'org_stock_id'])
            ->groupBy('org_stock_id')
            ->map(fn (Collection $links) => $links->map(fn ($link) => $keyByProduct[$link->product_id] ?? 0)->unique()->values());
        $stockOutsByKey = collect($stockOuts)
            ->where('cause', '!=', 'discontinued')
            ->flatMap(fn ($stockOut) => $keysByOrgStock->get($stockOut['org_stock_id'], collect())->map(fn ($key) => ['key' => $key, 'stock_out' => $stockOut]))
            ->groupBy('key')
            ->map(fn (Collection $rows) => $rows->pluck('stock_out'));

        $row = function (int|string $key, array $meta) use ($sales, $previousSales, $productsByKey, $stockOutsByKey) {
            $products  = $productsByKey->get($key, collect());
            $stockOuts = $stockOutsByKey->get($key, collect());

            return [
                ...$meta,
                'id'                    => $key,
                'sales'                 => round($sales[$key] ?? 0, 2),
                'previous_sales'        => round($previousSales[$key] ?? 0, 2),
                'websites'              => $products->where('state', 'active')->pluck('shop_id')->unique()->count(),
                'websites_out_of_stock' => $products->where('status', ProductStatusEnum::OUT_OF_STOCK->value)->pluck('shop_id')->unique()->count(),
                'stock_outs'            => $stockOuts->count(),
                'stock_out_days'        => $stockOuts->sum('days'),
                'lost_sales'            => round($stockOuts->sum('lost_sales'), 2),
            ];
        };

        return collect($this->scope->breakdownRows)
            ->map(fn ($meta, $key) => $row($key, $meta))
            ->when(
                ($sales[0] ?? 0) || ($previousSales[0] ?? 0),
                fn (Collection $rows) => $rows->push($row(0, [
                    'code'            => __('Other'),
                    'name'            => __('Not linked to any of the rows above'),
                    'slug'            => null,
                    'status'          => true,
                    'is_for_sale'     => true,
                    'created_at'      => null,
                    'discontinued_at' => null,
                ]))
            )
            ->sortBy([
                fn ($a, $b) => (!$a['sales'] && !$a['previous_sales']) <=> (!$b['sales'] && !$b['previous_sales']),
                fn ($a, $b) => ($a['sales'] - $a['previous_sales']) <=> ($b['sales'] - $b['previous_sales']),
            ])
            ->values()
            ->all();
    }

    private function webpageShops(): Collection
    {
        return collect($this->scope->webpageShops)
            ->union($this->products->filter(fn ($product) => $product->webpage_id)->pluck('shop_id', 'webpage_id'))
            ->filter(fn ($shopId) => in_array($shopId, $this->shopIds));
    }

    private function traffic(TimeSeriesFrequencyEnum $frequency, Carbon $from, Carbon $to): array
    {
        $webpageIds = $this->webpageShops()->keys();
        if ($webpageIds->isEmpty()) {
            return [];
        }

        return DB::table('webpage_time_series_records as records')
            ->join('webpage_time_series as series', 'series.id', 'records.webpage_time_series_id')
            ->whereRaw('series.webpage_id = any(?::int[])', [$this->intArray($webpageIds->all())])
            ->where('series.frequency', $frequency->value)
            ->where('records.frequency', $this->recordFrequencyCode($frequency))
            ->where('records.to', '>=', $from->toDateString())
            ->where('records.from', '<=', $to->toDateString())
            ->groupBy('records.from')
            ->orderBy('records.from')
            ->selectRaw('records."from"::text as date, coalesce(sum(records.visitors), 0)::int as visitors, coalesce(sum(records.page_views), 0)::int as page_views, coalesce(sum(records.add_to_baskets), 0)::int as add_to_baskets')
            ->get()
            ->all();
    }

    private function events(Carbon $from, Carbon $to): array
    {
        $end = $to->copy()->endOfDay();

        $events = collect();
        foreach ($this->scope->audits as $audit) {
            $labels = $audit['shops'] === null
                ? $audit['labels']
                : array_filter($audit['labels'], fn ($id) => in_array($audit['shops'][$id] ?? null, $this->shopIds), ARRAY_FILTER_USE_KEY);
            $events = $events->concat($this->auditEvents($audit['type'], $labels, $audit['shops'], $from, $end));
        }

        $events = $events
            ->concat($this->launchEvents($from, $end))
            ->concat($this->offerEvents($from, $end))
            ->concat($this->publishEvents($from, $end));

        return $this->groupEvents($events)->sortByDesc('datetime')->take(self::MAX_EVENTS)->values()->all();
    }

    private function auditEvents(string $auditableType, array $labels, ?array $shopIds, Carbon $from, Carbon $end): Collection
    {
        if (!$labels) {
            return collect();
        }

        return DB::table('audits')
            ->where('auditable_type', $auditableType)
            ->whereRaw('auditable_id = any(?::int[])', [$this->intArray(array_keys($labels))])
            ->whereBetween('created_at', [$from, $end])
            ->where('event', 'updated')
            ->select(['auditable_id', 'old_values', 'new_values', 'user_type', 'user_id', 'created_at'])
            ->orderBy('id')
            ->get()
            ->flatMap(function ($audit) use ($labels, $shopIds) {
                $old = json_decode($audit->old_values ?? '[]', true) ?: [];
                $new = json_decode($audit->new_values ?? '[]', true) ?: [];

                return collect($new)
                    ->filter(fn ($value, $key) => $this->auditType((string)$key, $value, $old[$key] ?? null) !== null)
                    ->map(fn ($value, $key) => [
                        'datetime' => Carbon::parse($audit->created_at),
                        'type'     => $this->auditType((string)$key, $value, $old[$key] ?? null),
                        'subject'  => $labels[$audit->auditable_id],
                        'field'    => (string)$key,
                        'old'      => $this->displayValue($old[$key] ?? null),
                        'new'      => $this->displayValue($value),
                        'shop_id'  => $shopIds[$audit->auditable_id] ?? null,
                        'user_id'  => $audit->user_type === 'User' ? $audit->user_id : null,
                    ])
                    ->values();
            });
    }

    private function auditType(string $key, mixed $value, mixed $old): ?string
    {
        $key = strtolower($key);

        if (in_array($key, self::PRICE_KEYS) || str_starts_with($key, 'price ') || str_starts_with($key, 'rrp ')) {
            return 'price';
        }
        if (str_starts_with($key, 'gold_reward') || str_starts_with($key, 'gr_vol')) {
            return 'offer';
        }
        if (in_array($key, self::CONTENT_KEYS)) {
            return 'content';
        }
        if (in_array($key, self::STATUS_KEYS)) {
            $outOfStock = ProductStatusEnum::OUT_OF_STOCK->value;

            return $value === $outOfStock || $old === $outOfStock ? null : 'status';
        }

        return null;
    }

    private function displayValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value);
        }
        if (is_bool($value)) {
            return $value ? __('yes') : __('no');
        }
        if (is_string($value) && mb_strlen($value) > 60) {
            return mb_substr(strip_tags($value), 0, 60).'…';
        }

        return (string)($value ?? '—');
    }

    private function launchEvents(Carbon $from, Carbon $end): Collection
    {
        return $this->products
            ->filter(fn ($product) => $product->created_at && Carbon::parse($product->created_at)->between($from, $end))
            ->map(fn ($product) => [
                'datetime' => Carbon::parse($product->created_at),
                'type'     => 'launch',
                'subject'  => $product->code,
                'field'    => 'launch',
                'old'      => null,
                'new'      => null,
                'shop_id'  => $product->shop_id,
                'user_id'  => null,
            ])
            ->values();
    }

    private function offerEvents(Carbon $from, Carbon $end): Collection
    {
        $productIds = $this->products->pluck('id')->all();
        $triggers   = collect($this->scope->offerTriggers)
            ->map(fn ($ids, $type) => $type === 'Product' ? array_values(array_intersect($ids, $productIds)) : $ids)
            ->filter();
        if ($triggers->isEmpty()) {
            return collect();
        }

        return DB::table('offers')
            ->whereNull('deleted_at')
            ->whereIn('shop_id', $this->shopIds)
            ->where(function ($query) use ($triggers) {
                foreach ($triggers as $type => $ids) {
                    $query->orWhere(fn ($query) => $query->where('trigger_type', $type)->whereRaw('trigger_id = any(?::int[])', [$this->intArray($ids)]));
                }
            })
            ->where(function ($query) use ($from, $end) {
                $query->whereBetween('start_at', [$from, $end])->orWhereBetween('end_at', [$from, $end]);
            })
            ->select(['name', 'shop_id', 'start_at', 'end_at'])
            ->get()
            ->flatMap(fn ($offer) => collect([
                $offer->start_at && Carbon::parse($offer->start_at)->between($from, $end) ? ['field' => 'offer_started', 'datetime' => Carbon::parse($offer->start_at)] : null,
                $offer->end_at && Carbon::parse($offer->end_at)->between($from, $end) ? ['field' => 'offer_ended', 'datetime' => Carbon::parse($offer->end_at)] : null,
            ])->filter()->map(fn ($moment) => [
                'datetime' => $moment['datetime'],
                'type'     => 'offer',
                'subject'  => $offer->name,
                'field'    => $moment['field'],
                'old'      => null,
                'new'      => null,
                'shop_id'  => $offer->shop_id,
                'user_id'  => null,
            ]));
    }

    private function publishEvents(Carbon $from, Carbon $end): Collection
    {
        $shopByWebpage = collect($this->scope->webpageShops)->filter(fn ($shopId) => in_array($shopId, $this->shopIds));
        if ($shopByWebpage->isEmpty()) {
            return collect();
        }

        return DB::table('snapshots')
            ->where('parent_type', 'Webpage')
            ->where('publisher_type', 'User')
            ->whereIn('parent_id', $shopByWebpage->keys())
            ->whereBetween('published_at', [$from, $end])
            ->select(['parent_id', 'published_at', 'comment', 'publisher_type', 'publisher_id'])
            ->get()
            ->map(fn ($snapshot) => [
                'datetime' => Carbon::parse($snapshot->published_at),
                'type'     => 'publish',
                'subject'  => $snapshot->comment ?: '',
                'field'    => 'publish',
                'old'      => null,
                'new'      => null,
                'shop_id'  => $shopByWebpage[$snapshot->parent_id],
                'user_id'  => $snapshot->publisher_id,
            ]);
    }

    private function groupEvents(Collection $events): Collection
    {
        $users = DB::table('users')->whereIn('id', $events->pluck('user_id')->filter()->unique())->pluck('contact_name', 'id');

        return $events
            ->groupBy(fn ($event) => implode('|', [
                $event['type'],
                $event['field'],
                $event['type'] === 'price' || $event['type'] === 'launch' || $event['type'] === 'publish' ? '' : $event['subject'],
                $event['type'] === 'price' ? '' : $event['new'],
                $event['datetime']->format('Y-m-d H'),
            ]))
            ->map(function (Collection $group) use ($users) {
                $first   = $group->first();
                $shopIds = $group->pluck('shop_id')->filter()->unique()->values();

                return [
                    'datetime' => $first['datetime']->toIso8601String(),
                    'date'     => $first['datetime']->toDateString(),
                    'type'     => $first['type'],
                    'field'    => $first['field'],
                    'subjects' => $group->pluck('subject')->filter()->unique()->values()->all(),
                    'old'      => $first['old'],
                    'new'      => $first['new'],
                    'changes'  => $group->count(),
                    'shops'    => $shopIds->map(fn ($shopId) => $this->shops[$shopId]->code ?? null)->filter()->values()->all(),
                    'details'  => $group->take(50)->map(fn ($event) => [
                        'subject' => $event['subject'],
                        'shop'    => $event['shop_id'] ? ($this->shops[$event['shop_id']]->code ?? null) : null,
                        'old'     => $event['old'],
                        'new'     => $event['new'],
                    ])->values()->all(),
                    'user'     => $group->pluck('user_id')->filter()->map(fn ($userId) => $users[$userId] ?? null)->filter()->first(),
                ];
            })
            ->values();
    }
}
