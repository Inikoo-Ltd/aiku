<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\TrafficSource\UI;

use App\Actions\CRM\Customer\UI\IndexCustomers;
use App\Actions\OrgAction;
use App\Actions\Traits\WithCustomersSubNavigation;
use App\Http\Resources\CRM\CustomersResource;
use App\Http\Resources\CRM\TrafficSourceResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSource;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowTrafficSource extends OrgAction
{
    use WithCustomersSubNavigation;

    private Organisation|Shop $parent;

    public function handle(TrafficSource $trafficSource): TrafficSource
    {
        return $trafficSource;
    }

    public function asController(Organisation $organisation, Shop $shop, TrafficSource $trafficSource, ActionRequest $request): TrafficSource
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(TrafficSourceTabsEnum::values());

        return $this->handle($trafficSource);
    }

    public function htmlResponse(TrafficSource $trafficSource, ActionRequest $request): Response
    {
        $navigations = TrafficSourceTabsEnum::navigation();


        return Inertia::render('Org/Shop/CRM/TrafficSource', [
            'title'       => __('TrafficSource details'),
            'breadcrumbs' => $this->getBreadcrumbs(
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            'pageHead'    => [
                'title' => $trafficSource->name,
                'model' => __('TrafficSource'),
                'icon'  => [
                    'icon'  => ['fal', 'fa-TrafficSource'],
                    'title' => __('TrafficSource')
                ],
                // 'actions' => $actions,
            ],
            'tabs'        => [
                'current'    => $this->tab,
                'navigation' => $navigations,
            ],

            TrafficSourceTabsEnum::CUSTOMERS->value => $this->tab == TrafficSourceTabsEnum::CUSTOMERS->value
                ? fn() => CustomersResource::collection(IndexCustomers::run($trafficSource, TrafficSourceTabsEnum::CUSTOMERS->value))
                : Inertia::lazy(fn() => CustomersResource::collection(IndexCustomers::run($trafficSource, TrafficSourceTabsEnum::CUSTOMERS->value))),


        ])->table(IndexCustomers::make()->tableStructure($trafficSource, [], TrafficSourceTabsEnum::CUSTOMERS->value));
    }

    public function jsonResponse(TrafficSource $trafficSource): TrafficSourceResource
    {
        return TrafficSourceResource::make($trafficSource);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (TrafficSource $trafficSource, array $routeParameters, $suffix = null) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => $trafficSource->name,
                    ],
                    'suffix' => $suffix
                ],
            ];
        };

        $trafficSource = TrafficSource::where('slug', $routeParameters['trafficSource'])->first();

        return match ($routeName) {
            'grp.org.shops.show.crm.traffic_sources.show' =>
            array_merge(
                IndexTrafficSources::make()->getBreadcrumbs('grp.org.shops.show.crm.traffic_sources.show', [
                    'organisation' => $trafficSource->organisation->slug,
                    'shop'         => $trafficSource->shop->slug,
                ]),
                $headCrumb(
                    $trafficSource,
                    [
                        'name'       => 'grp.org.shops.show.crm.traffic_sources.show',
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }
}
