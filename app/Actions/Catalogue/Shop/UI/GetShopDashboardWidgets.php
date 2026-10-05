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

    public const array WIDGETS = ['department_movers', 'family_movers', 'out_of_stock_month', 'problems_month', 'customer_actions', 'channels', 'top_customers', 'top_products', 'top_families', 'out_of_stock', 'email', 'marketing', 'top_webpages', 'subscriptions'];

    /**
     * @param array<string>|null $only the widgets to compute; a tab asks only for its own, so the
     *                                 marketing overview (the slow one) runs only on the Marketing tab
     */
    public function handle(Shop $shop, DateIntervalEnum $interval, array $userSettings, ?array $only = null): array
    {
        [$fromDate, $toDate] = $this->resolvePerformanceDates($interval, $userSettings);
        $this->from            = $fromDate ? Carbon::createFromFormat('Ymd', $fromDate)->startOfDay() : null;
        $this->to              = $toDate ? Carbon::createFromFormat('Ymd', $toDate)->endOfDay() : null;
        $this->recordFrequency = $this->from ? 'D' : 'Y';

        $widgets = [
            'department_movers' => fn () => $this->categoryMovers($shop, ProductCategoryTypeEnum::DEPARTMENT),
            'family_movers'     => fn () => $this->categoryMovers($shop, ProductCategoryTypeEnum::FAMILY),
            'channels'      => fn () => $this->salesByChannel($shop),
            'top_customers' => fn () => $this->topCustomers($shop, $fromDate, $toDate),
            'top_products'  => fn () => $this->topProducts($shop),
            'top_families'  => fn () => $this->topFamilies($shop),
            'out_of_stock'  => fn () => $this->outOfStockBestSellers($shop),
            'out_of_stock_month' => fn () => $this->outOfStockBestSellers($shop, now('UTC')->startOfMonth()),
            'problems_month'     => fn () => $shop->crmStats?->customers_dashboard['problems_month_to_date'] ?? null,
            'customer_actions'   => fn () => [
                'at_risk' => array_slice($shop->crmStats?->customers_dashboard['at_risk'] ?? [], 0, 5),
                'overdue' => array_slice($shop->crmStats?->customers_dashboard['overdue'] ?? [], 0, 5),
            ],
            'email'         => fn () => Arr::only(GetShopEmailMarketingPerformance::run($shop, $this->from, $this->to, 5), ['totals', 'mailshots']),
            'marketing'     => function () use ($shop) {
                $marketing = GetShopMarketingOverview::run($shop, $this->from, $this->to);

                return [
                    'totals'   => $marketing['totals'],
                    'channels' => collect($marketing['channels'])->sortByDesc('revenue')->take(6)->values()->all(),
                ];
            },
            'top_webpages'  => fn () => $this->topWebpages($shop),
            'subscriptions' => fn () => $this->subscriptions($shop, (int) GetShopEmailMarketingPerformance::run($shop, $this->from, $this->to, 5)['totals']['unsubscribed']),
        ];

        $data = [
            'interval'      => $interval->value,
            'from'          => $this->from?->toDateString(),
            'to'            => $this->to?->toDateString(),
            'currency_code' => $shop->currency->code,
            'routes'        => $this->routes($shop),
        ];

        foreach (Arr::only($widgets, $only ?? self::WIDGETS) as $key => $compute) {
            $cacheKey   = sprintf('dashboard:shop_widget:%s:%s:%s:%s:%s', $shop->id, $key, $interval->value, $fromDate ?? 'null', $toDate ?? 'null');
            $data[$key] = Cache::tags(["dashboard-shop-{$shop->id}"])->remember($cacheKey, now()->addSeconds(300), $compute);
        }

        return $data;
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
     * The departments or families whose sales rose or fell most, month to date against the same
     * days a year earlier. In the first week the month is too young to say anything, so it
     * compares the whole of last month instead. Fixed window: the period picker does not apply.
     *
     * @return array{period: string, from: string, to: string, growing: array, falling: array}
     */
    private function categoryMovers(Shop $shop, ProductCategoryTypeEnum $type): array
    {
        $today = now('UTC')->startOfDay();

        if ($today->day < 7) {
            $from   = $today->copy()->subMonthNoOverflow()->startOfMonth();
            $to     = $from->copy()->endOfMonth();
            $period = 'last_month';
        } else {
            $from   = $today->copy()->startOfMonth();
            $to     = $today;
            $period = 'month_to_date';
        }

        $current  = $this->categorySales($shop, $type, $from, $to);
        $lastYear = $this->categorySales($shop, $type, $from->copy()->subYear(), $to->copy()->subYear());

        $rows = $current->keys()->merge($lastYear->keys())->unique()
            ->map(function ($id) use ($current, $lastYear) {
                $category = $current->get($id) ?? $lastYear->get($id);
                $sales    = (float) ($current->get($id)?->sales ?? 0);
                $before   = (float) ($lastYear->get($id)?->sales ?? 0);

                return [
                    'slug'            => $category->slug,
                    'code'            => $category->code,
                    'name'            => $category->name,
                    'sales'           => round($sales, 2),
                    'sales_last_year' => round($before, 2),
                    'change'          => round($sales - $before, 2),
                ];
            });

        return [
            'period'  => $period,
            'from'    => $from->toDateString(),
            'to'      => $to->toDateString(),
            'growing' => $rows->where('change', '>', 0)->sortByDesc('change')->take(5)->values()->all(),
            'falling' => $rows->where('change', '<', 0)->sortBy('change')->take(5)->values()->all(),
        ];
    }

    private function categorySales(Shop $shop, ProductCategoryTypeEnum $type, Carbon $from, Carbon $to): \Illuminate\Support\Collection
    {
        return DB::table('product_category_time_series_records as r')
            ->join('product_category_time_series as t', 't.id', '=', 'r.product_category_time_series_id')
            ->join('product_categories as c', 'c.id', '=', 't.product_category_id')
            ->where('c.shop_id', $shop->id)
            ->where('c.type', $type->value)
            ->where('t.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->where('r.frequency', 'D')
            ->whereBetween('r.from', [$from->toDateString(), $to->toDateString()])
            ->groupBy('c.id', 'c.slug', 'c.code', 'c.name')
            ->selectRaw('c.id, c.slug, c.code, c.name, sum(r.sales_external) as sales')
            ->get()
            ->keyBy('id');
    }

    /**
     * Every product out of stock right now, with how long it has been out and an estimate of the
     * sales lost meanwhile: its average daily sales over the 90 days before it ran out, times the
     * days it has been out. Ranked by that estimate, with the newest open purchase order line as
     * the replenishment status (there is no supplier lead time in the data to give a real ETA).
     *
     * With $countFrom, only the days out since then count, so the estimate is what it cost this month.
     *
     * @return array{products: int, estimated_lost: float, rows: array}
     */
    private function outOfStockBestSellers(Shop $shop, ?Carbon $countFrom = null): array
    {
        $today = now('UTC')->startOfDay();

        $outOfStock = DB::table('products as p')
            ->where('p.shop_id', $shop->id)
            ->where('p.state', ProductStateEnum::ACTIVE->value)
            ->where('p.status', ProductStatusEnum::OUT_OF_STOCK->value)
            ->whereNull('p.deleted_at')
            ->get(['p.id', 'p.asset_id', 'p.slug', 'p.code', 'p.name', 'p.out_of_stock_since']);

        if ($outOfStock->isEmpty()) {
            return ['products' => 0, 'estimated_lost' => 0, 'rows' => []];
        }

        $salesBefore = DB::table('products as p')
            ->join('asset_time_series as t', 't.asset_id', '=', 'p.asset_id')
            ->join('asset_time_series_records as r', 'r.asset_time_series_id', '=', 't.id')
            ->whereIn('p.id', $outOfStock->pluck('id'))
            ->whereNotNull('p.out_of_stock_since')
            ->where('t.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->where('r.frequency', 'D')
            ->whereRaw("r.from >= (p.out_of_stock_since::date - interval '90 days') and r.from < p.out_of_stock_since::date")
            ->groupBy('p.id')
            ->selectRaw('p.id, sum(r.sales_external) as sales')
            ->pluck('sales', 'p.id');

        $products = $outOfStock->map(function ($product) use ($salesBefore, $today, $countFrom) {
            $since   = $product->out_of_stock_since ? Carbon::parse($product->out_of_stock_since)->startOfDay() : null;
            $daysOut = $since ? (int) $since->diffInDays($today) : null;
            $counted = $since ? (int) ($countFrom && $since->lt($countFrom) ? $countFrom : $since)->diffInDays($today) : 0;

            $product->days_out       = $daysOut;
            $product->estimated_lost = $counted ? round((float) ($salesBefore[$product->id] ?? 0) / 90 * $counted, 2) : 0.0;

            return $product;
        });

        $top = $products->where('estimated_lost', '>', 0)->sortByDesc('estimated_lost')->take(self::LIMIT);

        $openLines = DB::table('product_has_org_stocks as pos')
            ->join('purchase_order_transactions as pot', 'pot.org_stock_id', '=', 'pos.org_stock_id')
            ->join('purchase_orders as po', 'po.id', '=', 'pot.purchase_order_id')
            ->whereIn('pos.product_id', $top->pluck('id'))
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

        return [
            'products'       => $products->count(),
            'estimated_lost' => round($products->sum('estimated_lost'), 2),
            'rows'           => $top->map(function ($row) use ($openLines) {
                $line = $openLines->get($row->id);

                return [
                    'slug'               => $row->slug,
                    'code'               => $row->code,
                    'name'               => $row->name,
                    'out_of_stock_since' => $row->out_of_stock_since ? Carbon::parse($row->out_of_stock_since)->toDateString() : null,
                    'days_out'           => $row->days_out,
                    'estimated_lost'     => $row->estimated_lost,
                    'on_order'           => $line ? [
                        'reference'      => $line->reference,
                        'date'           => Carbon::parse($line->date)->toDateString(),
                        'delivery_state' => $line->delivery_state,
                        'quantity'       => (float) $line->quantity_ordered,
                    ] : null,
                ];
            })->values()->all(),
        ];
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
            'department' => ['name' => 'grp.org.shops.show.catalogue.departments.show', 'parameters' => $parameters],
            'marketing' => ['name' => 'grp.org.shops.show.marketing.dashboard', 'parameters' => $parameters],
            'chat_sessions' => ['name' => 'grp.org.shops.show.crm.chat_sessions.index', 'parameters' => $parameters],
            'mailshots' => ['name' => 'grp.org.shops.show.marketing.mailshots.index', 'parameters' => $parameters],
            'webpage'   => $shop->website ? ['name' => 'grp.org.shops.show.web.webpages.show', 'parameters' => array_merge($parameters, ['website' => $shop->website->slug])] : null,
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): JsonResponse
    {
        $this->initialisationFromShop($shop, $request);

        $userSettings = $request->user()->settings;
        $interval     = DateIntervalEnum::tryFrom((string) $request->query('interval', Arr::get($userSettings, 'selected_interval', 'all'))) ?? DateIntervalEnum::ALL;

        $only = $request->query('only') ? array_intersect(explode(',', (string) $request->query('only')), self::WIDGETS) : null;

        return response()->json($this->handle($shop, $interval, $userSettings, $only));
    }
}
