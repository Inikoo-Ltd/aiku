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
use App\Actions\Traits\Dashboards\WithDashboardIntervalOption;
use App\Actions\Traits\Dashboards\WithPerformanceDateResolution;
use App\Actions\Web\SearchConsole\GetWebsiteSearchConsoleStats;
use App\Actions\Web\SearchConsole\UI\IndexSearchConsoleQueries;
use App\Actions\Web\Webpage\UI\IndexWebpagesPerformance;
use App\Actions\Web\WebVital\GetWebsitePageSpeedSummary;
use App\Actions\Web\WebVital\UI\IndexWebpagesPageSpeed;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Crawl\AuditWebsite;
use App\Actions\Web\Seo\GetSeoApiUsage;
use App\Actions\Web\Website\PruneWebsitePageViews;
use App\Actions\Web\Website\PruneWebsiteVisitors;
use App\Actions\Web\WebsiteNotFoundPath\PruneWebsiteNotFoundPaths;
use App\Actions\Web\WebsiteNotFoundPath\UI\IndexWebsiteNotFoundPaths;
use App\Actions\Web\WebsitePageView\UI\IndexWebsitePageViews;
use App\Actions\Web\WebsiteVisitor\UI\IndexWebsiteVisitors;
use App\Http\Resources\Web\WebsiteNotFoundPathResource;
use App\Http\Resources\Web\WebsitePageViewResource;
use App\Http\Resources\Web\WebsiteVisitorResource;
use Illuminate\Support\Carbon;
use App\Actions\Web\Website\GetWebsitePerformanceStats;
use App\Actions\Web\WebsiteConversionEvent\UI\IndexWebsiteConversionCustomers;
use App\Enums\UI\Web\SeoDashboardPageTabsEnum;
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

    private SeoDashboardTabsEnum $tableTab = SeoDashboardTabsEnum::WEBPAGES;

    public function handle(Shop $shop): Shop
    {
        return $shop;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request)->withTab(SeoDashboardPageTabsEnum::values());

        $this->tableTab = SeoDashboardTabsEnum::tryFrom((string) $request->query('table')) ?? SeoDashboardTabsEnum::WEBPAGES;

        return $this->handle($shop);
    }

    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        $title        = __('SEO Dashboard');
        $userSettings = $request->user()->settings;
        $interval     = DateIntervalEnum::tryFrom(Arr::get($userSettings, 'selected_interval', 'all')) ?? DateIntervalEnum::ALL;

        [$fromDate, $toDate] = $this->resolvePerformanceDates($interval, $userSettings);

        $visitorsIndex      = IndexWebsiteVisitors::make();
        $notFoundPathsIndex = IndexWebsiteNotFoundPaths::make();

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
                'pageTabs'    => [
                    'current'    => $this->tab,
                    'navigation' => SeoDashboardPageTabsEnum::navigation(),
                ],
                'tabs'        => [
                    'current'    => $this->tableTab->value,
                    'navigation' => SeoDashboardTabsEnum::navigation(),
                ],
                'performance' => $this->overviewProp(fn () => $shop->website
                    ? GetWebsitePerformanceStats::run($shop->website, $fromDate, $toDate)
                    : null),
                'search'      => $this->overviewProp(fn () => $shop->website
                    ? GetWebsiteSearchConsoleStats::run($shop->website, $fromDate, $toDate)
                    : null),
                'page_speed_summary' => $this->overviewProp(fn () => $shop->website
                    ? GetWebsitePageSpeedSummary::run($shop->website)
                    : null),

                SeoDashboardTabsEnum::WEBPAGES->value => $this->tabProp(
                    SeoDashboardTabsEnum::WEBPAGES,
                    $shop->website,
                    fn (Website $website) => WebpagePerformanceResource::collection(IndexWebpagesPerformance::run($website, $fromDate, $toDate, SeoDashboardTabsEnum::WEBPAGES->value))
                        ->additional([
                            'periods'         => IndexWebpagesPerformance::periods($website, $fromDate, $toDate),
                            'min_for_percent' => IndexWebpagesPerformance::MIN_FOR_PERCENT,
                        ])
                ),

                SeoDashboardPageTabsEnum::VISITORS->value => $this->tabProp(
                    SeoDashboardPageTabsEnum::VISITORS,
                    $shop->website,
                    fn (Website $website) => [
                        'table'          => WebsiteVisitorResource::collection($visitorsIndex->handle($website, SeoDashboardPageTabsEnum::VISITORS->value)),
                        'retention_days' => PruneWebsiteVisitors::RETENTION_DAYS,
                    ]
                ),

                SeoDashboardPageTabsEnum::PAGE_VIEWS->value => $this->tabProp(
                    SeoDashboardPageTabsEnum::PAGE_VIEWS,
                    $shop->website,
                    fn (Website $website) => [
                        'table'          => WebsitePageViewResource::collection(IndexWebsitePageViews::make()->handle($website, prefix: SeoDashboardPageTabsEnum::PAGE_VIEWS->value)),
                        'retention_days' => PruneWebsitePageViews::RETENTION_DAYS,
                    ]
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

                SeoDashboardPageTabsEnum::MISSING_PAGES->value => $this->tabProp(
                    SeoDashboardPageTabsEnum::MISSING_PAGES,
                    $shop->website,
                    fn (Website $website) => [
                        'table'          => WebsiteNotFoundPathResource::collection($notFoundPathsIndex->handle($website, SeoDashboardPageTabsEnum::MISSING_PAGES->value)),
                        'website'        => [
                            'id'     => $website->id,
                            'domain' => $website->domain,
                            'url'    => AuditWebsite::publicBaseUrl($website),
                        ],
                        'retention_days' => PruneWebsiteNotFoundPaths::RETENTION_DAYS,
                        'can_edit'       => $this->canEdit,
                    ]
                ),

                SeoDashboardPageTabsEnum::API_USAGE->value => $this->tabProp(
                    SeoDashboardPageTabsEnum::API_USAGE,
                    $shop->website,
                    fn () => [
                        'usage'        => GetSeoApiUsage::run($this->usageMonth($request)),
                        'can_edit'     => $request->user()->authTo(['group-webmaster.edit', 'sysadmin.edit']),
                        'budget_route' => [
                            'name'       => 'grp.models.group.seo_api_budget.update',
                            'parameters' => [],
                        ],
                    ]
                ),
            ]
        );

        return $shop->website ? $inertiaResponse->table($this->tableStructure($shop->website, $visitorsIndex, $notFoundPathsIndex)) : $inertiaResponse;
    }

    /**
     * Only the table of the open tab is built: the Visitors and Missing pages tables count their
     * filters when they are built, which every other tab would pay for. A change of the table tab
     * asks for `queryBuilderProps` along with the tab.
     */
    private function tableStructure(Website $website, IndexWebsiteVisitors $visitorsIndex, IndexWebsiteNotFoundPaths $notFoundPathsIndex): ?Closure
    {
        return match (SeoDashboardPageTabsEnum::tryFrom((string) $this->tab)) {
            SeoDashboardPageTabsEnum::VISITORS      => $visitorsIndex->tableStructure($website, prefix: SeoDashboardPageTabsEnum::VISITORS->value),
            SeoDashboardPageTabsEnum::PAGE_VIEWS    => IndexWebsitePageViews::make()->tableStructure(prefix: SeoDashboardPageTabsEnum::PAGE_VIEWS->value),
            SeoDashboardPageTabsEnum::MISSING_PAGES => $notFoundPathsIndex->tableStructure($website, SeoDashboardPageTabsEnum::MISSING_PAGES->value),
            SeoDashboardPageTabsEnum::OVERVIEW      => match ($this->tableTab) {
                SeoDashboardTabsEnum::WEBPAGES             => IndexWebpagesPerformance::make()->tableStructure(prefix: SeoDashboardTabsEnum::WEBPAGES->value),
                SeoDashboardTabsEnum::CONVERSIONS          => IndexWebsiteConversionCustomers::make()->tableStructure(prefix: SeoDashboardTabsEnum::CONVERSIONS->value),
                SeoDashboardTabsEnum::SEARCH_QUERIES       => IndexSearchConsoleQueries::make()->tableStructure(prefix: SeoDashboardTabsEnum::SEARCH_QUERIES->value),
                SeoDashboardTabsEnum::SEARCH_OPPORTUNITIES => IndexSearchConsoleQueries::make()->tableStructure(prefix: SeoDashboardTabsEnum::SEARCH_OPPORTUNITIES->value, lowCtrOnly: true),
                SeoDashboardTabsEnum::PAGE_SPEED           => IndexWebpagesPageSpeed::make()->tableStructure($website, prefix: SeoDashboardTabsEnum::PAGE_SPEED->value),
            },
            default                                 => null,
        };
    }

    private function usageMonth(ActionRequest $request): Carbon
    {
        $month = (string) $request->query('month');

        return preg_match('/^\d{4}-\d{2}$/', $month) ? Carbon::createFromFormat('Y-m-d', $month.'-01') : now();
    }

    private function tabProp(SeoDashboardTabsEnum|SeoDashboardPageTabsEnum $tab, ?Website $website, Closure $resolver): mixed
    {
        $prop = fn () => $website ? $resolver($website) : null;

        $isOpen = $tab instanceof SeoDashboardPageTabsEnum
            ? $this->tab === $tab->value
            : $this->tab === SeoDashboardPageTabsEnum::OVERVIEW->value && $this->tableTab === $tab;

        return $isOpen ? $prop : Inertia::optional($prop);
    }

    private function overviewProp(Closure $resolver): mixed
    {
        return $this->tab === SeoDashboardPageTabsEnum::OVERVIEW->value ? $resolver : Inertia::optional($resolver);
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
