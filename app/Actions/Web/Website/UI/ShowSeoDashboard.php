<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 06 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Helpers\Dashboard\DashboardIntervalFilters;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Traits\Dashboards\WithDashboardIntervalOption;
use App\Actions\Traits\Dashboards\WithPerformanceDateResolution;
use App\Actions\Web\SearchConsole\GetWebsiteSearchConsoleStats;
use App\Actions\Web\SearchConsole\UI\IndexSearchConsoleQueries;
use App\Actions\Web\Webpage\UI\IndexWebpagesPerformance;
use App\Actions\Web\WebVital\GetWebsitePageSpeedSummary;
use App\Actions\Web\WebVital\UI\IndexWebpagesPageSpeed;
use App\Actions\Web\Website\GetWebsitePerformanceStats;
use App\Actions\Web\WebsiteConversionEvent\UI\IndexWebsiteConversionCustomers;
use App\Enums\UI\Web\SeoDashboardTabsEnum;
use App\Http\Resources\Web\SearchConsoleQueryResource;
use App\Http\Resources\Web\WebpagePageSpeedResource;
use App\Http\Resources\Web\WebpagePerformanceResource;
use App\Http\Resources\Web\WebsiteConversionCustomerResource;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Closure;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowSeoDashboard extends OrgAction
{
    use WithDashboardIntervalOption;
    use WithPerformanceDateResolution;
    use WithWebAuthorisation;

    public function handle(Shop $shop): Shop
    {
        return $shop;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request)->withTab(SeoDashboardTabsEnum::values());

        return $this->handle($shop);
    }

    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        $title        = __('SEO Dashboard');
        $userSettings = $request->user()->settings;
        $interval     = DateIntervalEnum::tryFrom(Arr::get($userSettings, 'selected_interval', 'all')) ?? DateIntervalEnum::ALL;

        [$fromDate, $toDate] = $this->resolvePerformanceDates($interval, $userSettings);

        $inertiaResponse = Inertia::render(
            'Org/Web/SeoDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-search'],
                        'title' => __('SEO')
                    ],
                    'title' => $title,
                ],
                'intervals'   => [
                    'options'        => $this->dashboardIntervalOption(),
                    'value'          => $interval->value,
                    'range_interval' => DashboardIntervalFilters::run($interval, $userSettings),
                ],
                'settings'    => [],
                'website'     => $shop->website ? [
                    'name'   => $shop->website->name,
                    'domain' => $shop->website->domain,
                    'url'    => $shop->website->getUrl(),
                    'route'  => [
                        'name'       => 'grp.org.shops.show.web.websites.show',
                        'parameters' => [$shop->organisation->slug, $shop->slug, $shop->website->slug],
                    ],
                ] : null,
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => SeoDashboardTabsEnum::navigation(),
                ],
                'performance' => fn () => $shop->website
                    ? GetWebsitePerformanceStats::run($shop->website, $fromDate, $toDate)
                    : null,
                'search'      => fn () => $shop->website
                    ? GetWebsiteSearchConsoleStats::run($shop->website, $fromDate, $toDate)
                    : null,
                'page_speed_summary' => fn () => $shop->website
                    ? GetWebsitePageSpeedSummary::run($shop->website)
                    : null,

                SeoDashboardTabsEnum::WEBPAGES->value => $this->tabProp(
                    SeoDashboardTabsEnum::WEBPAGES,
                    $shop->website,
                    fn (Website $website) => WebpagePerformanceResource::collection(IndexWebpagesPerformance::run($website, $fromDate, $toDate, SeoDashboardTabsEnum::WEBPAGES->value))
                ),

                SeoDashboardTabsEnum::CONVERSIONS->value => $this->tabProp(
                    SeoDashboardTabsEnum::CONVERSIONS,
                    $shop->website,
                    fn (Website $website) => WebsiteConversionCustomerResource::collection(IndexWebsiteConversionCustomers::run($website, $fromDate, $toDate, SeoDashboardTabsEnum::CONVERSIONS->value))
                ),

                SeoDashboardTabsEnum::SEARCH_QUERIES->value => $this->tabProp(
                    SeoDashboardTabsEnum::SEARCH_QUERIES,
                    $shop->website,
                    fn (Website $website) => SearchConsoleQueryResource::collection(IndexSearchConsoleQueries::run($website, $fromDate, $toDate, SeoDashboardTabsEnum::SEARCH_QUERIES->value))
                ),

                SeoDashboardTabsEnum::PAGE_SPEED->value => $this->tabProp(
                    SeoDashboardTabsEnum::PAGE_SPEED,
                    $shop->website,
                    fn (Website $website) => WebpagePageSpeedResource::collection(IndexWebpagesPageSpeed::run($website, SeoDashboardTabsEnum::PAGE_SPEED->value))
                ),

                SeoDashboardTabsEnum::SEARCH_OPPORTUNITIES->value => $this->tabProp(
                    SeoDashboardTabsEnum::SEARCH_OPPORTUNITIES,
                    $shop->website,
                    fn (Website $website) => SearchConsoleQueryResource::collection(IndexSearchConsoleQueries::run($website, $fromDate, $toDate, SeoDashboardTabsEnum::SEARCH_OPPORTUNITIES->value, lowCtrOnly: true))
                ),
            ]
        );

        if ($shop->website) {
            $inertiaResponse
                ->table(IndexWebpagesPerformance::make()->tableStructure(prefix: SeoDashboardTabsEnum::WEBPAGES->value))
                ->table(IndexWebsiteConversionCustomers::make()->tableStructure(prefix: SeoDashboardTabsEnum::CONVERSIONS->value))
                ->table(IndexSearchConsoleQueries::make()->tableStructure(prefix: SeoDashboardTabsEnum::SEARCH_QUERIES->value))
                ->table(IndexSearchConsoleQueries::make()->tableStructure(prefix: SeoDashboardTabsEnum::SEARCH_OPPORTUNITIES->value, lowCtrOnly: true))
                ->table(IndexWebpagesPageSpeed::make()->tableStructure($shop->website, prefix: SeoDashboardTabsEnum::PAGE_SPEED->value));
        }

        return $inertiaResponse;
    }

    private function tabProp(SeoDashboardTabsEnum $tab, ?Website $website, Closure $resolver): mixed
    {
        $prop = fn () => $website ? $resolver($website) : null;

        return $this->tab === $tab->value ? $prop : Inertia::optional($prop);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowShop::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.seo.dashboard',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('SEO Dashboard'),
                    ]
                ]
            ]
        );
    }
}
