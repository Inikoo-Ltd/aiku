<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\OrgAction;
use App\Actions\Search\GetWebsiteZeroResultOpportunities;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Website\WithWebsiteAnalyticsSubNavigation;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowWebsiteSearchOpportunities extends OrgAction
{
    use WithWebAuthorisation;
    use WithWebsiteAnalyticsSubNavigation;

    public function handle(Website $website): array
    {
        return GetWebsiteZeroResultOpportunities::run($website, 30, 60);
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);
        $this->website = $website;

        return $this->handle($website);
    }

    private Website $website;

    public function htmlResponse(array $opportunities, ActionRequest $request): Response
    {
        $title = __('Search opportunities');

        return Inertia::render(
            'Org/Web/WebsiteSearchOpportunities',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-lightbulb'],
                        'title' => $title
                    ],
                    'title'         => $title,
                    'subNavigation' => $this->getWebsiteAnalyticsNavigation($this->website),
                ],
                'opportunities' => $opportunities,
                'drilldown'     => [
                    'query'  => 'grp.org.shops.show.web.analytics.search.query',
                    'params' => $request->route()->originalParameters(),
                ],
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowWebsiteSearchAnalytics::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.web.analytics.search.opportunities',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Search opportunities'),
                    ]
                ]
            ]
        );
    }
}
