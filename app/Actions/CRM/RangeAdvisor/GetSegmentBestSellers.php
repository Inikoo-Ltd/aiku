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
        RebuildRangeAdvisorSales::LEVEL_TYPE_COUNTRY => ['shop_type', 'country_code'],
        RebuildRangeAdvisorSales::LEVEL_TYPE_REGION  => ['shop_type', 'region'],
        RebuildRangeAdvisorSales::LEVEL_TYPE         => ['shop_type'],
        RebuildRangeAdvisorSales::LEVEL_COUNTRY      => ['country_code'],
        RebuildRangeAdvisorSales::LEVEL_REGION       => ['region'],
        RebuildRangeAdvisorSales::LEVEL_SHOP         => [],
    ];

    /**
     * @param  array{kind?: 'product'|'family', sort?: 'customers'|'reorder_rate'|'revenue', limit?: int, customer?: ?\App\Models\CRM\Customer, exclude_product_ids?: array<int>}  $options
     *
     * @return array{segment: ?array{level: string, shop_type: ?string, country_code: ?string, region: ?string, customers: int}, items: Collection}
     */
    public function handle(Shop $shop, ?string $shopType, ?string $countryCode, array $options = []): array
    {
        $kind    = $options['kind'] ?? 'product';
        $segment = $this->resolveSegment($shop, $shopType, $countryCode, $kind);

        if (!$segment) {
            return ['segment' => null, 'items' => collect()];
        }

        $query = $kind === 'family'
            ? ProductCategory::query()
                ->select('product_categories.*')
                ->join('segment_product_sales', 'segment_product_sales.family_id', '=', 'product_categories.id')
                ->where('product_categories.shop_id', $shop->id)
                ->where('product_categories.state', ProductCategoryStateEnum::ACTIVE->value)
                ->whereNull('segment_product_sales.product_id')
            : $this->recommendableProducts($shop, $options['customer'] ?? null)
                ->select('products.*')
                ->join('segment_product_sales', 'segment_product_sales.product_id', '=', 'products.id')
                ->whereNotIn('products.id', $options['exclude_product_ids'] ?? []);

        $items = $this->rankedBySegment($query, $shop, $segment, $options['sort'] ?? 'customers')
            ->limit($options['limit'] ?? 24)
            ->get();

        return ['segment' => $segment, 'items' => $items];
    }

    /**
     * The narrowest segment that has rows, in this order: shop type and country, shop type and region,
     * shop type, country, region, the whole shop. A level is skipped when its key is unknown
     * (no shop type, no country, a country without continent).
     *
     * @return ?array{level: string, shop_type: ?string, country_code: ?string, region: ?string, customers: int}
     */
    public function resolveSegment(Shop $shop, ?string $shopType, ?string $countryCode, string $kind = 'product'): ?array
    {
        $keys = [
            'shop_type'    => $shopType,
            'country_code' => $countryCode,
            'region'       => $countryCode ? DB::table('countries')->where('code', $countryCode)->value('continent') : null,
        ];

        $candidates = [];
        foreach (self::LEVEL_COLUMNS as $level => $columns) {
            $segment = ['level' => $level, 'shop_type' => null, 'country_code' => null, 'region' => null];
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

        foreach ($candidates as $segment) {
            if ($found->has($segment['level'])) {
                return $segment + ['customers' => (int) $found->get($segment['level'])->segment_customers];
            }
        }

        return null;
    }

    /**
     * @param  array{level: string, shop_type: ?string, country_code: ?string, region: ?string}  $segment
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
        foreach (['shop_type', 'country_code', 'region'] as $column) {
            if ($segment[$column] !== null) {
                $query->where("segment_product_sales.$column", $segment[$column]);
            }
        }

        return $query;
    }
}
