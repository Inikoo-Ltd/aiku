<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\UI;

use App\Actions\Catalogue\Shop\UI\GetShopLanguageReach;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowDropshippingCommandCentre extends OrgAction
{
    private const array CACHE_FRESH_AND_STALE_SECONDS = [600, 1800];

    /**
     * Open dropshipping shops of the group the user can see.
     *
     * @return Collection<int, Shop>
     */
    public static function visibleShops(Group $group, User $user): Collection
    {
        return Shop::where('group_id', $group->id)
            ->where('type', ShopTypeEnum::DROPSHIPPING)
            ->where('state', ShopStateEnum::OPEN)
            ->with(['organisation.country', 'currency', 'dropshippingStats'])
            ->orderBy('id')
            ->get()
            ->filter(fn (Shop $shop) => $user->canSeeShop($shop))
            ->values();
    }

    public function authorize(ActionRequest $request): bool
    {
        return self::visibleShops($this->group, $request->user())->isNotEmpty();
    }

    public function asController(ActionRequest $request): Collection
    {
        $this->initialisationFromGroup(app('group'), $request);

        return self::visibleShops($this->group, $request->user());
    }

    /**
     * @param  Collection<int, Shop>  $shops
     */
    public function handle(Group $group, Collection $shops, ?Carbon $today = null): array
    {
        $today   = ($today ?? now('UTC'))->copy()->startOfDay();
        $shopIds = $shops->pluck('id')->all();

        return [
            'shops'    => $shops->map(fn (Shop $shop) => [
                'id'                => $shop->id,
                'code'              => $shop->code,
                'name'              => $shop->name,
                'country_code'      => $shop->organisation->country?->code,
                'currency_code'     => $shop->currency->code,
                'route'             => $this->shopRoute('grp.org.shops.show.dashboard.show', $shop),
            ])->all(),
            'currencies' => array_values(array_unique([$group->currency->code, 'EUR'])),
            'headline'   => [
                ['path' => 'sales.sales_30d', 'label' => __('Sales, last 30 days')],
                ['path' => 'sales.orders_30d', 'label' => __('Orders, last 30 days')],
                ['path' => 'customers.active', 'label' => __('Active customers')],
                ['path' => 'channels.connected', 'label' => __('Open stores')],
                ['path' => 'products.portfolios', 'label' => __('Products in portfolios')],
            ],
            'sections' => [
                $this->customersSection($shops, $shopIds, $today),
                $this->channelsSection($shops, $shopIds),
                $this->productsSection($shops, $shopIds),
                $this->languagesSection($shops),
                $this->currenciesSection($shops, $shopIds),
                $this->salesSection($group, $shops, $shopIds, $today),
            ],
        ];
    }

    /**
     * @param  Collection<int, Shop>  $shops
     * @param  array<int, int>  $shopIds
     */
    private function customersSection(Collection $shops, array $shopIds, Carbon $today): array
    {
        $counts = DB::table('customers')
            ->whereIn('shop_id', $shopIds)
            ->whereNull('deleted_at')
            ->groupBy('shop_id')
            ->selectRaw('shop_id, count(*) as total, count(*) filter (where last_invoiced_at >= ?) as active, count(*) filter (where created_at >= ?) as new', [
                $today->copy()->subDays(90),
                $today->copy()->subDays(29),
            ])
            ->get()
            ->keyBy('shop_id');

        $row = fn (string $key, string $label, ?string $tooltip = null) => $this->countRow(
            $key,
            $label,
            $shops,
            fn (Shop $shop) => (int) ($counts[$shop->id]->{$key} ?? 0),
            fn (Shop $shop) => $this->shopRoute('grp.org.shops.show.crm.customers.index', $shop),
            $tooltip
        );

        return [
            'key'   => 'customers',
            'title' => __('Customers'),
            'icon'  => 'fal fa-users',
            'rows'  => [
                $row('total', __('Customers')),
                $row('active', __('Active'), __('Invoiced in the last 90 days')),
                $row('new', __('New'), __('Registered in the last 30 days')),
            ],
        ];
    }

    /**
     * @param  Collection<int, Shop>  $shops
     * @param  array<int, int>  $shopIds
     */
    private function platformStats(array $shopIds): Collection
    {
        return DB::table('shop_platform_stats')
            ->join('platforms', 'platforms.id', '=', 'shop_platform_stats.platform_id')
            ->whereIn('shop_platform_stats.shop_id', $shopIds)
            ->orderBy('platforms.id')
            ->select([
                'shop_platform_stats.shop_id',
                'platforms.slug',
                'platforms.name',
                'shop_platform_stats.number_products',
            ])
            ->get();
    }

    /**
     * @param  Collection<int, Shop>  $shops
     * @param  array<int, int>  $shopIds
     */
    private function channelsSection(Collection $shops, array $shopIds): array
    {
        $platformStats = DB::table('customer_sales_channels')
            ->join('platforms', 'platforms.id', '=', 'customer_sales_channels.platform_id')
            ->whereIn('customer_sales_channels.shop_id', $shopIds)
            ->where('customer_sales_channels.status', CustomerSalesChannelStatusEnum::OPEN->value)
            ->whereNull('customer_sales_channels.deleted_at')
            ->groupBy('customer_sales_channels.shop_id', 'platforms.id', 'platforms.slug', 'platforms.name', 'platforms.type')
            ->orderBy('platforms.id')
            ->selectRaw('customer_sales_channels.shop_id, platforms.slug, platforms.name, count(*) as number_customer_sales_channels, count(*) filter (where not customer_sales_channels.platform_status and platforms.type != ?) as number_customer_sales_channel_broken', [PlatformTypeEnum::MANUAL->value])
            ->get();

        $channelsRow = function (string $key, string $label, Collection $stats, callable $route) use ($shops): array {
            $byShop = $stats->groupBy('shop_id');
            $row    = $this->countRow($key, $label, $shops, fn (Shop $shop) => (int) $byShop->get($shop->id)?->sum('number_customer_sales_channels'), $route);
            foreach ($shops as $shop) {
                $row['cells'][$shop->id]['broken'] = (int) $byShop->get($shop->id)?->sum('number_customer_sales_channel_broken');
            }
            $row['total']['broken'] = collect($row['cells'])->sum('broken');

            return $row;
        };

        $rows = [$channelsRow('connected', __('Open stores'), $platformStats, fn (Shop $shop) => $this->shopRoute('grp.org.shops.show.crm.platforms.index', $shop))];
        foreach ($platformStats->groupBy('slug') as $slug => $stats) {
            $rows[] = $channelsRow($slug, $stats->first()->name, $stats, fn (Shop $shop) => $this->shopRoute('grp.org.shops.show.crm.platforms.show', $shop, ['platform' => $slug]));
        }

        return [
            'key'   => 'channels',
            'title' => __('Channels'),
            'icon'  => 'fal fa-plug',
            'rows'  => $rows,
        ];
    }

    /**
     * @param  Collection<int, Shop>  $shops
     * @param  array<int, int>  $shopIds
     */
    private function productsSection(Collection $shops, array $shopIds): array
    {
        $rows = [
            $this->countRow(
                'portfolios',
                __('In portfolios'),
                $shops,
                fn (Shop $shop) => (int) ($shop->dropshippingStats?->number_current_portfolios ?? 0),
                fn (Shop $shop) => $this->shopRoute('grp.org.shops.show.crm.platforms.index', $shop),
                __('Products customers keep in their portfolios')
            ),
        ];

        foreach ($this->platformStats($shopIds)->groupBy('slug') as $slug => $stats) {
            if ($stats->sum('number_products') === 0) {
                continue;
            }
            $byShop = $stats->keyBy('shop_id');
            $rows[] = $this->countRow(
                'products_'.$slug,
                $stats->first()->name,
                $shops,
                fn (Shop $shop) => (int) ($byShop[$shop->id]->number_products ?? 0),
                fn (Shop $shop) => $this->shopRoute('grp.org.shops.show.crm.platforms.show', $shop, ['platform' => $slug]),
                __('Different products in :platform stores', ['platform' => $stats->first()->name]),
                false
            );
        }

        return [
            'key'   => 'products',
            'title' => __('Products'),
            'icon'  => 'fal fa-cube',
            'rows'  => $rows,
        ];
    }

    /**
     * One row per language: customers who use it in each shop, whether the shop offers it live or in preview, and how much of the catalogue has its names translated.
     *
     * @param  Collection<int, Shop>  $shops
     */
    private function languagesSection(Collection $shops): array
    {
        $reach = $shops->mapWithKeys(function (Shop $shop) {
            $shopReach = GetShopLanguageReach::run($shop);

            return [
                $shop->id => [
                    'languages' => collect($shopReach['languages'])->keyBy('code'),
                    'channels'  => collect($shopReach['channels']['by_language'])->keyBy('code'),
                ],
            ];
        });

        $names = $reach->flatMap(fn (array $shopReach) => $shopReach['languages']->pluck('language', 'code'));

        $rows = $names->map(function (string $name, string $code) use ($shops, $reach) {
            $cells = $shops->mapWithKeys(function (Shop $shop) use ($reach, $code) {
                $language = $reach[$shop->id]['languages']->get($code);
                $products = (int) ($language['readiness']['products'] ?? 0);

                if (!$language) {
                    return [$shop->id => null];
                }

                return [
                    $shop->id => [
                        'value'     => (int) ($language['customers'] ?? 0),
                        'channels'  => (int) ($reach[$shop->id]['channels']->get($code)['channels'] ?? 0),
                        'state'     => match (true) {
                            !$language['is_offered'] => null,
                            $language['is_preview']  => 'preview',
                            default                  => 'live',
                        },
                        'readiness' => $products > 0 ? (int) floor(100 * $language['readiness']['names'] / $products) : null,
                        'route'     => $language['customers_route'],
                    ],
                ];
            });

            return [
                'key'     => 'language_'.$code,
                'label'   => $name,
                'tooltip' => __('Customers using this language'),
                'kind'    => 'language',
                'cells'   => $cells->all(),
                'total'   => ['value' => $cells->sum('value')],
            ];
        })
            ->sortByDesc(fn (array $row) => $row['total']['value'])
            ->values()
            ->all();

        return [
            'key'   => 'languages',
            'title' => __('Languages'),
            'icon'  => 'fal fa-language',
            'rows'  => $rows,
        ];
    }

    /**
     * @param  Collection<int, Shop>  $shops
     * @param  array<int, int>  $shopIds
     */
    private function currenciesSection(Collection $shops, array $shopIds): array
    {
        $picks = DB::table('web_users')
            ->whereIn('shop_id', $shopIds)
            ->whereNull('deleted_at')
            ->whereNotNull(DB::raw("settings->>'display_currency'"))
            ->groupBy('shop_id', DB::raw("settings->>'display_currency'"))
            ->selectRaw("shop_id, settings->>'display_currency' as code, count(*) as number")
            ->get();

        if ($picks->isEmpty() && $shops->every(fn (Shop $shop) => count($shop->displayCurrencyCodes()) === 1)) {
            return ['key' => 'currencies', 'title' => __('Currencies'), 'icon' => 'fal fa-coins', 'rows' => []];
        }

        $codes = $shops->flatMap(fn (Shop $shop) => $shop->displayCurrencyCodes())
            ->merge($picks->pluck('code'))
            ->unique()
            ->sort()
            ->values();

        $rows = $codes->map(fn (string $code) => $this->countRow(
            'currency_'.$code,
            $code,
            $shops,
            fn (Shop $shop) => (int) $picks->where('shop_id', $shop->id)->where('code', $code)->sum('number'),
            fn (Shop $shop) => $this->shopRoute('grp.org.shops.show.settings.edit', $shop),
            __('Customers who picked :currency', ['currency' => $code])
        ))->all();

        return [
            'key'   => 'currencies',
            'title' => __('Currencies'),
            'icon'  => 'fal fa-coins',
            'rows'  => $rows,
        ];
    }

    /**
     * @param  Collection<int, Shop>  $shops
     * @param  array<int, int>  $shopIds
     */
    private function salesSection(Group $group, Collection $shops, array $shopIds, Carbon $today): array
    {
        $from30 = $today->copy()->subDays(29)->toDateString();
        $totals = DB::table('shop_time_series_records')
            ->join('shop_time_series', 'shop_time_series.id', '=', 'shop_time_series_records.shop_time_series_id')
            ->whereIn('shop_time_series.shop_id', $shopIds)
            ->where('shop_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->where('shop_time_series_records.frequency', TimeSeriesFrequencyEnum::DAILY->singleLetter())
            ->whereBetween('shop_time_series_records.period', [$today->copy()->subDays(364)->toDateString(), $today->toDateString()])
            ->groupBy('shop_time_series.shop_id')
            ->selectRaw('shop_time_series.shop_id,
                coalesce(sum(orders) filter (where period >= ?), 0) as orders_30d,
                coalesce(sum(orders), 0) as orders_12m,
                coalesce(sum(invoices) filter (where period >= ?), 0) as invoices_30d,
                coalesce(sum(invoices), 0) as invoices_12m,
                coalesce(sum(sales_external) filter (where period >= ?), 0) as sales_30d,
                coalesce(sum(sales_external), 0) as sales_12m,
                coalesce(sum(sales_grp_currency_external) filter (where period >= ?), 0) as sales_grp_30d,
                coalesce(sum(sales_grp_currency_external), 0) as sales_grp_12m', [$from30, $from30, $from30, $from30])
            ->get()
            ->keyBy('shop_id');

        $grpToEur = GetCurrencyExchange::make()->cachedOrStored($group->currency, Currency::where('code', 'EUR')->firstOrFail());

        $count = fn (string $key, string $label) => $this->countRow(
            $key,
            $label,
            $shops,
            fn (Shop $shop) => (int) ($totals[$shop->id]->{$key} ?? 0),
            fn (Shop $shop) => $this->shopRoute(str_starts_with($key, 'orders') ? 'grp.org.shops.show.ordering.orders.index' : 'grp.org.shops.show.ordering.invoices.index', $shop)
        );

        $grpCode = $group->currency->code;

        $money = function (string $period, string $label) use ($shops, $totals, $grpToEur, $grpCode): array {
            $cells = $shops->mapWithKeys(function (Shop $shop) use ($totals, $period, $grpToEur, $grpCode) {
                $amount = (float) ($totals[$shop->id]->{'sales_'.$period} ?? 0);
                $grp    = (float) ($totals[$shop->id]->{'sales_grp_'.$period} ?? 0);

                return [
                    $shop->id => [
                        'amounts' => [
                            $grpCode => round($grp, 2),
                            'EUR'    => $shop->currency->code === 'EUR' ? round($amount, 2) : ($grpToEur ? round($grp * $grpToEur, 2) : null),
                        ],
                        'route'   => $this->shopRoute('grp.org.shops.show.ordering.invoices.index', $shop),
                    ],
                ];
            });

            return [
                'key'   => 'sales_'.$period,
                'label' => $label,
                'kind'  => 'money',
                'cells' => $cells->all(),
                'total' => [
                    'amounts' => [
                        $grpCode => round($cells->sum('amounts.'.$grpCode), 2),
                        'EUR'    => $cells->contains(fn (array $cell) => $cell['amounts']['EUR'] === null) ? null : round($cells->sum('amounts.EUR'), 2),
                    ],
                ],
            ];
        };

        return [
            'key'   => 'sales',
            'title' => __('Orders and sales'),
            'icon'  => 'fal fa-chart-line',
            'rows'  => [
                $money('30d', __('Sales, last 30 days')),
                $money('12m', __('Sales, last 12 months')),
                $count('orders_30d', __('Orders, last 30 days')),
                $count('orders_12m', __('Orders, last 12 months')),
                $count('invoices_30d', __('Invoices, last 30 days')),
                $count('invoices_12m', __('Invoices, last 12 months')),
            ],
        ];
    }

    /**
     * @param  Collection<int, Shop>  $shops
     */
    private function countRow(string $key, string $label, Collection $shops, callable $value, callable $route, ?string $tooltip = null, bool $withTotal = true): array
    {
        $cells = $shops->mapWithKeys(fn (Shop $shop) => [
            $shop->id => [
                'value' => $value($shop),
                'route' => $route($shop),
            ],
        ]);

        return [
            'key'     => $key,
            'label'   => $label,
            'tooltip' => $tooltip,
            'kind'    => 'count',
            'cells'   => $cells->all(),
            'total'   => $withTotal ? ['value' => $cells->sum('value')] : null,
        ];
    }

    private function shopRoute(string $name, Shop $shop, array $extra = []): array
    {
        return [
            'name'       => $name,
            'parameters' => [
                'organisation' => $shop->organisation->slug,
                'shop'         => $shop->slug,
                ...$extra,
            ],
        ];
    }

    public function htmlResponse(Collection $shops, ActionRequest $request): Response
    {
        $cacheKey = 'dropshipping-command-centre:v2:'.app()->getLocale().':'.$this->group->id.':'.$shops->pluck('id')->implode('-');

        return Inertia::render(
            'Dropshipping/DropshippingCommandCentre',
            [
                'breadcrumbs' => array_merge(
                    ShowGroupDashboard::make()->getBreadcrumbs(),
                    [['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.dropshipping.dashboard'], 'label' => __('Dropshipping'), 'icon' => 'fal fa-parachute-box']]]
                ),
                'title'       => __('Dropshipping'),
                'pageHead'    => [
                    'title' => __('Dropshipping'),
                    'icon'  => ['fal', 'fa-parachute-box'],
                ],
                'overview'    => Inertia::defer(fn () => Cache::flexible($cacheKey, self::CACHE_FRESH_AND_STALE_SECONDS, fn () => $this->handle($this->group, $shops))),
            ]
        );
    }
}
