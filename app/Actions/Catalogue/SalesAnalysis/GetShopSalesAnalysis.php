<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Sep 2026 04:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\SalesAnalysis;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterShop;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Sales analysis of a whole shop or master shop. A shop has tens of thousands of products, so
 * sales come from the saved shop and department time series (which leave out the invoices to
 * our own organisations), stock from the daily organisation stock summary and traffic from the website
 * time series. Same shape as GetSalesAnalysis so the same components show it.
 */
class GetShopSalesAnalysis
{
    use AsAction;

    private const int MAX_EVENTS = 300;

    private Shop|MasterShop $parent;
    private Collection $shops;
    private array $shopIds = [];
    private string $unit = 'week';
    private string $seriesColumn;
    private string $invoiceColumn;
    private bool $includePartners = false;

    /**
     * @param array{from?: string|null, to?: string|null, compareFrom?: string|null, compareTo?: string|null, organisations?: array|string|null, shops?: array|string|null} $modelData
     */
    public function handle(Shop|MasterShop $parent, array $modelData): array
    {
        return Cache::remember(
            'sales-analysis:'.class_basename($parent).':'.$parent->id.':'.md5(json_encode([Arr::only($modelData, ['from', 'to', 'compareFrom', 'compareTo', 'organisations', 'shops', 'partners']), now()->toDateString()])),
            now()->endOfDay(),
            fn () => $this->analyse($parent, $modelData)
        );
    }

    public function teaser(Shop|MasterShop $parent): array
    {
        $analysis = $this->handle($parent, []);

        return [
            ...Arr::only($analysis, ['period', 'compare_period', 'currency', 'frequency', 'sales', 'compare_sales', 'totals', 'breakdown_label', 'stock_level']),
            'shop_count' => count($analysis['filters']['shops']),
            'shops'      => $this->movers($analysis['shops']),
            'breakdown'  => $this->movers(array_filter($analysis['breakdown'], fn ($row) => $row['id'] !== 0)),
        ];
    }

    private function analyse(Shop|MasterShop $parent, array $modelData): array
    {
        $this->parent          = $parent;
        $isShop                = $parent instanceof Shop;
        $this->includePartners = (bool)Arr::get($modelData, 'partners');

        $this->seriesColumn  = $isShop ? 'sales_external' : 'sales_grp_currency_external';
        $this->invoiceColumn = $isShop ? 'net_amount' : 'grp_net_amount';

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

        $days       = $from->diffInDays($to);
        $this->unit = match (true) {
            $days <= 92 => 'day',
            $days <= 731 => 'week',
            default => 'month',
        };

        $this->shops = DB::table('shops')
            ->when($isShop, fn ($query) => $query->where('id', $parent->id), fn ($query) => $query->where('master_shop_id', $parent->id))
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

        $departments = $this->departments($isShop);

        return [
            'filters'              => [
                'organisations'          => $organisations->map(fn ($organisation) => ['slug' => $organisation->slug, 'code' => $organisation->code, 'name' => $organisation->name])->values()->all(),
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
            'include_partners'     => $this->includePartners,
            'period'               => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'compare_period'       => ['from' => $compareFrom->toDateString(), 'to' => $compareTo->toDateString()],
            'frequency'            => ['day' => 'daily', 'week' => 'weekly', 'month' => 'monthly'][$this->unit],
            'currency'             => $isShop ? $parent->currency->code : group()->currency->code,
            'stock_level'          => 'organisation',
            'sales'                => $this->salesSeries($from, $to),
            'compare_sales'        => $this->salesSeries($compareFrom, $compareTo),
            'stock_series'         => $this->stockSeries($from, $to),
            'totals'               => $totals = [
                'current'  => $this->totals($from, $to),
                'previous' => $this->totals($compareFrom, $compareTo),
            ],
            'shops'                => $this->byShop($from, $to, $compareFrom, $compareTo),
            'breakdown_label'      => __('Departments'),
            'breakdown'            => $this->byDepartment($departments, $isShop, $from, $to, $compareFrom, $compareTo, $totals),
            'stock_outs'           => [],
            'skos'                 => $this->latestStockCount($to),
            'traffic'              => $this->traffic($from, $to),
            'events'               => $this->events($departments, $isShop, $from, $to),
        ];
    }

    private function movers(array $rows): array
    {
        $moved = collect($rows)->filter(fn ($row) => $row['sales'] != $row['previous_sales'])->sortBy(fn ($row) => $row['sales'] - $row['previous_sales']);

        return $moved->take(5)->merge($moved->reverse()->take(5))->unique(fn ($row) => json_encode($row))->values()->all();
    }

    private function slugs(array|string|null $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return array_values(array_filter(array_map('strval', $value ?? [])));
    }

    private function amount(string $externalColumn): string
    {
        $external = "coalesce(records.$externalColumn, 0)";

        return $this->includePartners ? $external.' + coalesce(records.'.str_replace('_external', '_internal', $externalColumn).', 0)' : $external;
    }

    private function organisationIds(): array
    {
        return $this->shops->only($this->shopIds)->pluck('organisation_id')->unique()->values()->all();
    }

    private function bucketOf(string $date): string
    {
        $day = Carbon::parse($date);

        return match ($this->unit) {
            'day' => $day->toDateString(),
            'week' => $day->startOfWeek(Carbon::MONDAY)->toDateString(),
            default => $day->startOfMonth()->toDateString(),
        };
    }

    private function buckets(Carbon $from, Carbon $to): array
    {
        $bucket = Carbon::parse($this->bucketOf($from->toDateString()));
        $dates  = [];
        while ($bucket->lte($to)) {
            $dates[] = $bucket->toDateString();
            $bucket->addUnit($this->unit);
        }

        return $dates;
    }

    /**
     * Daily shop time series records of the selected shops over a period.
     */
    private function shopRecords(Carbon $from, Carbon $to): Collection
    {
        return DB::table('shop_time_series_records as records')
            ->join('shop_time_series as series', 'series.id', 'records.shop_time_series_id')
            ->whereIn('series.shop_id', $this->shopIds)
            ->where('series.frequency', 'daily')
            ->where('records.frequency', 'D')
            ->whereBetween('records.from', [$from->toDateString(), $to->toDateString()])
            ->select(['series.shop_id', 'records.from'])
            ->selectRaw($this->amount($this->seriesColumn)."::float as sales, coalesce(records.orders, 0) as orders, coalesce(records.invoices, 0) as invoices, coalesce(records.refunds, 0) as refunds, coalesce(records.registrations_with_orders, 0) + coalesce(records.registrations_without_orders, 0) as registrations")
            ->get();
    }

    private function salesSeries(Carbon $from, Carbon $to): array
    {
        $sales = $this->shopRecords($from, $to)->groupBy(fn ($row) => $this->bucketOf($row->from))->map->sum('sales');

        return array_map(fn ($date) => ['date' => $date, 'sales' => round($sales[$date] ?? 0, 2)], $this->buckets($from, $to));
    }

    private function totals(Carbon $from, Carbon $to): array
    {
        $records = $this->shopRecords($from, $to);
        $stock   = $this->stockHistory($from, $to);

        $customers = DB::table('invoices')
            ->whereIn('shop_id', $this->shopIds)
            ->when(!$this->includePartners, fn ($query) => $query->whereNull('as_organisation_id'))
            ->whereNull('deleted_at')
            ->where('type', InvoiceTypeEnum::INVOICE->value)
            ->whereBetween('date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->distinct()
            ->count('customer_id');

        return [
            'sales'                   => round($records->sum('sales'), 2),
            'orders'                  => (int)$records->sum('orders'),
            'invoices'                => (int)$records->sum('invoices'),
            'refunds'                 => (int)$records->sum('refunds'),
            'customers'               => $customers,
            'registrations'           => (int)$records->sum('registrations'),
            'out_of_stock_percentage' => $stock->sum('skos') ? round($stock->sum('out_of_stock') / $stock->sum('skos') * 100, 1) : 0,
            'stock_outs'              => 0,
            'stock_out_days'          => 0,
            'lost_sales'              => 0,
            'stock_outs_no_order'     => 0,
        ];
    }

    private function byShop(Carbon $from, Carbon $to, Carbon $compareFrom, Carbon $compareTo): array
    {
        $grpColumn = 'sales_grp_currency_external';
        $sum       = function (Carbon $from, Carbon $to) use ($grpColumn) {
            $sales = DB::table('shop_time_series_records as records')
                ->join('shop_time_series as series', 'series.id', 'records.shop_time_series_id')
                ->whereIn('series.shop_id', $this->shopIds)
                ->where('series.frequency', 'daily')
                ->where('records.frequency', 'D')
                ->whereBetween('records.from', [$from->toDateString(), $to->toDateString()])
                ->groupBy('series.shop_id')
                ->selectRaw("series.shop_id, sum({$this->amount($grpColumn)})::float as sales")
                ->pluck('sales', 'shop_id');
            return $sales;
        };

        if ($this->parent instanceof Shop) {
            return [];
        }

        $sales    = $sum($from, $to);
        $previous = $sum($compareFrom, $compareTo);

        return collect($this->shopIds)
            ->map(fn ($shopId) => [
                'shop_id'         => $shopId,
                'shop_code'       => $this->shops[$shopId]->code,
                'shop_name'       => $this->shops[$shopId]->name,
                'shop_state'      => $this->shops[$shopId]->state,
                'organisation_id' => $this->shops[$shopId]->organisation_id,
                'node_state'      => null,
                'sales'           => round($sales[$shopId] ?? 0, 2),
                'previous_sales'  => round($previous[$shopId] ?? 0, 2),
                'stock_out_days'  => 0,
            ])
            ->sortBy(fn ($row) => $row['sales'] - $row['previous_sales'])
            ->values()
            ->all();
    }

    /**
     * Departments of the selected shops, and the row each one is shown under: itself in a shop,
     * its master department in a master shop.
     */
    private function departments(bool $isShop): Collection
    {
        return DB::table('product_categories')
            ->whereIn('shop_id', $this->shopIds)
            ->where('type', ProductCategoryTypeEnum::DEPARTMENT->value)
            ->select(['id', 'shop_id', 'code', 'name', 'slug', 'state', 'master_product_category_id', 'created_at', 'discontinued_at'])
            ->get()
            ->each(fn ($department) => $department->key = $isShop ? $department->id : (int)$department->master_product_category_id);
    }

    private function byDepartment(Collection $departments, bool $isShop, Carbon $from, Carbon $to, Carbon $compareFrom, Carbon $compareTo, array $totals): array
    {
        $keyOfDepartment = $departments->pluck('key', 'id');
        $sum             = function (Carbon $from, Carbon $to) use ($departments, $keyOfDepartment) {
            $sales = DB::table('product_category_time_series_records as records')
                ->join('product_category_time_series as series', 'series.id', 'records.product_category_time_series_id')
                ->whereIn('series.product_category_id', $departments->pluck('id'))
                ->where('series.frequency', 'daily')
                ->where('records.frequency', 'D')
                ->whereBetween('records.from', [$from->toDateString(), $to->toDateString()])
                ->groupBy('series.product_category_id')
                ->selectRaw("series.product_category_id, sum({$this->amount($this->seriesColumn)})::float as sales")
                ->get()
                ->groupBy(fn ($row) => $keyOfDepartment[$row->product_category_id] ?? 0)
                ->map->sum('sales');

            return $sales;
        };

        $sales    = $sum($from, $to);
        $previous = $sum($compareFrom, $compareTo);

        $rows = $isShop
            ? $departments->keyBy('id')
            : DB::table('master_product_categories')->whereIn('id', $departments->pluck('key')->filter()->unique())->select(['id', 'code', 'name', 'slug', 'status', 'created_at', 'discontinued_at'])->get()->keyBy('id');

        $rows = $rows
            ->map(fn ($row, $key) => [
                'id'                    => $key,
                'code'                  => $row->code,
                'name'                  => $row->name,
                'slug'                  => $row->slug,
                'status'                => $isShop ? !in_array($row->state, ['discontinued', 'inactive']) : (bool)$row->status,
                'is_for_sale'           => true,
                'created_at'            => $row->created_at ? Carbon::parse($row->created_at)->toDateString() : null,
                'discontinued_at'       => $row->discontinued_at ? Carbon::parse($row->discontinued_at)->toDateString() : null,
                'sales'                 => round($sales[$key] ?? 0, 2),
                'previous_sales'        => round($previous[$key] ?? 0, 2),
                'websites'              => $departments->where('key', $key)->pluck('shop_id')->unique()->count(),
                'websites_out_of_stock' => 0,
                'stock_outs'            => 0,
                'stock_out_days'        => 0,
                'lost_sales'            => 0,
            ]);

        $other         = round($totals['current']['sales'] - $rows->sum('sales'), 2);
        $otherPrevious = round($totals['previous']['sales'] - $rows->sum('previous_sales'), 2);

        return $rows
            ->when($other || $otherPrevious, fn (Collection $rows) => $rows->push([
                'id'                    => 0,
                'code'                  => __('Other'),
                'name'                  => __('Shipping, charges and products outside these departments'),
                'slug'                  => null,
                'status'                => true,
                'is_for_sale'           => true,
                'created_at'            => null,
                'discontinued_at'       => null,
                'sales'                 => $other,
                'previous_sales'        => $otherPrevious,
                'websites'              => 0,
                'websites_out_of_stock' => 0,
                'stock_outs'            => 0,
                'stock_out_days'        => 0,
                'lost_sales'            => 0,
            ]))
            ->sortBy([
                fn ($a, $b) => (!$a['sales'] && !$a['previous_sales']) <=> (!$b['sales'] && !$b['previous_sales']),
                fn ($a, $b) => ($a['sales'] - $a['previous_sales']) <=> ($b['sales'] - $b['previous_sales']),
            ])
            ->values()
            ->all();
    }

    private function stockHistory(Carbon $from, Carbon $to): Collection
    {
        return DB::table('organisation_stock_histories')
            ->whereIn('organisation_id', $this->organisationIds())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('date')
            ->selectRaw('date::text as date, sum(number_out_of_stock_org_stocks)::int as out_of_stock, sum(number_org_stocks)::int as skos')
            ->get();
    }

    private function stockSeries(Carbon $from, Carbon $to): array
    {
        $byBucket = $this->stockHistory($from, $to)->groupBy(fn ($row) => $this->bucketOf($row->date));

        return array_map(fn ($date) => [
            'date'         => $date,
            'out_of_stock' => (int)round($byBucket->get($date, collect())->avg('out_of_stock') ?? 0),
        ], $this->buckets($from, $to));
    }

    private function latestStockCount(Carbon $to): int
    {
        return (int)DB::table('organisation_stock_histories')
            ->whereIn('organisation_id', $this->organisationIds())
            ->where('date', DB::table('organisation_stock_histories')->whereIn('organisation_id', $this->organisationIds())->where('date', '<=', $to->toDateString())->max('date'))
            ->sum('number_org_stocks');
    }

    private function traffic(Carbon $from, Carbon $to): array
    {
        $frequency = ['day' => ['daily', 'D'], 'week' => ['weekly', 'W'], 'month' => ['monthly', 'M']][$this->unit];

        return DB::table('website_time_series_records as records')
            ->join('website_time_series as series', 'series.id', 'records.website_time_series_id')
            ->whereIn('series.website_id', DB::table('websites')->whereIn('shop_id', $this->shopIds)->select('id'))
            ->where('series.frequency', $frequency[0])
            ->where('records.frequency', $frequency[1])
            ->where('records.to', '>=', $from->toDateString())
            ->where('records.from', '<=', $to->toDateString())
            ->groupBy('records.from')
            ->orderBy('records.from')
            ->selectRaw('records."from"::text as date, coalesce(sum(records.visitors), 0)::int as visitors, coalesce(sum(records.page_views), 0)::int as page_views, 0 as add_to_baskets')
            ->get()
            ->all();
    }

    private function events(Collection $departments, bool $isShop, Carbon $from, Carbon $to): array
    {
        [$type, $labels, $shopOf] = $isShop
            ? ['ProductCategory', $departments->pluck('code', 'id')->all(), $departments->pluck('shop_id', 'id')->all()]
            : ['MasterProductCategory', DB::table('master_product_categories')->whereIn('id', $departments->pluck('key')->filter()->unique())->pluck('code', 'id')->all(), []];
        if (!$labels) {
            return [];
        }

        $users = collect();

        $events = DB::table('audits')
            ->where('auditable_type', $type)
            ->whereIn('auditable_id', array_keys($labels))
            ->whereBetween('created_at', [$from, $to->copy()->endOfDay()])
            ->where('event', 'updated')
            ->select(['auditable_id', 'old_values', 'new_values', 'user_type', 'user_id', 'created_at'])
            ->orderByDesc('id')
            ->limit(self::MAX_EVENTS)
            ->get()
            ->flatMap(function ($audit) use ($labels, $shopOf) {
                $old = json_decode($audit->old_values ?? '[]', true) ?: [];
                $new = json_decode($audit->new_values ?? '[]', true) ?: [];

                return collect($new)
                    ->filter(fn ($value, $key) => in_array($key, ['name', 'description', 'code', 'state', 'status', 'is_for_sale']))
                    ->map(fn ($value, $key) => [
                        'datetime' => Carbon::parse($audit->created_at)->toIso8601String(),
                        'date'     => Carbon::parse($audit->created_at)->toDateString(),
                        'type'     => in_array($key, ['state', 'status', 'is_for_sale']) ? 'status' : 'content',
                        'field'    => (string)$key,
                        'subjects' => [$labels[$audit->auditable_id]],
                        'old'      => is_scalar($old[$key] ?? null) ? mb_substr(strip_tags((string)$old[$key]), 0, 60) : null,
                        'new'      => is_scalar($value) ? mb_substr(strip_tags((string)$value), 0, 60) : null,
                        'changes'  => 1,
                        'shops'    => isset($shopOf[$audit->auditable_id]) ? array_filter([$this->shops[$shopOf[$audit->auditable_id]]->code ?? null]) : [],
                        'details'  => [],
                        'user_id'  => $audit->user_type === 'User' ? $audit->user_id : null,
                    ])
                    ->values();
            });

        $users = DB::table('users')->whereIn('id', $events->pluck('user_id')->filter()->unique())->pluck('contact_name', 'id');

        return $events->map(fn ($event) => [...Arr::except($event, 'user_id'), 'user' => $users[$event['user_id']] ?? null])->values()->all();
    }
}
