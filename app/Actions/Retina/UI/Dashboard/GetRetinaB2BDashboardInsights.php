<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Sept 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Dashboard;

use App\Actions\Catalogue\Product\GetProductIncomingStock;
use App\Actions\Retina\Ecom\Basket\GetRetinaProductBasketRecommendations;
use App\Actions\Retina\Traits\HasBasketTransactions;
use App\Actions\Traits\WithCustomerPurchasableProduct;
use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Http\Resources\Catalogue\IrisProductBasketRecommendationResource;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What a wholesale customer sees on their dashboard: how much they buy and how often, the products
 * they keep coming back for, when each of those is due again, which of them are running short in our
 * warehouse or out of stock (with the date the next delivery lands), their latest orders to repeat,
 * and products that sell alongside their range which they have never bought.
 *
 * Money is net of tax in the shop currency, orders are the ones submitted and not cancelled.
 */
class GetRetinaB2BDashboardInsights
{
    use AsObject;
    use HasBasketTransactions;
    use WithCustomerPurchasableProduct;

    private const int REGULAR_PRODUCTS = 20;
    private const int RECENT_ORDERS = 5;

    /**
     * ponytail: our stock is "running short" when it would not cover three of the customer's usual orders.
     */
    private const int LOW_STOCK_ORDERS_COVER = 3;

    /**
     * ponytail: quiet for three months, or for three of their usual gaps between orders, whichever is longer.
     */
    private const int LAPSED_AFTER_DAYS = 90;

    private const int SHOP_BEST_SELLERS_POOL = 60;

    public function handle(Customer $customer): array
    {
        $today       = now()->startOfDay();
        $yearAgo     = $today->copy()->subYear();
        $twoYearsAgo = $today->copy()->subYears(2);

        $orders = DB::table('orders')
            ->where('customer_id', $customer->id)
            ->whereNotIn('state', [OrderStateEnum::CREATING->value, OrderStateEnum::CANCELLED->value])
            ->where('date', '>=', $twoYearsAgo)
            ->orderBy('date')
            ->get(['date', 'net_amount'])
            ->map(fn ($order) => (object) [
                'date'       => Carbon::parse($order->date),
                'net_amount' => (float) $order->net_amount,
            ]);

        $lastYearOrders     = $orders->filter(fn ($order) => $order->date->gte($yearAgo));
        $previousYearOrders = $orders->filter(fn ($order) => $order->date->lt($yearAgo));

        $productSales = $this->getProductSales($customer, $yearAgo);
        if ($productSales->isEmpty()) {
            $productSales = $this->getProductSales($customer);
        }

        [$recommendationsSource, $recommendations] = $this->getRecommendations($customer, $productSales);

        return [
            'currency_code'   => $customer->shop->currency->code,
            'kpis'            => $this->getKpis($customer, $lastYearOrders, $previousYearOrders, $orders, $today),
            'monthly'         => $this->getMonthly($orders, $today),
            'regulars'        => $this->getRegulars($customer, $productSales->take(self::REGULAR_PRODUCTS), $today),
            'recent_orders'   => $this->getRecentOrders($customer),
            'recommendations'        => $recommendations,
            'recommendations_source' => $recommendationsSource,
        ];
    }

    private function getKpis(Customer $customer, Collection $lastYearOrders, Collection $previousYearOrders, Collection $orders, Carbon $today): array
    {
        $spend         = $lastYearOrders->sum('net_amount');
        $previousSpend = $previousYearOrders->sum('net_amount');

        $orderEveryDays = $this->medianGapInDays($orders->pluck('date'));
        $lastOrderAt    = $orders->last()?->date;

        $nextOrderDueAt = ($lastOrderAt && $orderEveryDays)
            ? $lastOrderAt->copy()->addDays($orderEveryDays)
            : null;

        $lastOrderAt ??= $this->getLastOrderDate($customer);
        $daysSinceLast = $lastOrderAt ? (int) $lastOrderAt->copy()->startOfDay()->diffInDays($today) : null;

        return [
            'spend'                => round($spend, 2),
            'previous_spend'       => round($previousSpend, 2),
            'orders'               => $lastYearOrders->count(),
            'previous_orders'      => $previousYearOrders->count(),
            'average_order'        => $lastYearOrders->count() ? round($spend / $lastYearOrders->count(), 2) : null,
            'previous_average'     => $previousYearOrders->count() ? round($previousSpend / $previousYearOrders->count(), 2) : null,
            'order_every_days'     => $orderEveryDays,
            'last_order_at'        => $lastOrderAt?->toDateString(),
            'days_since_last'      => $daysSinceLast,
            'next_order_due_at'    => $nextOrderDueAt?->toDateString(),
            'is_lapsed'            => $daysSinceLast !== null && $daysSinceLast > max(self::LAPSED_AFTER_DAYS, 3 * ($orderEveryDays ?? 0)),
        ];
    }

    /**
     * Twelve months ending this month, each next to the same month a year earlier.
     */
    private function getMonthly(Collection $orders, Carbon $today): array
    {
        $byMonth = $orders->groupBy(fn ($order) => $order->date->format('Y-m'));

        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $month         = $today->copy()->startOfMonth()->subMonths($i);
            $previousMonth = $month->copy()->subYear();

            $current  = $byMonth->get($month->format('Y-m'), collect());
            $previous = $byMonth->get($previousMonth->format('Y-m'), collect());

            $months[] = [
                'month'           => $month->format('Y-m'),
                'spend'           => round($current->sum('net_amount'), 2),
                'orders'          => $current->count(),
                'previous_spend'  => round($previous->sum('net_amount'), 2),
                'previous_orders' => $previous->count(),
            ];
        }

        return $months;
    }

    /**
     * Every product the customer bought since the given date (ever, when none), best spend first.
     */
    private function getProductSales(Customer $customer, ?Carbon $since = null): Collection
    {
        return DB::table('orders')
            ->join('transactions', 'transactions.order_id', 'orders.id')
            ->where('orders.customer_id', $customer->id)
            ->whereNotIn('orders.state', [OrderStateEnum::CREATING->value, OrderStateEnum::CANCELLED->value])
            ->when($since, fn ($query) => $query->where('orders.date', '>=', $since))
            ->where('transactions.model_type', 'Product')
            ->whereNull('transactions.deleted_at')
            ->where('transactions.is_gift', false)
            ->groupBy('transactions.model_id')
            ->select('transactions.model_id as product_id')
            ->selectRaw('count(distinct orders.id) as orders')
            ->selectRaw('sum(transactions.quantity_ordered) as quantity')
            ->selectRaw('sum(transactions.net_amount) as spend')
            ->selectRaw('min(orders.date) as first_ordered_at')
            ->selectRaw('max(orders.date) as last_ordered_at')
            ->orderByDesc('spend')
            ->get();
    }

    private function getRegulars(Customer $customer, Collection $productSales, Carbon $today): array
    {
        if ($productSales->isEmpty()) {
            return [];
        }

        $products = Product::query()
            ->whereIn('products.id', $productSales->pluck('product_id'))
            ->visibleToCustomer($customer->id)
            ->leftJoin('webpages', function ($join) {
                $join->on('products.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'Product');
            })
            ->select('products.*', 'webpages.canonical_url')
            ->get()
            ->keyBy('id');

        $basketTransactions = $this->getBasketTransactions($customer);
        $etas               = GetProductIncomingStock::make()->earliestEtaByProduct(
            $products->filter(fn (Product $product) => $product->available_quantity <= 0)->keys()->all()
        );

        return $productSales
            ->filter(fn ($sale) => $products->has($sale->product_id))
            ->map(function ($sale) use ($products, $customer, $basketTransactions, $etas, $today) {
                /** @var Product $product */
                $product = $products->get($sale->product_id);

                $orders          = (int) $sale->orders;
                $averageQuantity = max(1, (int) ceil($sale->quantity / max(1, $orders)));
                $lastOrderedAt   = Carbon::parse($sale->last_ordered_at)->startOfDay();
                $reorderEvery    = $orders > 1
                    ? (int) round(Carbon::parse($sale->first_ordered_at)->startOfDay()->diffInDays($lastOrderedAt) / ($orders - 1))
                    : null;
                $dueAt           = $reorderEvery ? $lastOrderedAt->copy()->addDays($reorderEvery) : null;

                return [
                    'id'                 => $product->id,
                    'code'               => $product->code,
                    'name'               => $product->name,
                    'image'              => data_get($product->web_images, 'main.thumbnail') ?? data_get($product->web_images, 'main.original'),
                    'url'                => $this->productUrl($product->canonical_url),
                    'price'              => (float) $product->price,
                    'unit'               => $product->unit,
                    'units'              => (float) $product->units,
                    'available_quantity' => (int) $product->available_quantity,
                    'stock_status'       => $this->stockStatus($product, $averageQuantity),
                    'eta'                => $etas[$product->id] ?? null,
                    'is_purchasable'     => $this->isProductPurchasableByCustomer($product, $customer),
                    'orders'             => $orders,
                    'quantity'           => (float) $sale->quantity,
                    'spend'              => round((float) $sale->spend, 2),
                    'average_quantity'   => $averageQuantity,
                    'last_ordered_at'    => $lastOrderedAt->toDateString(),
                    'reorder_every_days' => $reorderEvery,
                    'due_at'             => $dueAt?->toDateString(),
                    'days_until_due'     => $dueAt ? (int) $today->diffInDays($dueAt, false) : null,
                    'quantity_in_basket' => $basketTransactions[$product->id]['quantity_ordered'] ?? 0,
                ];
            })
            ->values()
            ->all();
    }

    private function stockStatus(Product $product, int $averageQuantity): string
    {
        if (!$this->isForSale($product)) {
            return 'unavailable';
        }

        if ($product->is_on_demand) {
            return 'in_stock';
        }

        if ($product->available_quantity <= 0) {
            return 'out_of_stock';
        }

        return $product->available_quantity < $averageQuantity * self::LOW_STOCK_ORDERS_COVER ? 'low' : 'in_stock';
    }

    private function isForSale(Product $product): bool
    {
        return in_array($product->status, [ProductStatusEnum::FOR_SALE, ProductStatusEnum::OUT_OF_STOCK, ProductStatusEnum::COMING_SOON], true);
    }

    private function getRecentOrders(Customer $customer): array
    {
        return DB::table('orders')
            ->leftJoin('order_stats', 'order_stats.order_id', 'orders.id')
            ->where('orders.customer_id', $customer->id)
            ->whereNotIn('orders.state', [OrderStateEnum::CREATING->value, OrderStateEnum::CANCELLED->value])
            ->orderByDesc('orders.date')
            ->limit(self::RECENT_ORDERS)
            ->get([
                'orders.id',
                'orders.slug',
                'orders.reference',
                'orders.date',
                'orders.state',
                'orders.total_amount',
                'order_stats.number_item_transactions',
            ])
            ->map(fn ($order) => [
                'id'          => $order->id,
                'slug'        => $order->slug,
                'reference'   => $order->reference,
                'date'        => $order->date,
                'state'       => $order->state,
                'state_label' => OrderStateEnum::labels()[$order->state] ?? $order->state,
                'total'       => (float) $order->total_amount,
                'items'       => (int) $order->number_item_transactions,
            ])
            ->all();
    }

    /**
     * Products that sell alongside the customer's best sellers and which they have never bought. A customer
     * who has not ordered yet gets the shop's best sellers of the last two months instead.
     *
     * @return array{0: string, 1: array}
     */
    private function getRecommendations(Customer $customer, Collection $productSales): array
    {
        if ($productSales->isEmpty()) {
            return ['shop_best_sellers', IrisProductBasketRecommendationResource::collection($this->getShopBestSellers($customer))->resolve()];
        }

        $products = GetRetinaProductBasketRecommendations::make()->handle(
            $customer->shop,
            $productSales->take(self::REGULAR_PRODUCTS)->pluck('product_id')->all(),
            [
                'prefer_cheaper'      => false,
                'exclude_product_ids' => $productSales->pluck('product_id')->all(),
            ]
        );

        return ['bought_together', IrisProductBasketRecommendationResource::collection($products)->resolve()];
    }

    /**
     * ponytail: the shop-wide count takes ~1.5s on the biggest shop, so the ranked ids are kept for a day.
     */
    private function getShopBestSellers(Customer $customer): Collection
    {
        $shop = $customer->shop;

        $rankedIds = Cache::remember("retina_b2b_shop_best_sellers:$shop->id", now()->addDay(), function () use ($shop) {
            return DB::table('transactions')
                ->where('shop_id', $shop->id)
                ->where('model_type', 'Product')
                ->whereNull('deleted_at')
                ->where('submitted_at', '>=', now()->subDays(60))
                ->groupBy('model_id')
                ->orderByRaw('count(distinct order_id) desc')
                ->limit(self::SHOP_BEST_SELLERS_POOL)
                ->pluck('model_id')
                ->all();
        });

        if (!$rankedIds) {
            return collect();
        }

        $rank = array_flip($rankedIds);

        return Product::query()
            ->whereIn('products.id', $rankedIds)
            ->visibleToCustomer($customer->id)
            ->leftJoin('webpages', 'webpages.id', '=', 'products.webpage_id')
            ->where('products.state', ProductStateEnum::ACTIVE->value)
            ->where('products.has_live_webpage', true)
            ->where('products.available_quantity', '>', 0)
            ->where('products.price', '>', 0)
            ->select('products.*', 'webpages.canonical_url', 'products.offers_data as product_offers_data')
            ->get()
            ->sortBy(fn (Product $product) => $rank[$product->id])
            ->take(GetRetinaProductBasketRecommendations::MAX_PRODUCTS)
            ->values();
    }

    private function getLastOrderDate(Customer $customer): ?Carbon
    {
        $lastOrderDate = DB::table('orders')
            ->where('customer_id', $customer->id)
            ->whereNotIn('state', [OrderStateEnum::CREATING->value, OrderStateEnum::CANCELLED->value])
            ->max('date');

        return $lastOrderDate ? Carbon::parse($lastOrderDate) : null;
    }

    private function medianGapInDays(Collection $dates): ?int
    {
        $days = $dates->map(fn (Carbon $date) => $date->copy()->startOfDay())->unique(fn ($date) => $date->toDateString())->values();

        if ($days->count() < 3) {
            return null;
        }

        $gaps = $days->sliding(2)->map(fn ($pair) => $pair->first()->diffInDays($pair->last()));

        return max(1, (int) round($gaps->median()));
    }

    private function productUrl(?string $canonicalUrl): ?string
    {
        if ($canonicalUrl && !app()->environment('production')) {
            return ShowIrisWebpage::make()->getEnvironmentUrl($canonicalUrl);
        }

        return $canonicalUrl;
    }
}
