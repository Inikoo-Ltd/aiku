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
use App\Actions\Web\Webpage\UI\IndexWebpagesPerformance;
use App\Actions\Web\Website\GetWebsitePerformanceStats;
use App\Http\Resources\Web\WebpagePerformanceResource;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowSeoDashboard extends OrgAction
{
    use WithDashboardIntervalOption;
    use WithPerformanceDateResolution;

    public function handle(Shop $shop): Shop
    {
        return $shop;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request);

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
                'performance' => fn () => $shop->website
                    ? GetWebsitePerformanceStats::run($shop->website, $fromDate, $toDate)
                    : null,
                'webpages'    => fn () => $shop->website
                    ? WebpagePerformanceResource::collection(IndexWebpagesPerformance::run($shop->website, $fromDate, $toDate, 'webpages'))
                    : null,
            ]
        );

        if ($shop->website) {
            $inertiaResponse->table(IndexWebpagesPerformance::make()->tableStructure(prefix: 'webpages'));
        }

        return $inertiaResponse;
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
