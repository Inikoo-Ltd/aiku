<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 02 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\OrgAction;
use App\Actions\Search\GetWebsiteSearchQueryAnalytics;
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

class ShowWebsiteSearchQuery extends OrgAction
{
    use WithWebAuthorisation;
    use WithWebsiteAnalyticsSubNavigation;
    use WithWebsiteSearchLogsTable;

    private Website $website;
    private string $searchQuery;

    public function handle(Website $website, string $searchQuery, $prefix = null): LengthAwarePaginator
    {
        return $this->websiteSearchLogsQuery(
            $website,
            fn ($query) => $query->whereRaw('lower(website_search_logs.query) = ?', [mb_strtolower($searchQuery)]),
            $prefix
        );
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'max:255'],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->website = $website;
        $this->initialisationFromShop($shop, $request);
        $this->searchQuery = $this->validatedData['q'];

        return $this->handle($website, $this->searchQuery);
    }

    public function htmlResponse(LengthAwarePaginator $searchLogs, ActionRequest $request): Response
    {
        $title = __('Search term').': '.$this->searchQuery;

        $constrain = fn ($query) => $query->whereRaw('lower(website_search_logs.query) = ?', [mb_strtolower($this->searchQuery)]);

        return Inertia::render(
            'Org/Web/WebsiteSearchQuery',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-search'],
                        'title' => __('Search term')
                    ],
                    'title'         => $this->searchQuery,
                    'subNavigation' => $this->getWebsiteAnalyticsNavigation($this->website),
                ],
                'insights'  => GetWebsiteSearchQueryAnalytics::run($this->website, $this->searchQuery),
                'drilldown' => [
                    'query'    => 'grp.org.shops.show.web.analytics.search.query',
                    'customer' => 'grp.org.shops.show.web.analytics.search.customer',
                    'params'   => $request->route()->originalParameters(),
                ],
                'data'      => WebsiteSearchLogsResource::collection($searchLogs),
            ]
        )->table($this->websiteSearchLogsTableStructure($this->website, $constrain, ['query']));
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
                            'name'       => 'grp.org.shops.show.web.analytics.search.query',
                            'parameters' => $routeParameters
                        ],
                        'label' => $this->searchQuery,
                    ]
                ]
            ]
        );
    }
}
