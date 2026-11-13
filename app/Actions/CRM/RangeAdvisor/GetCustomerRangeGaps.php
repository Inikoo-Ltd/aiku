<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\RangeAdvisor;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\CRM\Customer;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Gaps in a customer's range: what customers like them buy most that they never bought (missing),
 * and what they bought more than 12 months ago but not since (forgotten). Forgotten reads the 36
 * months customer_product_purchases keeps first, then the customer's older orders for whatever
 * room is left, so a customer back after years still sees what they used to order. It is the
 * customer's own history, so it ignores the segment and the minimum group size.
 */
class GetCustomerRangeGaps
{
    use AsAction;
    use WithRecommendableProducts;

    /**
     * @return array{segment: ?array{level: string, shop_type: ?string, country_code: ?string, area: ?string, region: ?string, customers: int}, missing: Collection, forgotten: Collection}
     */
    public function handle(Customer $customer, int $limit = 24): array
    {
        $shop = $customer->shop;
        $area = DB::table('addresses')
            ->where('addresses.id', $customer->address_id)
            ->selectRaw(RebuildRangeAdvisorSales::AREA_SQL.' as area')
            ->first()?->area;

        $boughtProductIds = DB::table('customer_product_purchases')->where('customer_id', $customer->id)->pluck('product_id')->all();

        $bestSellers = GetSegmentBestSellers::make()->handle(
            $shop,
            $customer->shop_type?->value,
            data_get($customer->location, 0),
            [
                'customer'            => $customer,
                'area'                => $area,
                'limit'               => $limit,
                'exclude_product_ids' => $boughtProductIds,
            ]
        );

        $forgotten = $this->recommendableProducts($shop, $customer)
            ->select('products.*')
            ->join('customer_product_purchases', 'customer_product_purchases.product_id', '=', 'products.id')
            ->where('customer_product_purchases.customer_id', $customer->id)
            ->where('customer_product_purchases.orders_12m', 0)
            ->addSelect([
                'customer_product_purchases.orders as customer_orders',
                'customer_product_purchases.last_ordered_at as customer_last_ordered_at',
            ])
            ->withCasts(['customer_last_ordered_at' => 'datetime'])
            ->orderByDesc('customer_product_purchases.orders')
            ->orderByDesc('customer_product_purchases.last_ordered_at')
            ->orderBy('products.id')
            ->limit($limit)
            ->get();

        if ($forgotten->count() < $limit) {
            $forgotten = $forgotten->concat(
                $this->recommendableProducts($shop, $customer)
                    ->select('products.*')
                    ->joinSub($this->olderPurchases($customer), 'older_purchases', 'older_purchases.product_id', '=', 'products.id')
                    ->whereNotIn('products.id', $boughtProductIds)
                    ->addSelect([
                        'older_purchases.orders as customer_orders',
                        'older_purchases.last_ordered_at as customer_last_ordered_at',
                    ])
                    ->withCasts(['customer_last_ordered_at' => 'datetime'])
                    ->orderByDesc('older_purchases.orders')
                    ->orderByDesc('older_purchases.last_ordered_at')
                    ->orderBy('products.id')
                    ->limit($limit - $forgotten->count())
                    ->get()
            );
        }

        return [
            'segment'   => $bestSellers['segment'],
            'missing'   => $bestSellers['items'],
            'forgotten' => $forgotten,
        ];
    }

    /**
     * What this one customer ordered before the months customer_product_purchases keeps, read from
     * their orders with the same rules as RebuildRangeAdvisorSales.
     */
    private function olderPurchases(Customer $customer): Builder
    {
        return DB::table('orders')
            ->join('transactions', 'transactions.order_id', '=', 'orders.id')
            ->join('products as bought', 'bought.id', '=', 'transactions.model_id')
            ->leftJoin('variants', 'variants.id', '=', 'bought.variant_id')
            ->where('orders.customer_id', $customer->id)
            ->whereNotIn('orders.state', [OrderStateEnum::CREATING->value, OrderStateEnum::CANCELLED->value])
            ->where('orders.date', '<', now()->subMonths(RebuildRangeAdvisorSales::HISTORY_MONTHS))
            ->where('transactions.model_type', 'Product')
            ->whereNull('transactions.deleted_at')
            ->where('transactions.is_gift', false)
            ->groupByRaw('coalesce(variants.leader_id, bought.id)')
            ->selectRaw('coalesce(variants.leader_id, bought.id) as product_id, count(distinct orders.id) as orders, max(orders.date) as last_ordered_at');
    }
}
