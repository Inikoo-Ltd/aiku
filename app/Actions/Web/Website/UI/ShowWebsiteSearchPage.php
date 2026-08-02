<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\OrgAction;
use App\Actions\Search\GetWebsiteSearchPageAnalytics;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Website\WithWebsiteAnalyticsSubNavigation;
use App\Http\Resources\Web\WebsiteSearchLogsResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowWebsiteSearchPage extends OrgAction
{
    use WithWebAuthorisation;
    use WithWebsiteAnalyticsSubNavigation;
    use WithWebsiteSearchLogsTable;

    private Website $website;
    private string $clickedUrl;

    public function handle(Website $website, string $clickedUrl, $prefix = null): LengthAwarePaginator
    {
        return $this->websiteSearchLogsQuery(
            $website,
            fn ($query) => $query->where('website_search_logs.clicked_url', $clickedUrl),
            $prefix
        );
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048'],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->website = $website;
        $this->initialisationFromShop($shop, $request);
        $this->clickedUrl = $this->validatedData['url'];

        return $this->handle($website, $this->clickedUrl);
    }

    public function htmlResponse(LengthAwarePaginator $searchLogs, ActionRequest $request): Response
    {
        $pageLabel = basename(parse_url($this->clickedUrl, PHP_URL_PATH) ?: '') ?: $this->clickedUrl;

        $constrain = fn ($query) => $query->where('website_search_logs.clicked_url', $this->clickedUrl);

        return Inertia::render(
            'Org/Web/WebsiteSearchPage',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters(), $pageLabel),
                'title'       => __('Searches landing on').': '.$pageLabel,
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-file'],
                        'title' => __('Webpage')
                    ],
                    'title'         => $pageLabel,
                    'subNavigation' => $this->getWebsiteAnalyticsNavigation($this->website),
                ],
                'insights'  => GetWebsiteSearchPageAnalytics::run($this->website, $this->clickedUrl),
                'drilldown' => [
                    'query'    => 'grp.org.shops.show.web.analytics.search.query',
                    'customer' => 'grp.org.shops.show.web.analytics.search.customer',
                    'params'   => $request->route()->originalParameters(),
                ],
                'data'      => WebsiteSearchLogsResource::collection($searchLogs),
            ]
        )->table($this->websiteSearchLogsTableStructure($this->website, $constrain, ['clicked_at']));
    }

    public function getBreadcrumbs(array $routeParameters, string $pageLabel): array
    {
        return array_merge(
            ShowWebsiteSearchAnalytics::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.web.analytics.search.page',
                            'parameters' => $routeParameters
                        ],
                        'label' => $pageLabel,
                    ]
                ]
            ]
        );
    }
}
