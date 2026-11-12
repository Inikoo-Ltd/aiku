<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\RangeAdvisor;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Products of a shop that sold mostly in the run-up to the last finished Christmas (1 September to
 * 24 December): at least SEASON_SHARE of their orders of that year fell in the season, which is a
 * third of the year, so an all-year best seller is left out and a gift that never says Christmas
 * in its name is kept. A product launched during that year is left out: all its orders fall in the
 * season only because it was new. Ranked by the number of customers who bought it in the season.
 *
 * Privacy: a product needs at least RebuildRangeAdvisorSales::MIN_GROUP_SIZE season buyers, and
 * only the ranked product ids leave this class, never counts.
 *
 * ponytail: shop level only and last year's season only; add the segment levels of
 * segment_product_sales, or this year's season so far, when the answers need them.
 * ponytail: the ranking is read from the orders once a day per shop and cached; move it into
 * the nightly range-advisor:rebuild-sales if the first question of the day gets slow.
 */
class GetChristmasBestSellers
{
    use AsAction;
    use WithRecommendableProducts;

    public const float SEASON_SHARE = 0.6;

    private const int RANKED = 200;

    /**
     * @param  ?array<int, int>  $onlyProductIds  keep only these products, e.g. the ones matching what the customer asked for
     *
     * @return Collection<int, Product>
     */
    public function handle(Shop $shop, ?Customer $customer = null, int $limit = 12, ?array $onlyProductIds = null): Collection
    {
        $ranked = $this->rankedProductIds($shop);
        if ($onlyProductIds !== null) {
            $ranked = array_values(array_intersect($ranked, $onlyProductIds));
        }

        if (!$ranked) {
            return collect();
        }

        $position = array_flip($ranked);

        return $this->recommendableProducts($shop, $customer)
            ->select('products.*')
            ->whereIn('products.id', $ranked)
            ->get()
            ->sortBy(fn (Product $product) => $position[$product->id])
            ->take($limit)
            ->values()
            ->toBase();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function season(): array
    {
        $now  = CarbonImmutable::now();
        $year = $now->gt($now->setDate($now->year, 12, 24)->endOfDay()) ? $now->year : $now->year - 1;

        return [CarbonImmutable::create($year, 9, 1)->startOfDay(), CarbonImmutable::create($year, 12, 24)->endOfDay()];
    }

    /**
     * @return array<int, int>
     */
    private function rankedProductIds(Shop $shop): array
    {
        [$from, $to] = $this->season();

        return Cache::remember("range_advisor:christmas_best_sellers:$shop->id:".$to->year, now()->addDay(), fn () => collect(DB::select(
            <<<'SQL'
            select leaders.id
            from orders
            join transactions on transactions.order_id = orders.id
            join products bought on bought.id = transactions.model_id
            left join variants on variants.id = bought.variant_id
            join products leaders on leaders.id = coalesce(variants.leader_id, bought.id)
            where orders.shop_id = ?
                and orders.customer_id is not null
                and orders.state not in (?, ?)
                and orders.date > ?
                and orders.date <= ?
                and transactions.model_type = 'Product'
                and transactions.deleted_at is null
                and transactions.is_gift = false
                and leaders.created_at <= ?
            group by leaders.id
            having count(distinct orders.customer_id) filter (where orders.date >= ?) >= ?
                and count(distinct orders.id) filter (where orders.date >= ?) >= ?::numeric * count(distinct orders.id)
            order by count(distinct orders.customer_id) filter (where orders.date >= ?) desc, leaders.id
            limit ?
            SQL,
            [
                $shop->id,
                OrderStateEnum::CREATING->value,
                OrderStateEnum::CANCELLED->value,
                $to->subYear()->toDateTimeString(),
                $to->toDateTimeString(),
                $to->subYear()->toDateTimeString(),
                $from->toDateTimeString(),
                RebuildRangeAdvisorSales::MIN_GROUP_SIZE,
                $from->toDateTimeString(),
                self::SEASON_SHARE,
                $from->toDateTimeString(),
                self::RANKED,
            ]
        ))->pluck('id')->all());
    }
}
