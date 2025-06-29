<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Analytics\WebUserRequest\UI;

use App\Actions\CRM\WebUser\WithAuthorizeWebUserScope;
use App\Actions\OrgAction;
use App\Actions\Web\Website\UI\ShowWebsiteAnalyticsDashboard;
use App\Actions\Web\Website\WithWebsiteAnalyticsSubNavigation;
use App\Http\Resources\CRM\WebUserRequestsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Analytics\WebUserRequest;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWebUserRequests extends OrgAction
{
    use WithAuthorizeWebUserScope;
    use WithWebsiteAnalyticsSubNavigation;


    public function handle(Shop|Organisation|Customer|FulfilmentCustomer|Website $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('web_users.username', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(WebUserRequest::class);
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

        $queryBuilder->leftJoin('web_users', 'web_users.id', '=', 'web_user_requests.web_user_id');


        return $queryBuilder
            ->defaultSort('web_users.username')
            ->select([
                'web_users.username',
                'web_users.id',
                'web_user_requests.*'
            ])
            ->allowedSorts(['username', 'ip_address', 'date'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->column(key: 'username', label: __('username'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'ip_address', label: __('ip address'), canBeHidden: false, sortable: true)
                ->column(key: 'url', label: __('url'), canBeHidden: false)
                ->column(key: 'user_agent', label: __('user agent'), canBeHidden: false)
                ->column(key: 'location', label: __('location'), canBeHidden: false)
                ->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true)
                ->defaultSort('-date');
        };
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
