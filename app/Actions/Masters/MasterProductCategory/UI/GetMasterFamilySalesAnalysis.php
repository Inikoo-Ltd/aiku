<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 17:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Masters\MasterProductCategory;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMasterFamilySalesAnalysis
{
    use AsAction;

    private const int MAX_EVENTS = 400;

    private const array PRICE_KEYS = ['price', 'rrp'];
    private const array CONTENT_KEYS = ['name', 'description', 'description_extra', 'description_title', 'code'];
    private const array STATUS_KEYS = ['status', 'state', 'is_for_sale'];

    private Collection $shopFamilies;
    private Collection $shops;
    private Collection $familyProducts;
    private bool $isFiltered = false;

    /**
     * @param array{from?: string|null, to?: string|null, compareFrom?: string|null, compareTo?: string|null, organisations?: array|string|null, shops?: array|string|null} $modelData
     */
    public function handle(MasterProductCategory $masterFamily, array $modelData, bool $withDetails = true): array
    {
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

        $frequency = $this->frequency($from, $to);

        $allShopFamilies = DB::table('product_categories')
            ->where('master_product_category_id', $masterFamily->id)
            ->select(['id', 'shop_id', 'organisation_id', 'state', 'webpage_id'])
            ->get();
        $this->shops = DB::table('shops')
            ->whereIn('id', $allShopFamilies->pluck('shop_id'))
            ->select(['id', 'code', 'name', 'slug', 'organisation_id', 'state'])
            ->orderBy('code')
            ->get()
            ->keyBy('id');
        $organisations = DB::table('organisations')
            ->whereIn('id', $allShopFamilies->pluck('organisation_id')->unique())
            ->select(['id', 'slug', 'code', 'name'])
            ->orderBy('id')
            ->get();

        $selectedOrganisations = $organisations->whereIn('slug', $this->slugs(Arr::get($modelData, 'organisations')))->values();
        $selectedShops         = $this->shops
            ->whereIn('slug', $this->slugs(Arr::get($modelData, 'shops')))
            ->when($selectedOrganisations->isNotEmpty(), fn (Collection $shops) => $shops->whereIn('organisation_id', $selectedOrganisations->pluck('id')))
            ->values();

        $this->isFiltered   = $selectedOrganisations->isNotEmpty() || $selectedShops->isNotEmpty();
        $this->shopFamilies = $allShopFamilies
            ->when($selectedOrganisations->isNotEmpty(), fn (Collection $families) => $families->whereIn('organisation_id', $selectedOrganisations->pluck('id')))
            ->when($selectedShops->isNotEmpty(), fn (Collection $families) => $families->whereIn('shop_id', $selectedShops->pluck('id')))
            ->values();

        $this->familyProducts = DB::table('products')
            ->whereIn('id', GetMasterFamilyStockOuts::familyProductIds($masterFamily))
            ->whereIn('shop_id', $this->filteredShopIds())
            ->whereNotNull('asset_id')
            ->select(['id', 'asset_id', 'shop_id', 'master_product_id'])
            ->get();

        $stockOuts        = $this->filterStockOuts(GetMasterFamilyStockOuts::run($masterFamily, $from, $to));
        $compareStockOuts = $withDetails ? $this->filterStockOuts(GetMasterFamilyStockOuts::run($masterFamily, $compareFrom, $compareTo)) : [];

        return [
            'filters'        => [
                'organisations'          => $organisations->map(fn ($organisation) => [
                    'slug' => $organisation->slug,
                    'code' => $organisation->code,
                    'name' => $organisation->name,
                ])->all(),
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
            'period'         => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'compare_period' => ['from' => $compareFrom->toDateString(), 'to' => $compareTo->toDateString()],
            'frequency'      => $frequency->value,
            'currency'       => $masterFamily->group->currency->code,
            'sales'          => $this->salesSeries($frequency, $from, $to),
            'compare_sales'  => $this->salesSeries($frequency, $compareFrom, $compareTo),
            'totals'         => [
                'current'  => [
                    ...$this->salesTotals($from, $to),
                    ...$this->stockOutTotals($stockOuts),
                ],
                'previous' => [
                    ...$this->salesTotals($compareFrom, $compareTo),
                    ...$this->stockOutTotals($compareStockOuts),
                ],
            ],
            'shops'          => $this->byShop($from, $to, $compareFrom, $compareTo, $stockOuts),
            'products'       => $this->byProduct($masterFamily, $from, $to, $compareFrom, $compareTo, $stockOuts),
            'stock_outs'     => $stockOuts,
            'skos'           => $this->stockKeepingUnitCount(),
            'traffic'        => $withDetails ? $this->traffic($masterFamily, $frequency, $from, $to) : [],
            'events'         => $withDetails ? $this->events($masterFamily, $from, $to) : [],
        ];
    }

    /**
     * @return array{period: array, compare_period: array, currency: string, frequency: string, sales: array, compare_sales: array, totals: array, shops: array, products: array}
     */
    public function teaser(MasterProductCategory $masterFamily): array
    {
        $analysis = $this->handle($masterFamily, [], withDetails: false);

        return [
            ...Arr::only($analysis, ['period', 'compare_period', 'currency', 'frequency', 'sales', 'compare_sales', 'totals']),
            'shops'    => $this->movers($analysis['shops']),
            'products' => $this->movers(array_filter($analysis['products'], fn ($product) => $product['id'] !== 0)),
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

    private function filterStockOuts(array $stockOuts): array
    {
        if (!$this->isFiltered) {
            return $stockOuts;
        }

        $organisationIds = $this->shopFamilies->pluck('organisation_id')->unique()->all();

        return array_values(array_filter($stockOuts, fn ($stockOut) => in_array($stockOut['organisation_id'], $organisationIds)));
    }

    private function stockKeepingUnitCount(): int
    {
        return DB::table('product_has_org_stocks')
            ->join('org_stocks', 'org_stocks.id', 'product_has_org_stocks.org_stock_id')
            ->whereIn('product_has_org_stocks.product_id', $this->familyProducts->pluck('id'))
            ->whereNotIn('org_stocks.state', [OrgStockStateEnum::DISCONTINUED->value, OrgStockStateEnum::DISCONTINUING->value])
            ->distinct()
            ->count('product_has_org_stocks.org_stock_id');
    }

    private function filteredShopIds(): array
    {
        return $this->shopFamilies->pluck('shop_id')->unique()->values()->all();
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
        $unit = match ($frequency) {
            TimeSeriesFrequencyEnum::DAILY => 'day',
            TimeSeriesFrequencyEnum::WEEKLY => 'week',
            default => 'month',
        };

        $rows = $this->invoiceLines($from, $to)
            ->groupByRaw('1')
            ->selectRaw("date_trunc('$unit', invoice_transactions.date)::date::text as date, sum(invoice_transactions.grp_net_amount)::float as sales, count(distinct invoice_transactions.order_id) as orders")
            ->get()
            ->keyBy('date');

        $bucket = match ($unit) {
            'day' => $from->copy(),
            'week' => $from->copy()->startOfWeek(Carbon::MONDAY),
            default => $from->copy()->startOfMonth(),
        };

        $series = [];
        while ($bucket->lte($to)) {
            $date     = $bucket->toDateString();
            $series[] = [
                'date'   => $date,
                'sales'  => round($rows[$date]->sales ?? 0, 2),
                'orders' => (int)($rows[$date]->orders ?? 0),
            ];
            $bucket->addUnit($unit);
        }

        return $series;
    }

    private function salesTotals(Carbon $from, Carbon $to): array
    {
        $totals = $this->invoiceLines($from, $to)
            ->selectRaw(
                'coalesce(sum(invoice_transactions.grp_net_amount), 0)::float as sales,
                count(distinct invoice_transactions.order_id) as orders,
                count(distinct invoice_transactions.invoice_id) filter (where invoices.type = ?) as invoices,
                count(distinct invoice_transactions.invoice_id) filter (where invoices.type = ?) as refunds,
                count(distinct invoice_transactions.customer_id) as customers',
                [InvoiceTypeEnum::INVOICE->value, InvoiceTypeEnum::REFUND->value]
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
            ->join('invoices', 'invoices.id', 'invoice_transactions.invoice_id')
            ->whereRaw('invoice_transactions.asset_id = any(?::int[])', ['{'.$this->familyProducts->pluck('asset_id')->unique()->implode(',').'}'])
            ->whereNull('invoice_transactions.deleted_at')
            ->whereNull('invoices.deleted_at')
            ->whereNull('invoices.as_organisation_id')
            ->whereBetween('invoice_transactions.date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
    }

    /**
     * @return Collection<int|string, object{sales: float, orders: int}>
     */
    private function salesBy(string $column, Carbon $from, Carbon $to): Collection
    {
        return $this->invoiceLines($from, $to)
            ->groupBy("invoice_transactions.$column")
            ->selectRaw("invoice_transactions.$column as key, coalesce(sum(invoice_transactions.grp_net_amount), 0)::float as sales, count(distinct invoice_transactions.order_id) as orders")
            ->get()
            ->keyBy('key');
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

        return $this->shopFamilies
            ->map(function ($family) use ($sales, $previousSales, $stockOutDaysByOrganisation) {
                $shop     = $this->shops[$family->shop_id] ?? null;
                $row      = $sales[$family->shop_id] ?? null;
                $previous = $previousSales[$family->shop_id] ?? null;

                return [
                    'shop_id'         => $family->shop_id,
                    'shop_code'       => $shop?->code,
                    'shop_name'       => $shop?->name,
                    'shop_state'      => $shop?->state,
                    'organisation_id' => $family->organisation_id,
                    'family_state'    => $family->state,
                    'sales'           => round($row?->sales ?? 0, 2),
                    'previous_sales'  => round($previous?->sales ?? 0, 2),
                    'orders'          => (int)($row?->orders ?? 0),
                    'previous_orders' => (int)($previous?->orders ?? 0),
                    'stock_out_days'  => $stockOutDaysByOrganisation[$family->organisation_id] ?? 0,
                ];
            })
            ->sortBy(fn ($row) => $row['sales'] - $row['previous_sales'])
            ->values()
            ->all();
    }

    private function byProduct(MasterProductCategory $masterFamily, Carbon $from, Carbon $to, Carbon $compareFrom, Carbon $compareTo, array $stockOuts): array
    {
        $masterAssets = DB::table('master_assets')
            ->where('master_family_id', $masterFamily->id)
            ->select(['id', 'code', 'name', 'slug', 'status', 'is_for_sale', 'created_at', 'discontinued_at'])
            ->get();

        $masterAssetByAsset = $this->familyProducts->pluck('master_product_id', 'asset_id');
        $sumByMasterAsset   = fn (Collection $rows) => $rows
            ->groupBy(fn ($row) => $masterAssetByAsset[$row->key] ?? 0)
            ->map(fn (Collection $group) => $group->sum('sales'));

        $sales         = $sumByMasterAsset($this->salesBy('asset_id', $from, $to));
        $previousSales = $sumByMasterAsset($this->salesBy('asset_id', $compareFrom, $compareTo));

        $listings = DB::table('products')
            ->whereIn('master_product_id', $masterAssets->pluck('id'))
            ->when($this->isFiltered, fn ($query) => $query->whereIn('shop_id', $this->filteredShopIds()))
            ->groupBy('master_product_id')
            ->selectRaw("master_product_id, count(*) filter (where state = 'active') as active, count(*) filter (where status = ?) as out_of_stock", [ProductStatusEnum::OUT_OF_STOCK->value])
            ->get()
            ->keyBy('master_product_id');

        $stockOutsByCode = collect($stockOuts)->where('cause', '!=', 'discontinued')->groupBy(fn ($stockOut) => strtolower($stockOut['code']));

        return $masterAssets
            ->map(function ($masterAsset) use ($sales, $previousSales, $listings, $stockOutsByCode) {
                $stockOuts = $stockOutsByCode->get(strtolower($masterAsset->code), collect());

                return [
                    'id'                  => $masterAsset->id,
                    'code'                => $masterAsset->code,
                    'name'                => $masterAsset->name,
                    'slug'                => $masterAsset->slug,
                    'status'              => (bool)$masterAsset->status,
                    'is_for_sale'         => (bool)$masterAsset->is_for_sale,
                    'created_at'          => $masterAsset->created_at ? Carbon::parse($masterAsset->created_at)->toDateString() : null,
                    'discontinued_at'     => $masterAsset->discontinued_at ? Carbon::parse($masterAsset->discontinued_at)->toDateString() : null,
                    'sales'               => round($sales[$masterAsset->id] ?? 0, 2),
                    'previous_sales'      => round($previousSales[$masterAsset->id] ?? 0, 2),
                    'websites'            => (int)($listings[$masterAsset->id]->active ?? 0),
                    'websites_out_of_stock' => (int)($listings[$masterAsset->id]->out_of_stock ?? 0),
                    'stock_outs'          => $stockOuts->count(),
                    'stock_out_days'      => $stockOuts->sum('days'),
                    'lost_sales'          => round($stockOuts->sum('lost_sales'), 2),
                ];
            })
            ->when(
                ($sales[0] ?? 0) || ($previousSales[0] ?? 0),
                fn (Collection $rows) => $rows->push([
                    'id'                    => 0,
                    'code'                  => __('Other'),
                    'name'                  => __('Products in these families not linked to a master product'),
                    'slug'                  => null,
                    'status'                => true,
                    'is_for_sale'           => true,
                    'created_at'            => null,
                    'discontinued_at'       => null,
                    'sales'                 => round($sales[0] ?? 0, 2),
                    'previous_sales'        => round($previousSales[0] ?? 0, 2),
                    'websites'              => 0,
                    'websites_out_of_stock' => 0,
                    'stock_outs'            => 0,
                    'stock_out_days'        => 0,
                    'lost_sales'            => 0,
                ])
            )
            ->sortBy([
                fn ($a, $b) => (!$a['sales'] && !$a['previous_sales']) <=> (!$b['sales'] && !$b['previous_sales']),
                fn ($a, $b) => ($a['sales'] - $a['previous_sales']) <=> ($b['sales'] - $b['previous_sales']),
            ])
            ->values()
            ->all();
    }

    private function traffic(MasterProductCategory $masterFamily, TimeSeriesFrequencyEnum $frequency, Carbon $from, Carbon $to): array
    {
        $webpageIds = $this->shopFamilies->pluck('webpage_id')->filter()
            ->merge(
                DB::table('products')
                    ->whereIn('master_product_id', DB::table('master_assets')->where('master_family_id', $masterFamily->id)->select('id'))
                    ->when($this->isFiltered, fn ($query) => $query->whereIn('shop_id', $this->filteredShopIds()))
                    ->whereNotNull('webpage_id')
                    ->pluck('webpage_id')
            )
            ->unique()
            ->values();

        if ($webpageIds->isEmpty()) {
            return [];
        }

        return DB::table('webpage_time_series_records as records')
            ->join('webpage_time_series as series', 'series.id', 'records.webpage_time_series_id')
            ->whereIn('series.webpage_id', $webpageIds)
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

    private function events(MasterProductCategory $masterFamily, Carbon $from, Carbon $to): array
    {
        $end = $to->copy()->endOfDay();

        $masterAssets = DB::table('master_assets')->where('master_family_id', $masterFamily->id)->pluck('code', 'id');
        $products     = DB::table('products')
            ->whereIn('id', $this->familyProducts->pluck('id'))
            ->select(['id', 'code', 'shop_id', 'created_at', 'master_product_id'])
            ->get()
            ->keyBy('id');

        $events = collect()
            ->concat($this->auditEvents('MasterProductCategory', [$masterFamily->id => $masterFamily->code], null, $from, $end))
            ->concat($this->auditEvents('MasterAsset', $masterAssets->all(), null, $from, $end))
            ->concat($this->auditEvents('ProductCategory', $this->shopFamilies->pluck('id', 'id')->map(fn () => $masterFamily->code)->all(), $this->shopFamilies->pluck('shop_id', 'id')->all(), $from, $end))
            ->concat($this->auditEvents('Product', $products->pluck('code', 'id')->all(), $products->pluck('shop_id', 'id')->all(), $from, $end))
            ->concat($this->launchEvents($products, $from, $end))
            ->concat($this->offerEvents($products->keys()->all(), $from, $end))
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
            ->whereIn('auditable_id', array_keys($labels))
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

    private function launchEvents(Collection $products, Carbon $from, Carbon $end): Collection
    {
        return $products
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

    private function offerEvents(array $productIds, Carbon $from, Carbon $end): Collection
    {
        return DB::table('offers')
            ->whereNull('deleted_at')
            ->where(function ($query) use ($productIds) {
                $query->where(function ($query) {
                    $query->where('trigger_type', 'ProductCategory')->whereIn('trigger_id', $this->shopFamilies->pluck('id'));
                })->orWhere(function ($query) use ($productIds) {
                    $query->where('trigger_type', 'Product')->whereIn('trigger_id', $productIds);
                });
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
        $shopByWebpage = $this->shopFamilies->filter(fn ($family) => $family->webpage_id)->pluck('shop_id', 'webpage_id');
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
                'user_id'  => $snapshot->publisher_type === 'User' ? $snapshot->publisher_id : null,
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
