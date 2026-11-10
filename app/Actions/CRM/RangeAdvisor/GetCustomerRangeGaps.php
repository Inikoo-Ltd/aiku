<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\RangeAdvisor;

use App\Models\CRM\Customer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Gaps in a customer's range: what customers like them buy most that they never bought (missing),
 * and what they bought 12 to 36 months ago but not since (forgotten). Forgotten cannot look further
 * back than the 36 months customer_product_purchases keeps, and it is the customer's own history,
 * so it ignores the segment and the minimum group size.
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

        $bestSellers = GetSegmentBestSellers::make()->handle(
            $shop,
            $customer->shop_type?->value,
            data_get($customer->location, 0),
            [
                'customer'            => $customer,
                'area'                => $area,
                'limit'               => $limit,
                'exclude_product_ids' => DB::table('customer_product_purchases')->where('customer_id', $customer->id)->pluck('product_id')->all(),
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

        return [
            'segment'   => $bestSellers['segment'],
            'missing'   => $bestSellers['items'],
            'forgotten' => $forgotten,
        ];
    }
}
