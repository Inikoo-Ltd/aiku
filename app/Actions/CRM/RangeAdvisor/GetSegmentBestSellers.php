<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\RangeAdvisor;

use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Best sellers of the last 12 months among customers like the given one, read from segment_product_sales.
 * Every product returned is live, in stock, priced and visible to the customer.
 */
class GetSegmentBestSellers
{
    use AsAction;
    use WithRecommendableProducts;

    private const array LEVEL_COLUMNS = [
        RebuildRangeAdvisorSales::LEVEL_TYPE_AREA    => ['shop_type', 'country_code', 'area'],
        RebuildRangeAdvisorSales::LEVEL_TYPE_COUNTRY => ['shop_type', 'country_code'],
        RebuildRangeAdvisorSales::LEVEL_TYPE_GROUP   => ['shop_type', 'region'],
        RebuildRangeAdvisorSales::LEVEL_TYPE         => ['shop_type'],
        RebuildRangeAdvisorSales::LEVEL_AREA         => ['country_code', 'area'],
        RebuildRangeAdvisorSales::LEVEL_COUNTRY      => ['country_code'],
        RebuildRangeAdvisorSales::LEVEL_GROUP        => ['region'],
        RebuildRangeAdvisorSales::LEVEL_SHOP         => [],
    ];

    /**
     * @param  array{kind?: 'product'|'family', sort?: 'customers'|'reorder_rate'|'revenue', limit?: int, customer?: ?\App\Models\CRM\Customer, exclude_product_ids?: array<int>, area?: ?string}  $options
     *
     * @return array{segment: ?array{level: string, shop_type: ?string, country_code: ?string, area: ?string, region: ?string, customers: int}, items: Collection}
     */
    public function handle(Shop $shop, ?string $shopType, ?string $countryCode, array $options = []): array
    {
        $kind     = $options['kind'] ?? 'product';
        $limit    = $options['limit'] ?? 24;
        $segments = $this->resolveSegments($shop, $shopType, $countryCode, $kind, $options['area'] ?? null);
        $items    = collect();

        foreach ($segments as $segment) {
            if ($items->count() >= $limit) {
                break;
            }

            $query = $kind === 'family'
                ? ProductCategory::query()
                    ->select('product_categories.*')
                    ->join('segment_product_sales', 'segment_product_sales.family_id', '=', 'product_categories.id')
                    ->where('product_categories.shop_id', $shop->id)
                    ->where('product_categories.state', ProductCategoryStateEnum::ACTIVE->value)
                    ->whereNull('segment_product_sales.product_id')
                    ->whereNotIn('product_categories.id', $items->pluck('id')->all())
                : $this->recommendableProducts($shop, $options['customer'] ?? null)
                    ->select('products.*')
                    ->join('segment_product_sales', 'segment_product_sales.product_id', '=', 'products.id')
                    ->whereNotIn('products.id', array_merge($options['exclude_product_ids'] ?? [], $items->pluck('id')->all()));

            $found = $this->rankedBySegment($query, $shop, $segment, $options['sort'] ?? 'customers')
                ->limit($limit - $items->count())
                ->get()
                ->each(fn ($item) => $item->setAttribute('segment_level', $segment['level']));

            $items = $items->concat($found);
        }

        return ['segment' => $segments[0] ?? null, 'items' => $items];
    }

    /**
     * @return ?array{level: string, shop_type: ?string, country_code: ?string, area: ?string, region: ?string, customers: int}
     */
    public function resolveSegment(Shop $shop, ?string $shopType, ?string $countryCode, string $kind = 'product', ?string $area = null): ?array
    {
        return $this->resolveSegments($shop, $shopType, $countryCode, $kind, $area)[0] ?? null;
    }

    /**
     * The segments that have rows, narrowest first: shop type and area, shop type and country,
     * shop type and country group, shop type, area, country, country group, the whole shop.
     * A level is skipped when its key is unknown (no shop type, no country, no area, no group).
     * A narrow segment lists few products, so the list is filled from the wider ones in this order
     * and each item says which level it came from (segment_level).
     *
     * @return array<int, array{level: string, shop_type: ?string, country_code: ?string, area: ?string, region: ?string, customers: int}>
     */
    public function resolveSegments(Shop $shop, ?string $shopType, ?string $countryCode, string $kind = 'product', ?string $area = null): array
    {
        $keys = [
            'shop_type'    => $shopType,
            'country_code' => $countryCode,
            'area'         => $area,
            'region'       => RebuildRangeAdvisorSales::regionOf($countryCode),
        ];

        $candidates = [];
        foreach (self::LEVEL_COLUMNS as $level => $columns) {
            $segment = ['level' => $level, 'shop_type' => null, 'country_code' => null, 'area' => null, 'region' => null];
            foreach ($columns as $column) {
                $segment[$column] = $keys[$column];
            }
            if (!in_array(null, array_intersect_key($segment, array_flip($columns)), true)) {
                $candidates[] = $segment;
            }
        }

        $found = DB::table('segment_product_sales')
            ->where('segment_product_sales.shop_id', $shop->id)
            ->when($kind === 'family', fn (QueryBuilder $query) => $query->whereNull('product_id'), fn (QueryBuilder $query) => $query->whereNotNull('product_id'))
            ->where(function (QueryBuilder $query) use ($candidates) {
                foreach ($candidates as $segment) {
                    $query->orWhere(fn (QueryBuilder $subQuery) => $this->whereSegment($subQuery, $segment));
                }
            })
            ->distinct()
            ->get(['level', 'segment_customers'])
            ->keyBy('level');

        $segments = [];
        foreach ($candidates as $segment) {
            if ($found->has($segment['level'])) {
                $segments[] = $segment + ['customers' => (int) $found->get($segment['level'])->segment_customers];
            }
        }

        return $segments;
    }

    /**
     * @param  array{level: string, shop_type: ?string, country_code: ?string, area: ?string, region: ?string}  $segment
     */
    private function rankedBySegment(Builder $query, Shop $shop, array $segment, string $sort = 'customers'): Builder
    {
        $orderBy = match ($sort) {
            'reorder_rate' => 'segment_product_sales.repeat_customers::numeric / segment_product_sales.customers desc, segment_product_sales.customers desc',
            'revenue'      => 'segment_product_sales.revenue desc',
            default        => 'segment_product_sales.customers desc, segment_product_sales.revenue desc',
        };

        return $query
            ->where('segment_product_sales.shop_id', $shop->id)
            ->where(fn ($subQuery) => $this->whereSegment($subQuery->getQuery(), $segment))
            ->addSelect([
                'segment_product_sales.customers as segment_customers_buying',
                'segment_product_sales.repeat_customers as segment_repeat_customers',
                'segment_product_sales.revenue as segment_revenue',
            ])
            ->selectRaw('round(segment_product_sales.repeat_customers::numeric / segment_product_sales.customers, 2) as segment_reorder_rate')
            ->withCasts(['segment_reorder_rate' => 'float', 'segment_revenue' => 'float'])
            ->orderByRaw($orderBy)
            ->orderBy($query->getModel()->getQualifiedKeyName());
    }

    private function whereSegment(QueryBuilder $query, array $segment): QueryBuilder
    {
        $query->where('segment_product_sales.level', $segment['level']);
        foreach (['shop_type', 'country_code', 'area', 'region'] as $column) {
            if ($segment[$column] !== null) {
                $query->where("segment_product_sales.$column", $segment[$column]);
            }
        }

        return $query;
    }
}
