<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\TrafficSource\UI;

use App\Actions\Comms\Mailshot\UI\IndexNewsletterMailshots;
use App\Actions\CRM\Customer\UI\IndexCustomers;
use App\Actions\Ordering\Order\UI\IndexOrdersInTrafficSource;
use App\Actions\OrgAction;
use App\Actions\Traits\WithCustomersSubNavigation;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Http\Resources\CRM\CustomersResource;
use App\Http\Resources\CRM\TrafficSourceResource;
use App\Http\Resources\Mail\NewsletterMailshotsResource;
use App\Http\Resources\Ordering\OrdersResource;
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
        $this->initialisationFromShop($shop, $request)->withTab(TrafficSourceTabsEnum::valuesFor($this->channelType($trafficSource)));

        return $this->handle($trafficSource);
    }

    private function channelType(TrafficSource $trafficSource): ?TrafficSourcesTypeEnum
    {
        return TrafficSourcesTypeEnum::tryFrom($trafficSource->type);
    }

    private function isNewsletter(TrafficSource $trafficSource): bool
    {
        return $this->channelType($trafficSource) === TrafficSourcesTypeEnum::NEWSLETTER;
    }

    public function htmlResponse(TrafficSource $trafficSource, ActionRequest $request): Response
    {
        $navigations = TrafficSourceTabsEnum::navigation($this->channelType($trafficSource));


        $props = [
            'title'       => $trafficSource->name,
            'breadcrumbs' => $this->getBreadcrumbs(
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            'pageHead'    => [
                'title' => $trafficSource->name,
                'model' => __('Traffic Source'),
                'icon'  => [
                    'icon'  => ['fal', 'fa-traffic-light'],
                    'title' => __('Traffic Source')
                ],
                // 'actions' => $actions,
            ],
            'tabs'        => [
                'current'    => $this->tab,
                'navigation' => $navigations,
            ],

            TrafficSourceTabsEnum::OVERVIEW->value => $this->tab == TrafficSourceTabsEnum::OVERVIEW->value
                ? fn () => GetTrafficSourceShowcase::run($trafficSource)
                : Inertia::optional(fn () => GetTrafficSourceShowcase::run($trafficSource)),

            TrafficSourceTabsEnum::CUSTOMERS->value => $this->tab == TrafficSourceTabsEnum::CUSTOMERS->value
                ? fn () => CustomersResource::collection(IndexCustomers::run($trafficSource, TrafficSourceTabsEnum::CUSTOMERS->value))
                : Inertia::optional(fn () => CustomersResource::collection(IndexCustomers::run($trafficSource, TrafficSourceTabsEnum::CUSTOMERS->value))),

            TrafficSourceTabsEnum::ORDERS->value => $this->tab == TrafficSourceTabsEnum::ORDERS->value
                ? fn () => OrdersResource::collection(IndexOrdersInTrafficSource::run($trafficSource, TrafficSourceTabsEnum::ORDERS->value))
                : Inertia::optional(fn () => OrdersResource::collection(IndexOrdersInTrafficSource::run($trafficSource, TrafficSourceTabsEnum::ORDERS->value))),


        ];

        if ($this->isNewsletter($trafficSource)) {
            $props[TrafficSourceTabsEnum::NEWSLETTERS->value] = $this->tab == TrafficSourceTabsEnum::NEWSLETTERS->value
                ? fn () => NewsletterMailshotsResource::collection(IndexNewsletterMailshots::run($trafficSource->shop, TrafficSourceTabsEnum::NEWSLETTERS->value))
                : Inertia::optional(fn () => NewsletterMailshotsResource::collection(IndexNewsletterMailshots::run($trafficSource->shop, TrafficSourceTabsEnum::NEWSLETTERS->value)));
        }

        $response = Inertia::render('Org/Shop/CRM/TrafficSource', $props)
            ->table(IndexCustomers::make()->tableStructure($trafficSource, [], TrafficSourceTabsEnum::CUSTOMERS->value))
            ->table(IndexOrdersInTrafficSource::make()->tableStructure($trafficSource, TrafficSourceTabsEnum::ORDERS->value));

        if ($this->isNewsletter($trafficSource)) {
            $response = $response->table(
                IndexNewsletterMailshots::make()->tableStructure($trafficSource->shop, null, TrafficSourceTabsEnum::NEWSLETTERS->value)
            );
        }

        return $response;
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
            'grp.org.shops.show.marketing.traffic_sources.show' =>
            array_merge(
                IndexTrafficSources::make()->getBreadcrumbs([
                    'organisation' => $trafficSource->organisation->slug,
                    'shop'         => $trafficSource->shop->slug,
                ]),
                $headCrumb(
                    $trafficSource,
                    [
                        'name'       => 'grp.org.shops.show.marketing.traffic_sources.show',
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }
}
