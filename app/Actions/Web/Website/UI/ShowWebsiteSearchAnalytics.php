<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\OrgAction;
use App\Actions\Search\GetWebsiteSearchAnalytics;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Website\WithWebsiteAnalyticsSubNavigation;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowWebsiteSearchAnalytics extends OrgAction
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

    public function htmlResponse(Website $website, ActionRequest $request): Response
    {
        $title = __('Website Search');

        return Inertia::render(
            'Org/Web/WebsiteSearchAnalytics',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-satellite-dish'],
                        'title' => __('Comms')
                    ],
                    'iconRight'     => [
                        'icon'  => ['fal', 'fa-search'],
                        'title' => $title
                    ],
                    'title'         => $title,
                    'subNavigation' => $this->getWebsiteAnalyticsNavigation($website),
                ],
                'search_insights' => GetWebsiteSearchAnalytics::run($website),
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        /** @var Website $website */
        $website = request()->route()->parameter('website');

        return array_merge(
            ShowWebsite::make()->getBreadcrumbs($website, 'grp.org.shops.show.web.websites.show', $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.web.analytics.search',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Website Search'),
                    ]
                ]
            ]
        );
    }
}
