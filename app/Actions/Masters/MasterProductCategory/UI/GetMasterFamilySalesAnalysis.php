<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 17:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Masters\MasterProductCategory;
use Carbon\Carbon;
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

    /**
     * @param array{from?: string|null, to?: string|null, compareFrom?: string|null, compareTo?: string|null} $modelData
     */
    public function handle(MasterProductCategory $masterFamily, array $modelData): array
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

        $this->shopFamilies = DB::table('product_categories')
            ->where('master_product_category_id', $masterFamily->id)
            ->select(['id', 'shop_id', 'organisation_id', 'state', 'webpage_id'])
            ->get();
        $this->shops = DB::table('shops')
            ->whereIn('id', $this->shopFamilies->pluck('shop_id'))
            ->select(['id', 'code', 'name', 'slug', 'organisation_id', 'state'])
            ->get()
            ->keyBy('id');

        $stockOuts        = GetMasterFamilyStockOuts::run($masterFamily, $from, $to);
        $compareStockOuts = GetMasterFamilyStockOuts::run($masterFamily, $compareFrom, $compareTo);

        return [
            'period'         => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'compare_period' => ['from' => $compareFrom->toDateString(), 'to' => $compareTo->toDateString()],
            'frequency'      => $frequency->value,
            'currency'       => $masterFamily->group->currency->code,
            'sales'          => $this->salesSeries($masterFamily, $frequency, $from, $to),
            'compare_sales'  => $this->salesSeries($masterFamily, $frequency, $compareFrom, $compareTo),
            'totals'         => [
                'current'  => [
                    ...$this->salesTotals($masterFamily, $from, $to),
                    ...$this->stockOutTotals($stockOuts),
                ],
                'previous' => [
                    ...$this->salesTotals($masterFamily, $compareFrom, $compareTo),
                    ...$this->stockOutTotals($compareStockOuts),
                ],
            ],
            'shops'          => $this->byShop($from, $to, $compareFrom, $compareTo, $stockOuts),
            'products'       => $this->byProduct($masterFamily, $from, $to, $compareFrom, $compareTo, $stockOuts),
            'stock_outs'     => $stockOuts,
            'traffic'        => $this->traffic($masterFamily, $frequency, $from, $to),
            'events'         => $this->events($masterFamily, $from, $to),
        ];
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

    private function salesSeries(MasterProductCategory $masterFamily, TimeSeriesFrequencyEnum $frequency, Carbon $from, Carbon $to): array
    {
        return DB::table('master_product_category_time_series_records as records')
            ->join('master_product_category_time_series as series', 'series.id', 'records.master_product_category_time_series_id')
            ->where('series.master_product_category_id', $masterFamily->id)
            ->where('series.frequency', $frequency->value)
            ->where('records.frequency', $this->recordFrequencyCode($frequency))
            ->where('records.to', '>=', $from->toDateString())
            ->where('records.from', '<=', $to->toDateString())
            ->orderBy('records.from')
            ->selectRaw('records."from"::text as date, round(coalesce(records.sales_grp_currency_external, 0), 2)::float as sales, coalesce(records.orders, 0) as orders')
            ->get()
            ->all();
    }

    private function salesTotals(MasterProductCategory $masterFamily, Carbon $from, Carbon $to): array
    {
        $totals = DB::table('master_product_category_time_series_records as records')
            ->join('master_product_category_time_series as series', 'series.id', 'records.master_product_category_time_series_id')
            ->where('series.master_product_category_id', $masterFamily->id)
            ->where('series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->where('records.frequency', 'D')
            ->whereBetween('records.from', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('coalesce(sum(records.sales_grp_currency_external), 0)::float as sales, coalesce(sum(records.orders), 0)::int as orders, coalesce(sum(records.invoices), 0)::int as invoices, coalesce(sum(records.refunds), 0)::int as refunds')
            ->first();

        return [
            'sales'    => round($totals->sales, 2),
            'orders'   => $totals->orders,
            'invoices' => $totals->invoices,
            'refunds'  => $totals->refunds,
        ];
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
        $sales = DB::table('product_category_time_series_records as records')
            ->join('product_category_time_series as series', 'series.id', 'records.product_category_time_series_id')
            ->whereIn('series.product_category_id', $this->shopFamilies->pluck('id'))
            ->where('series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->where('records.frequency', 'D')
            ->where(function ($query) use ($from, $to, $compareFrom, $compareTo) {
                $query->whereBetween('records.from', [$from->toDateString(), $to->toDateString()])
                    ->orWhereBetween('records.from', [$compareFrom->toDateString(), $compareTo->toDateString()]);
            })
            ->groupBy('series.product_category_id')
            ->selectRaw(
                'series.product_category_id,
                coalesce(sum(records.sales_grp_currency_external) filter (where records."from" between ? and ?), 0)::float as sales,
                coalesce(sum(records.sales_grp_currency_external) filter (where records."from" between ? and ?), 0)::float as previous_sales,
                coalesce(sum(records.orders) filter (where records."from" between ? and ?), 0)::int as orders,
                coalesce(sum(records.orders) filter (where records."from" between ? and ?), 0)::int as previous_orders',
                [
                    $from->toDateString(), $to->toDateString(), $compareFrom->toDateString(), $compareTo->toDateString(),
                    $from->toDateString(), $to->toDateString(), $compareFrom->toDateString(), $compareTo->toDateString(),
                ]
            )
            ->get()
            ->keyBy('product_category_id');

        $stockOutDaysByOrganisation = collect($stockOuts)->where('cause', '!=', 'discontinued')->groupBy('organisation_id')->map->sum('days');

        return $this->shopFamilies
            ->map(function ($family) use ($sales, $stockOutDaysByOrganisation) {
                $shop = $this->shops[$family->shop_id] ?? null;
                $row  = $sales[$family->id] ?? null;

                return [
                    'shop_id'         => $family->shop_id,
                    'shop_code'       => $shop?->code,
                    'shop_name'       => $shop?->name,
                    'shop_state'      => $shop?->state,
                    'organisation_id' => $family->organisation_id,
                    'family_state'    => $family->state,
                    'sales'           => round($row?->sales ?? 0, 2),
                    'previous_sales'  => round($row?->previous_sales ?? 0, 2),
                    'orders'          => $row?->orders ?? 0,
                    'previous_orders' => $row?->previous_orders ?? 0,
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

        $sales = DB::table('master_asset_time_series_records as records')
            ->join('master_asset_time_series as series', 'series.id', 'records.master_asset_time_series_id')
            ->whereIn('series.master_asset_id', $masterAssets->pluck('id'))
            ->where('series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->where('records.frequency', 'D')
            ->where(function ($query) use ($from, $to, $compareFrom, $compareTo) {
                $query->whereBetween('records.from', [$from->toDateString(), $to->toDateString()])
                    ->orWhereBetween('records.from', [$compareFrom->toDateString(), $compareTo->toDateString()]);
            })
            ->groupBy('series.master_asset_id')
            ->selectRaw(
                'series.master_asset_id,
                coalesce(sum(records.sales_grp_currency_external) filter (where records."from" between ? and ?), 0)::float as sales,
                coalesce(sum(records.sales_grp_currency_external) filter (where records."from" between ? and ?), 0)::float as previous_sales',
                [$from->toDateString(), $to->toDateString(), $compareFrom->toDateString(), $compareTo->toDateString()]
            )
            ->get()
            ->keyBy('master_asset_id');

        $listings = DB::table('products')
            ->whereIn('master_product_id', $masterAssets->pluck('id'))
            ->groupBy('master_product_id')
            ->selectRaw("master_product_id, count(*) filter (where state = 'active') as active, count(*) filter (where status = ?) as out_of_stock", [ProductStatusEnum::OUT_OF_STOCK->value])
            ->get()
            ->keyBy('master_product_id');

        $stockOutsByCode = collect($stockOuts)->where('cause', '!=', 'discontinued')->groupBy(fn ($stockOut) => strtolower($stockOut['code']));

        return $masterAssets
            ->map(function ($masterAsset) use ($sales, $listings, $stockOutsByCode) {
                $row       = $sales[$masterAsset->id] ?? null;
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
                    'sales'               => round($row?->sales ?? 0, 2),
                    'previous_sales'      => round($row?->previous_sales ?? 0, 2),
                    'websites'            => (int)($listings[$masterAsset->id]->active ?? 0),
                    'websites_out_of_stock' => (int)($listings[$masterAsset->id]->out_of_stock ?? 0),
                    'stock_outs'          => $stockOuts->count(),
                    'stock_out_days'      => $stockOuts->sum('days'),
                    'lost_sales'          => round($stockOuts->sum('lost_sales'), 2),
                ];
            })
            ->sortBy(fn ($row) => $row['sales'] - $row['previous_sales'])
            ->values()
            ->all();
    }

    private function traffic(MasterProductCategory $masterFamily, TimeSeriesFrequencyEnum $frequency, Carbon $from, Carbon $to): array
    {
        $webpageIds = $this->shopFamilies->pluck('webpage_id')->filter()
            ->merge(
                DB::table('products')
                    ->whereIn('master_product_id', DB::table('master_assets')->where('master_family_id', $masterFamily->id)->select('id'))
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
            ->whereIn('master_product_id', $masterAssets->keys())
            ->orWhereIn('family_id', $this->shopFamilies->pluck('id'))
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
