<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 05 Jan 2025 14:07:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Enums\UI\Discounts\DiscountsDashboardTabsEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowDiscountsDashboard extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("discounts.{$this->shop->id}.view");
    }


    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): ActionRequest
    {
        $this->initialisationFromShop($shop, $request);

        return $request;
    }


    public function htmlResponse(ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Discounts/DiscountsDashboard',
            [
                'breadcrumbs'  => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'        => __('Offers dashboard'),
                'pageHead'     => [
                    'icon'      => [
                        'icon'  => ['fal', 'fa-badge-percent'],
                        'title' => __('Offers dashboard')
                    ],
                    'iconRight' => [
                        'icon'  => ['fal', 'fa-chart-network'],
                        'title' => __('Offer')
                    ],
                    'title' => __('Offers dashboard'),
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => DiscountsDashboardTabsEnum::navigation()
                ],
                'stats'     => [
                    [
                        'name'     => __('Campaigns'),
                        'value'     => $this->shop->discountsStats->number_current_offer_campaigns,
                        'icon'      => 'fal fa-comment-dollar',
                        'route'     => [
                            'name'       => 'grp.org.shops.show.discounts.campaigns.index',
                            'parameters' => $request->route()->originalParameters()
                        ]
                    ],
                    [
                        'name'     => __('Offers'),
                        'value'     => $this->shop->discountsStats->number_offers,
                        'icon'      => 'fal fa-badge-percent',
                        'route'     => [
                            'name'       => 'grp.org.shops.show.discounts.offers.index',
                            'parameters' => $request->route()->originalParameters()
                        ]
                    ],
                ]


            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return
           array_merge(
               ShowShop::make()->getBreadcrumbs($routeParameters),
               [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.discounts.dashboard',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Offers')
                        ]
                    ]
                ]
           );
    }


}
