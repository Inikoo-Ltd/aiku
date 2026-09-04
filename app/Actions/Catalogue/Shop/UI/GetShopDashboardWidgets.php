<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Sep 2026 10:20:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\CRM\Customer\GetTopCustomersStats;
use App\Actions\CRM\TrafficSource\GetShopEmailMarketingPerformance;
use App\Actions\CRM\TrafficSource\GetShopMarketingOverview;
use App\Actions\OrgAction;
use App\Actions\Traits\Dashboards\WithPerformanceDateResolution;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Ordering\SalesChannel\SalesChannelTypeEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionDeliveryStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * The "top lists" of the shop landing dashboard for one interval: sales by channel, top customers,
 * products and families, out-of-stock best sellers with their replenishment status, email and
 * marketing performance, most visited pages and the registrations/unsubscribes balance.
 *
 * Everything is in the shop currency and comes from the pre-aggregated time series where one exists
 * (customers, assets, families, webpages). Channels come straight from invoices because the
 * per-channel time series is not backfilled yet. Ranked lists over "all time" read the yearly
 * records instead of the daily ones so the query stays a few thousand rows.
 */
class GetShopDashboardWidgets extends OrgAction
{
    use WithPerformanceDateResolution;

    private const int LIMIT = 10;

    private ?Carbon $from = null;

    private ?Carbon $to = null;

    private string $recordFrequency = 'D';

    public function handle(Shop $shop, DateIntervalEnum $interval, array $userSettings): array
    {
        [$fromDate, $toDate] = $this->resolvePerformanceDates($interval, $userSettings);
        $this->from            = $fromDate ? Carbon::createFromFormat('Ymd', $fromDate)->startOfDay() : null;
        $this->to              = $toDate ? Carbon::createFromFormat('Ymd', $toDate)->endOfDay() : null;
        $this->recordFrequency = $this->from ? 'D' : 'Y';

        $cacheKey = sprintf('dashboard:shop_widgets:%s:%s:%s:%s', $shop->id, $interval->value, $fromDate ?? 'null', $toDate ?? 'null');

        return Cache::tags(["dashboard-shop-{$shop->id}"])->remember($cacheKey, now()->addSeconds(300), function () use ($shop, $interval, $fromDate, $toDate) {
            $email     = GetShopEmailMarketingPerformance::run($shop, $this->from, $this->to, 5);
            $marketing = GetShopMarketingOverview::run($shop, $this->from, $this->to);

            return [
                'interval'      => $interval->value,
                'from'          => $this->from?->toDateString(),
                'to'            => $this->to?->toDateString(),
                'currency_code' => $shop->currency->code,
                'channels'      => $this->salesByChannel($shop),
                'top_customers' => $this->topCustomers($shop, $fromDate, $toDate),
                'top_products'  => $this->topProducts($shop),
                'top_families'  => $this->topFamilies($shop),
                'out_of_stock'  => $this->outOfStockBestSellers($shop),
                'email'         => [
                    'totals'    => $email['totals'],
                    'mailshots' => $email['mailshots'],
                ],
                'marketing'     => [
                    'totals'   => $marketing['totals'],
                    'channels' => collect($marketing['channels'])->sortByDesc('revenue')->take(6)->values()->all(),
                ],
                'top_webpages'  => $this->topWebpages($shop),
                'subscriptions' => $this->subscriptions($shop, (int) $email['totals']['unsubscribed']),
                'routes'        => $this->routes($shop),
            ];
        });
    }

    private function salesByChannel(Shop $shop): array
    {
        $rows = DB::table('invoices as i')
            ->leftJoin('sales_channels as sc', 'sc.id', '=', 'i.sales_channel_id')
            ->where('i.shop_id', $shop->id)
            ->where('i.type', InvoiceTypeEnum::INVOICE->value)
            ->where('i.in_process', false)
            ->whereNull('i.deleted_at')
            ->when($this->from, fn (Builder $query) => $query->whereBetween('i.date', [$this->from, $this->to]))
            ->groupBy('i.sales_channel_id', 'sc.name', 'sc.type')
            ->selectRaw('i.sales_channel_id, sc.name, sc.type, count(*) as invoices, sum(i.net_amount) as sales')
            ->orderByDesc('sales')
            ->get();

        $total = (float) $rows->sum('sales');

        return $rows->map(fn ($row) => [
            'name'     => match (true) {
                $row->sales_channel_id === null                   => __('Unassigned'),
                $row->type === SalesChannelTypeEnum::WEBSITE->value => __('Web'),
                default                                            => $row->name,
            },
            'type'     => $row->type,
            'invoices' => (int) $row->invoices,
            'sales'    => (float) $row->sales,
            'share'    => $total > 0 ? round((float) $row->sales / $total * 100, 1) : 0,
        ])->all();
    }

    private function topCustomers(Shop $shop, ?string $fromDate, ?string $toDate): array
    {
        return collect(GetTopCustomersStats::run($shop, $fromDate, $toDate, self::LIMIT))
            ->filter(fn ($customer) => $customer['sales'] > 0)
            ->map(fn ($customer) => [
                'slug'     => $customer['slug'],
                'name'     => $customer['name'],
                'sales'    => $customer['sales'],
                'invoices' => $customer['invoices'],
            ])->values()->all();
    }

    private function assetSalesQuery(Shop $shop): Builder
    {
        return DB::table('asset_time_series_records as r')
            ->join('asset_time_series as t', 't.id', '=', 'r.asset_time_series_id')
            ->join('products as p', 'p.asset_id', '=', 't.asset_id')
            ->where('t.shop_id', $shop->id)
            ->where('t.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->where('r.frequency', $this->recordFrequency)
            ->when($this->from, fn (Builder $query) => $query->whereBetween('r.from', [$this->from, $this->to]))
            ->groupBy('p.id', 'p.slug', 'p.code', 'p.name')
            ->selectRaw('p.id, p.slug, p.code, p.name, sum(r.sales_external) as sales, sum(r.sold) as sold, sum(r.invoices) as invoices')
            ->havingRaw('sum(r.sales_external) > 0')
            ->orderByDesc('sales')
            ->limit(self::LIMIT);
    }

    private function topProducts(Shop $shop): array
    {
        return $this->assetSalesQuery($shop)->get()->map(fn ($row) => [
            'slug'     => $row->slug,
            'code'     => $row->code,
            'name'     => $row->name,
            'sales'    => (float) $row->sales,
            'sold'     => (float) $row->sold,
            'invoices' => (int) $row->invoices,
        ])->all();
    }

    private function topFamilies(Shop $shop): array
    {
        return DB::table('product_category_time_series_records as r')
            ->join('product_category_time_series as t', 't.id', '=', 'r.product_category_time_series_id')
            ->join('product_categories as f', 'f.id', '=', 't.product_category_id')
            ->where('f.shop_id', $shop->id)
            ->where('f.type', ProductCategoryTypeEnum::FAMILY->value)
            ->where('t.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->where('r.frequency', $this->recordFrequency)
            ->when($this->from, fn (Builder $query) => $query->whereBetween('r.from', [$this->from->toDateString(), $this->to->toDateString()]))
            ->groupBy('f.id', 'f.slug', 'f.code', 'f.name')
            ->selectRaw('f.slug, f.code, f.name, sum(r.sales_external) as sales, sum(r.invoices) as invoices')
            ->havingRaw('sum(r.sales_external) > 0')
            ->orderByDesc('sales')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn ($row) => [
                'slug'     => $row->slug,
                'code'     => $row->code,
                'name'     => $row->name,
                'sales'    => (float) $row->sales,
                'invoices' => (int) $row->invoices,
            ])->all();
    }

    /**
     * Out-of-stock products that sold most in the period, with the newest open purchase order line
     * for any of their org stocks. There is no supplier lead time in the data, so "ETA" is the honest
     * replenishment status: on order / dispatched with the order date, or not on order at all.
     */
    private function outOfStockBestSellers(Shop $shop): array
    {
        $products = $this->assetSalesQuery($shop)
            ->where('p.state', ProductStateEnum::ACTIVE->value)
            ->where('p.status', ProductStatusEnum::OUT_OF_STOCK->value)
            ->get();

        if ($products->isEmpty()) {
            return [];
        }

        $openLines = DB::table('product_has_org_stocks as pos')
            ->join('purchase_order_transactions as pot', 'pot.org_stock_id', '=', 'pos.org_stock_id')
            ->join('purchase_orders as po', 'po.id', '=', 'pot.purchase_order_id')
            ->whereIn('pos.product_id', $products->pluck('id'))
            ->whereIn('pot.delivery_state', [
                PurchaseOrderTransactionDeliveryStateEnum::IN_PROCESS->value,
                PurchaseOrderTransactionDeliveryStateEnum::CONFIRMED->value,
                PurchaseOrderTransactionDeliveryStateEnum::READY_TO_SHIP->value,
                PurchaseOrderTransactionDeliveryStateEnum::DISPATCHED->value,
            ])
            ->whereNull('pot.deleted_at')
            ->orderByDesc('po.date')
            ->get(['pos.product_id', 'po.reference', 'po.date', 'pot.delivery_state', 'pot.quantity_ordered'])
            ->unique('product_id')
            ->keyBy('product_id');

        return $products->map(function ($row) use ($openLines) {
            $line = $openLines->get($row->id);

            return [
                'slug'     => $row->slug,
                'code'     => $row->code,
                'name'     => $row->name,
                'sales'    => (float) $row->sales,
                'sold'     => (float) $row->sold,
                'on_order' => $line ? [
                    'reference'      => $line->reference,
                    'date'           => Carbon::parse($line->date)->toDateString(),
                    'delivery_state' => $line->delivery_state,
                    'quantity'       => (float) $line->quantity_ordered,
                ] : null,
            ];
        })->values()->all();
    }

    private function topWebpages(Shop $shop): array
    {
        $website = $shop->website;
        if (!$website) {
            return [];
        }

        return DB::table('webpage_time_series_records as r')
            ->join('webpage_time_series as t', 't.id', '=', 'r.webpage_time_series_id')
            ->join('webpages as w', 'w.id', '=', 't.webpage_id')
            ->where('w.website_id', $website->id)
            ->where('t.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->where('r.frequency', $this->recordFrequency)
            ->when($this->from, fn (Builder $query) => $query->whereBetween('r.from', [$this->from, $this->to]))
            ->groupBy('w.id', 'w.slug', 'w.title', 'w.url')
            ->selectRaw('w.slug, w.title, w.url, sum(r.page_views) as page_views, sum(r.visitors) as visitors')
            ->havingRaw('sum(r.page_views) > 0')
            ->orderByDesc('page_views')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn ($row) => [
                'slug'       => $row->slug,
                'title'      => $row->title ?: $row->url,
                'url'        => $row->url,
                'page_views' => (int) $row->page_views,
                'visitors'   => (int) $row->visitors,
            ])->all();
    }

    private function subscriptions(Shop $shop, int $unsubscribed): array
    {
        $registrations = DB::table('customers')
            ->where('shop_id', $shop->id)
            ->whereNull('deleted_at')
            ->when($this->from, fn (Builder $query) => $query->whereBetween('registered_at', [$this->from, $this->to]))
            ->count();

        return [
            'registrations' => $registrations,
            'unsubscribed'  => $unsubscribed,
            'net'           => $registrations - $unsubscribed,
        ];
    }

    private function routes(Shop $shop): array
    {
        $parameters = ['organisation' => $this->organisation->slug, 'shop' => $shop->slug];

        return [
            'customers' => ['name' => 'grp.org.shops.show.crm.customers.index', 'parameters' => $parameters],
            'customer'  => ['name' => 'grp.org.shops.show.crm.customers.show', 'parameters' => $parameters],
            'products'  => ['name' => 'grp.org.shops.show.catalogue.products.current_products.index', 'parameters' => $parameters],
            'product'   => ['name' => 'grp.org.shops.show.catalogue.products.current_products.show', 'parameters' => $parameters],
            'families'  => ['name' => 'grp.org.shops.show.catalogue.families.index', 'parameters' => $parameters],
            'family'    => ['name' => 'grp.org.shops.show.catalogue.families.show', 'parameters' => $parameters],
            'marketing' => ['name' => 'grp.org.shops.show.marketing.dashboard', 'parameters' => $parameters],
            'mailshots' => ['name' => 'grp.org.shops.show.marketing.mailshots.index', 'parameters' => $parameters],
            'webpage'   => $shop->website ? ['name' => 'grp.org.shops.show.web.webpages.show', 'parameters' => array_merge($parameters, ['website' => $shop->website->slug])] : null,
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): JsonResponse
    {
        $this->initialisationFromShop($shop, $request);

        $userSettings = $request->user()->settings;
        $interval     = DateIntervalEnum::tryFrom((string) $request->query('interval', Arr::get($userSettings, 'selected_interval', 'all'))) ?? DateIntervalEnum::ALL;

        return response()->json($this->handle($shop, $interval, $userSettings));
    }
}
