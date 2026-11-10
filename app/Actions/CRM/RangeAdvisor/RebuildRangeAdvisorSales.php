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
use App\Models\CRM\Customer;
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
 * Segment levels, from GROUPING(shop_type, country_code, area, region), where a set bit means the
 * column is NOT grouped (shop_type 8, country_code 4, area 2, region 1):
 * type_area 1, type_country 3, type_group 6, type 7, area 9, country 11, group 14, shop 15.
 * An area is only meaningful inside its country, so area levels group by country_code too.
 * region holds the country group of COUNTRY_GROUPS, else the lower-cased continent.
 *
 * ponytail: the area is the postcode area in GB and the first two digits of the postal code
 * elsewhere, which is rough outside GB and wrong for Irish Eircodes; give a country its own rule
 * in AREA_SQL if it ever needs better.
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

    public const string LEVEL_TYPE_AREA = 'type_area';

    public const string LEVEL_TYPE_COUNTRY = 'type_country';

    public const string LEVEL_TYPE_GROUP = 'type_group';

    public const string LEVEL_TYPE = 'type';

    public const string LEVEL_AREA = 'area';

    public const string LEVEL_COUNTRY = 'country';

    public const string LEVEL_GROUP = 'group';

    public const string LEVEL_SHOP = 'shop';

    public const array COUNTRY_GROUPS = [
        'uk_ireland'             => ['GB', 'IE', 'GG', 'JE', 'IM'],
        'nordics'                => ['SE', 'NO', 'DK', 'FI', 'IS'],
        'german_speaking'        => ['DE', 'AT', 'CH', 'LI'],
        'benelux'                => ['NL', 'BE', 'LU'],
        'france'                 => ['FR', 'MC'],
        'southern_europe'        => ['ES', 'PT', 'IT', 'GR', 'MT', 'CY', 'AD', 'SM'],
        'central_eastern_europe' => ['PL', 'CZ', 'SK', 'HU', 'RO', 'BG', 'HR', 'SI', 'EE', 'LV', 'LT'],
    ];

    /**
     * Coarse postal area of an `addresses` row: the leading letters of a GB postcode (NG, EH, B),
     * elsewhere the first two digits of the postal code; null when there is none.
     */
    public const string AREA_SQL = <<<'SQL'
        nullif(
            case
                when addresses.country_code = 'GB' then upper(substring(replace(addresses.postal_code, ' ', '') from '^[A-Za-z]{1,2}'))
                else left(regexp_replace(addresses.postal_code, '[^0-9]', '', 'g'), 2)
            end,
            ''
        )
        SQL;

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

            $areaSql               = self::AREA_SQL;
            $countryGroupBindings  = collect(self::COUNTRY_GROUPS)->flatMap(fn (array $countryCodes, string $group) => collect($countryCodes)->flatMap(fn (string $countryCode) => [$countryCode, $group]))->all();
            $countryGroupRows      = implode(', ', array_fill(0, count($countryGroupBindings) / 2, '(?, ?)'));

            DB::insert(
                <<<SQL
                insert into segment_product_sales
                    (shop_id, level, shop_type, country_code, area, region, product_id, family_id, segment_customers, customers, repeat_customers, revenue)
                with buyers as (
                    select
                        purchases.customer_id,
                        purchases.product_id,
                        purchases.family_id,
                        purchases.orders_12m,
                        purchases.spend_12m,
                        customers.shop_type,
                        customers.location->>0 as country_code,
                        {$areaSql} as area,
                        coalesce(country_groups.group_code, lower(countries.continent)) as region
                    from customer_product_purchases purchases
                    join customers on customers.id = purchases.customer_id
                    left join addresses on addresses.id = customers.address_id
                    left join countries on countries.code = customers.location->>0
                    left join (values {$countryGroupRows}) as country_groups (country_code, group_code) on country_groups.country_code = customers.location->>0
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
                        buyers.area,
                        buyers.region,
                        family_orders.orders_12m,
                        sum(buyers.spend_12m) as spend_12m
                    from buyers
                    join family_orders on family_orders.customer_id = buyers.customer_id and family_orders.family_id = buyers.family_id
                    group by buyers.customer_id, buyers.family_id, buyers.shop_type, buyers.country_code, buyers.area, buyers.region, family_orders.orders_12m
                ),
                segments as (
                    select
                        grouping(shop_type, country_code, area, region) as grouping_id,
                        shop_type,
                        country_code,
                        area,
                        region,
                        count(distinct customer_id) as segment_customers
                    from buyers
                    group by grouping sets ((shop_type, country_code, area), (shop_type, country_code), (shop_type, region), (shop_type), (country_code, area), (country_code), (region), ())
                ),
                segment_rows as (
                    select
                        grouping(shop_type, country_code, area, region) as grouping_id,
                        shop_type,
                        country_code,
                        area,
                        region,
                        product_id,
                        family_id,
                        count(distinct customer_id) as customers,
                        count(distinct customer_id) filter (where orders_12m >= 2) as repeat_customers,
                        sum(spend_12m) as revenue
                    from buyers
                    group by product_id, family_id, grouping sets ((shop_type, country_code, area), (shop_type, country_code), (shop_type, region), (shop_type), (country_code, area), (country_code), (region), ())
                    union all
                    select
                        grouping(shop_type, country_code, area, region),
                        shop_type,
                        country_code,
                        area,
                        region,
                        null::integer,
                        family_id,
                        count(distinct customer_id),
                        count(distinct customer_id) filter (where orders_12m >= 2),
                        sum(spend_12m)
                    from family_buyers
                    group by family_id, grouping sets ((shop_type, country_code, area), (shop_type, country_code), (shop_type, region), (shop_type), (country_code, area), (country_code), (region), ())
                )
                select
                    ?,
                    case segment_rows.grouping_id when 1 then ? when 3 then ? when 6 then ? when 7 then ? when 9 then ? when 11 then ? when 14 then ? when 15 then ? end,
                    segment_rows.shop_type,
                    segment_rows.country_code,
                    segment_rows.area,
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
                    and segments.area is not distinct from segment_rows.area
                    and segments.region is not distinct from segment_rows.region
                where segments.segment_customers >= ?
                    and segment_rows.customers >= ?
                    and (segment_rows.grouping_id & 8 = 8 or segment_rows.shop_type is not null)
                    and (segment_rows.grouping_id & 4 = 4 or segment_rows.country_code is not null)
                    and (segment_rows.grouping_id & 2 = 2 or segment_rows.area is not null)
                    and (segment_rows.grouping_id & 1 = 1 or segment_rows.region is not null)
                SQL,
                [
                    ...$countryGroupBindings,
                    $shop->id,
                    $shop->id,
                    OrderStateEnum::CREATING->value,
                    OrderStateEnum::CANCELLED->value,
                    $windowStart,
                    $shop->id,
                    self::LEVEL_TYPE_AREA,
                    self::LEVEL_TYPE_COUNTRY,
                    self::LEVEL_TYPE_GROUP,
                    self::LEVEL_TYPE,
                    self::LEVEL_AREA,
                    self::LEVEL_COUNTRY,
                    self::LEVEL_GROUP,
                    self::LEVEL_SHOP,
                    self::MIN_GROUP_SIZE,
                    self::MIN_GROUP_SIZE,
                ]
            );
        });
    }

    /**
     * The country group of COUNTRY_GROUPS, else the lower-cased continent; the same map the rebuild uses.
     */
    public static function regionOf(?string $countryCode): ?string
    {
        if (!$countryCode) {
            return null;
        }

        foreach (self::COUNTRY_GROUPS as $group => $countryCodes) {
            if (in_array($countryCode, $countryCodes, true)) {
                return $group;
            }
        }

        $continent = DB::table('countries')->where('code', $countryCode)->value('continent');

        return $continent ? strtolower($continent) : null;
    }

    /**
     * The area of the customer's address, worked out with the same AREA_SQL the rebuild uses.
     */
    public static function areaOf(Customer $customer): ?string
    {
        if (!$customer->address_id) {
            return null;
        }

        return DB::table('addresses')
            ->where('addresses.id', $customer->address_id)
            ->selectRaw(self::AREA_SQL.' as area')
            ->first()?->area;
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
