<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\RangeAdvisor;

use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Rebuilds, for one shop, the two rollups the range advisor reads: what each customer bought
 * (customer_product_purchases, last 36 months) and what each customer segment buys
 * (segment_product_sales, last 12 months).
 *
 * Purchases of a variant option are counted on the variant leader, the only one shown on the website.
 *
 * Privacy: a segment row is kept only when the segment has at least MIN_GROUP_SIZE buyers and the
 * product or family itself was bought by at least MIN_GROUP_SIZE of them, so no row reveals one
 * customer's spend.
 *
 * ponytail: the shop's rows are deleted and reinserted in one transaction, which bloats both tables
 * until autovacuum; build into a staging table and swap if the rebuild ever gets slow or contended.
 * A family's repeat buyers are counted on the family's own orders, so the orders are read a second
 * time; keep a customer_family_purchases table if that second read ever costs too much.
 */
class RebuildRangeAdvisorSales
{
    use AsAction;

    public const int HISTORY_MONTHS = 36;

    public const int WINDOW_MONTHS = 12;

    public const int MIN_GROUP_SIZE = 5;

    public const string LEVEL_TYPE_COUNTRY = 'type_country';

    public const string LEVEL_TYPE_REGION = 'type_region';

    public const string LEVEL_TYPE = 'type';

    public const string LEVEL_COUNTRY = 'country';

    public const string LEVEL_REGION = 'region';

    public const string LEVEL_SHOP = 'shop';

    public string $commandSignature = 'range-advisor:rebuild-sales {--shop= : shop slug, default every open B2B shop}';

    public string $commandDescription = 'Rebuild what each customer bought and the best sellers per customer segment, per shop';

    public function handle(Shop $shop): void
    {
        $windowStart  = now()->subMonths(self::WINDOW_MONTHS)->toDateTimeString();
        $historyStart = now()->subMonths(self::HISTORY_MONTHS)->toDateTimeString();

        DB::transaction(function () use ($shop, $windowStart, $historyStart) {
            DB::table('segment_product_sales')->where('shop_id', $shop->id)->delete();
            DB::table('customer_product_purchases')->where('shop_id', $shop->id)->delete();

            DB::insert(
                <<<'SQL'
                insert into customer_product_purchases
                    (customer_id, shop_id, product_id, family_id, orders, orders_12m, quantity_12m, spend_12m, first_ordered_at, last_ordered_at)
                select
                    orders.customer_id,
                    orders.shop_id,
                    leaders.id,
                    leaders.family_id,
                    count(distinct orders.id),
                    count(distinct orders.id) filter (where orders.date >= ?),
                    coalesce(sum(transactions.quantity_ordered) filter (where orders.date >= ?), 0),
                    coalesce(sum(transactions.net_amount) filter (where orders.date >= ?), 0),
                    min(orders.date),
                    max(orders.date)
                from orders
                join transactions on transactions.order_id = orders.id
                join products bought on bought.id = transactions.model_id
                left join variants on variants.id = bought.variant_id
                join products leaders on leaders.id = coalesce(variants.leader_id, bought.id)
                where orders.shop_id = ?
                    and orders.customer_id is not null
                    and orders.state not in (?, ?)
                    and orders.date >= ?
                    and transactions.model_type = 'Product'
                    and transactions.deleted_at is null
                    and transactions.is_gift = false
                group by orders.customer_id, orders.shop_id, leaders.id, leaders.family_id
                SQL,
                [
                    $windowStart,
                    $windowStart,
                    $windowStart,
                    $shop->id,
                    OrderStateEnum::CREATING->value,
                    OrderStateEnum::CANCELLED->value,
                    $historyStart,
                ]
            );

            DB::insert(
                <<<'SQL'
                insert into segment_product_sales
                    (shop_id, level, shop_type, country_code, region, product_id, family_id, segment_customers, customers, repeat_customers, revenue)
                with buyers as (
                    select
                        purchases.customer_id,
                        purchases.product_id,
                        purchases.family_id,
                        purchases.orders_12m,
                        purchases.spend_12m,
                        customers.shop_type,
                        customers.location->>0 as country_code,
                        countries.continent as region
                    from customer_product_purchases purchases
                    join customers on customers.id = purchases.customer_id
                    left join countries on countries.code = customers.location->>0
                    where purchases.shop_id = ?
                        and purchases.orders_12m > 0
                ),
                family_orders as (
                    select
                        orders.customer_id,
                        leaders.family_id,
                        count(distinct orders.id) as orders_12m
                    from orders
                    join transactions on transactions.order_id = orders.id
                    join products bought on bought.id = transactions.model_id
                    left join variants on variants.id = bought.variant_id
                    join products leaders on leaders.id = coalesce(variants.leader_id, bought.id)
                    where orders.shop_id = ?
                        and orders.customer_id is not null
                        and orders.state not in (?, ?)
                        and orders.date >= ?
                        and transactions.model_type = 'Product'
                        and transactions.deleted_at is null
                        and transactions.is_gift = false
                        and leaders.family_id is not null
                    group by orders.customer_id, leaders.family_id
                ),
                family_buyers as (
                    select
                        buyers.customer_id,
                        buyers.family_id,
                        buyers.shop_type,
                        buyers.country_code,
                        buyers.region,
                        family_orders.orders_12m,
                        sum(buyers.spend_12m) as spend_12m
                    from buyers
                    join family_orders on family_orders.customer_id = buyers.customer_id and family_orders.family_id = buyers.family_id
                    group by buyers.customer_id, buyers.family_id, buyers.shop_type, buyers.country_code, buyers.region, family_orders.orders_12m
                ),
                segments as (
                    select
                        grouping(shop_type, country_code, region) as grouping_id,
                        shop_type,
                        country_code,
                        region,
                        count(distinct customer_id) as segment_customers
                    from buyers
                    group by grouping sets ((shop_type, country_code), (shop_type, region), (shop_type), (country_code), (region), ())
                ),
                segment_rows as (
                    select
                        grouping(shop_type, country_code, region) as grouping_id,
                        shop_type,
                        country_code,
                        region,
                        product_id,
                        family_id,
                        count(distinct customer_id) as customers,
                        count(distinct customer_id) filter (where orders_12m >= 2) as repeat_customers,
                        sum(spend_12m) as revenue
                    from buyers
                    group by product_id, family_id, grouping sets ((shop_type, country_code), (shop_type, region), (shop_type), (country_code), (region), ())
                    union all
                    select
                        grouping(shop_type, country_code, region),
                        shop_type,
                        country_code,
                        region,
                        null::integer,
                        family_id,
                        count(distinct customer_id),
                        count(distinct customer_id) filter (where orders_12m >= 2),
                        sum(spend_12m)
                    from family_buyers
                    group by family_id, grouping sets ((shop_type, country_code), (shop_type, region), (shop_type), (country_code), (region), ())
                )
                select
                    ?,
                    case segment_rows.grouping_id when 1 then ? when 2 then ? when 3 then ? when 5 then ? when 6 then ? when 7 then ? end,
                    segment_rows.shop_type,
                    segment_rows.country_code,
                    segment_rows.region,
                    segment_rows.product_id,
                    segment_rows.family_id,
                    segments.segment_customers,
                    segment_rows.customers,
                    segment_rows.repeat_customers,
                    segment_rows.revenue
                from segment_rows
                join segments on segments.grouping_id = segment_rows.grouping_id
                    and segments.shop_type is not distinct from segment_rows.shop_type
                    and segments.country_code is not distinct from segment_rows.country_code
                    and segments.region is not distinct from segment_rows.region
                where segments.segment_customers >= ?
                    and segment_rows.customers >= ?
                    and (segment_rows.grouping_id & 4 = 4 or segment_rows.shop_type is not null)
                    and (segment_rows.grouping_id & 2 = 2 or segment_rows.country_code is not null)
                    and (segment_rows.grouping_id & 1 = 1 or segment_rows.region is not null)
                SQL,
                [
                    $shop->id,
                    $shop->id,
                    OrderStateEnum::CREATING->value,
                    OrderStateEnum::CANCELLED->value,
                    $windowStart,
                    $shop->id,
                    self::LEVEL_TYPE_COUNTRY,
                    self::LEVEL_TYPE_REGION,
                    self::LEVEL_TYPE,
                    self::LEVEL_COUNTRY,
                    self::LEVEL_REGION,
                    self::LEVEL_SHOP,
                    self::MIN_GROUP_SIZE,
                    self::MIN_GROUP_SIZE,
                ]
            );
        });
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $shops = Shop::query()
            ->when(
                $command->option('shop'),
                fn ($query, string $slug) => $query->where('slug', $slug),
                fn ($query) => $query->where('type', ShopTypeEnum::B2B)->where('state', ShopStateEnum::OPEN)
            )
            ->orderBy('id')
            ->get();

        foreach ($shops as $shop) {
            $startedAt = microtime(true);
            $this->handle($shop);

            $purchases   = DB::table('customer_product_purchases')->where('shop_id', $shop->id)->count();
            $segmentRows = DB::table('segment_product_sales')->where('shop_id', $shop->id)->count();
            $seconds     = round(microtime(true) - $startedAt, 1);

            $command->info("$shop->slug: $purchases purchases, $segmentRows segment rows, {$seconds}s");
        }

        return 0;
    }
}
