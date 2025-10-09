<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Dec 2024 18:14:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Comms\Traits\WithCommsSubNavigation;
use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\OrgAction;
use App\Enums\UI\Mail\CommsDashboardTabsEnum;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowCommsDashboard extends OrgAction
{
    use WithCommsSubNavigation;


    public function handle(Shop|Fulfilment $parent): Shop|Fulfilment
    {
        return $parent;
    }


    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request)->withTab(CommsDashboardTabsEnum::values());

        return $this->handle($shop);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): Fulfilment
    {
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(CommsDashboardTabsEnum::values());

        return $this->handle($fulfilment);
    }

    public function htmlResponse(Shop|Fulfilment $parent, ActionRequest $request): Response
    {
        return Inertia::render(
            'Comms/CommsDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('mail'),
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-satellite-dish'],
                        'title' => __('Comms')
                    ],
                    'iconRight'     => [
                        'icon'  => ['fal', 'fa-chart-network'],
                        'title' => __('Dashboard')
                    ],
                    'title'         => __('Comms dashboard'),
                    'subNavigation' => $this->getCommsNavigation($parent),
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => CommsDashboardTabsEnum::navigation()
                ],


            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            'grp.org.shops.show.dashboard.comms.dashboard' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.dashboard.comms.dashboard',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Comms')
                        ]
                    ]
                ]
            ),
            'grp.org.fulfilments.show.comms.dashboard', 'grp.org.fulfilments.show.operations.comms.dashboard' =>
            array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.operations.comms.dashboard',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Comms')
                        ]
                    ]
                ]
            ),
            default => []
        };
    }


}
