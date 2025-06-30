<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Jun 2025 18:37:49 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Website\GetWebsiteCloudflareAnalytics;
use App\Actions\Web\Website\WithWebsiteAnalyticsSubNavigation;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowWebsiteAnalyticsDashboard extends OrgAction
{
    use WithWebAuthorisation;
    use WithWebsiteAnalyticsSubNavigation;


    public function handle(Website $website): Website
    {
        return $website;
    }


    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): Website
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($website);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, ActionRequest $request): Website
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($website);
    }

    /**
     * @throws \Throwable
     */
    public function htmlResponse(Website $website, ActionRequest $request): Response
    {
        $analyticReq = $request->only([
            'since',
            'until',
            'showTopNs',
            'partialShowTopNs',
            'partialFilterTimeSeries',
            'partialTimeSeriesData',
            'partialFilterPerfAnalytics',
            'partialWebVitals',
            'partialWebVitalsData',
        ]);

        $title = __('Website Analytics Dashboard');

        return Inertia::render(
            'Org/Web/WebsiteAnalytics',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-satellite-dish'],
                        'title' => __('comms')
                    ],
                    'iconRight'     => [
                        'icon'  => ['fal', 'fa-chart-network'],
                        'title' => __('dashboard')
                    ],
                    'title'         => $title,
                    'subNavigation' => $this->getWebsiteAnalyticsNavigation($website),

                ],
                'data'        => GetWebsiteCloudflareAnalytics::make()->action($website, $analyticReq)


            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {

        /** @var Website $website */
        $website = request()->route()->parameter('website');
        if ($routeName == 'grp.org.shops.show.web.analytics.dashboard') {



            return array_merge(
                ShowWebsite::make()->getBreadcrumbs($website, 'grp.org.shops.show.web.websites.show', $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.web.analytics.dashboard',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Analytics Dashboard'),
                        ]
                    ]
                ]
            );
        } else {
            return array_merge(
                ShowWebsite::make()->getBreadcrumbs($website, 'grp.org.fulfilments.show.web.websites.show', $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.web.analytics.dashboard',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Analytics Dashboard'),
                        ]
                    ]
                ]
            );
        }


    }


}
