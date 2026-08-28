<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 02 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\OrgAction;
use App\Actions\Search\GetWebsiteSearchCustomerAnalytics;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Website\WithWebsiteAnalyticsSubNavigation;
use App\Http\Resources\Web\WebsiteSearchLogsResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowWebsiteSearchCustomer extends OrgAction
{
    use WithWebAuthorisation;
    use WithWebsiteAnalyticsSubNavigation;
    use WithWebsiteSearchLogsTable;

    private Website $website;
    private Customer $customer;

    public function handle(Website $website, Customer $customer, $prefix = null): LengthAwarePaginator
    {
        return $this->websiteSearchLogsQuery(
            $website,
            fn ($query) => $query->where('website_search_logs.customer_id', $customer->id),
            $prefix
        );
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, Customer $customer, ActionRequest $request): LengthAwarePaginator
    {
        $this->website  = $website;
        $this->customer = $customer;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($website, $customer);
    }

    public function htmlResponse(LengthAwarePaginator $searchLogs, ActionRequest $request): Response
    {
        $constrain = fn ($query) => $query->where('website_search_logs.customer_id', $this->customer->id);

        return Inertia::render(
            'Org/Web/WebsiteSearchCustomer',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $this->customer->name,
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-user'],
                        'title' => __('Customer searches')
                    ],
                    'title'         => $this->customer->name,
                    'subNavigation' => $this->getWebsiteAnalyticsNavigation($this->website),
                ],
                'insights'  => GetWebsiteSearchCustomerAnalytics::run($this->website, $this->customer),
                'drilldown' => [
                    'query'    => 'grp.org.shops.show.web.analytics.search.query',
                    'customer' => 'grp.org.shops.show.web.analytics.search.customer',
                    'params'   => array_diff_key($request->route()->originalParameters(), ['customer' => true]),
                ],
                'data'      => WebsiteSearchLogsResource::collection($searchLogs),
            ]
        )->table($this->websiteSearchLogsTableStructure($this->website, $constrain, ['customer_name']));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowWebsiteSearchAnalytics::make()->getBreadcrumbs(array_diff_key($routeParameters, ['customer' => true])),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.web.analytics.search.customer',
                            'parameters' => $routeParameters
                        ],
                        'label' => $this->customer->name,
                    ]
                ]
            ]
        );
    }
}
