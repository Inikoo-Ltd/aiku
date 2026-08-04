<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-01-2025, Bali, Indonesia
 * GitHub: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\SysAdmin\WebUserRequest\UI;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\WebUserRequest\UI\Traits\WithWebUserRequestsUI;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Website\UI\ShowWebsiteAnalyticsDashboard;
use App\Actions\Web\Website\WithWebsiteAnalyticsSubNavigation;
use App\Http\Resources\CRM\WebUserRequestsResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexWebUserRequests extends OrgAction
{
    use WithWebAuthorisation;
    use WithWebsiteAnalyticsSubNavigation;
    use WithWebUserRequestsUI;


    public function handle(Shop|Organisation|Customer|FulfilmentCustomer|Website $parent, $prefix = null): LengthAwarePaginator
    {
        $queryBuilder = $this->getWebUserRequestsQueryBuilder($prefix);

        if ($parent instanceof Website) {
            $queryBuilder->where('web_user_requests.website_id', $parent->id);
        } elseif ($parent instanceof FulfilmentCustomer) {
            $queryBuilder->where('web_user_requests.website_id', $parent->customer->shop->website->id);
        } elseif ($parent instanceof Customer) {
            $queryBuilder->where('web_user_requests.website_id', $parent->shop->website->id);
        } elseif ($parent instanceof Shop) {
            $queryBuilder->where('web_user_requests.website_id', $parent->website->id);
        } elseif ($parent instanceof Organisation) {
            $queryBuilder->whereExists(function ($query) use ($parent) {
                $query->select('id')
                    ->from('web_users')
                    ->whereColumn('web_users.id', 'web_user_requests.web_user_id')
                    ->whereIn('web_users.id', $parent->webUsers->pluck('id'));
            });
        }

        return $this->finalizeWebUserRequestsQuery($queryBuilder, $prefix);
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($website);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($website);
    }

    public function htmlResponse(LengthAwarePaginator $requests, ActionRequest $request): Response
    {
        /** @var Website $website */
        $website = $request->route()->parameter('website');
        $title   = __('Web User Requests');

        return Inertia::render(
            'Org/Web/WebUserRequests',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => [
                    'title'         => $title,
                    'subNavigation' => $this->getWebsiteAnalyticsNavigation($website),
                ],
                'data'        => WebUserRequestsResource::collection($requests),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {

        if ($routeName == 'grp.org.shops.show.web.analytics.web_user_requests.index') {
            return array_merge(
                ShowWebsiteAnalyticsDashboard::make()->getBreadcrumbs('grp.org.shops.show.web.analytics.dashboard', $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.web.analytics.web_user_requests.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Web user requests'),
                        ]
                    ]
                ]
            );
        } else {
            return array_merge(
                ShowWebsiteAnalyticsDashboard::make()->getBreadcrumbs('grp.org.fulfilments.show.web.analytics.dashboard', $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.web.analytics.dashboard',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Web user requests'),
                        ]
                    ]
                ]
            );
        }
    }


}
