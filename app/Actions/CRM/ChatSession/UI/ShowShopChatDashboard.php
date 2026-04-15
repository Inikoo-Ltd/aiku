<?php

/*
 * Author: stewicca
 * Created: Mon, 14 Apr 2025
 * Copyright (c) 2025, Inikoo Ltd
 */

namespace App\Actions\CRM\ChatSession\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\CRM\Agent\UI\IndexAgent;
use App\Actions\CRM\ChatSession\GetChatDashboardData;
use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowShopChatDashboard extends OrgAction
{
    use AsAction;
    use WithInertia;

    public function handle(Shop $shop): Shop
    {
        return $shop;
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        $title         = __('Chat');
        $dashboardData = GetChatDashboardData::run($shop->organisation);
        $agents        = IndexAgent::make()->handle($shop->organisation, 'agents');
        $routeParams   = $request->route()->originalParameters();

        return Inertia::render(
            'Org/Shop/Chat/Dashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs($routeParams),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-comment-alt'],
                        'title' => $title,
                    ],
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => [
                        'dashboard' => [
                            'name'  => 'dashboard',
                            'icon'  => 'fal fa-tachometer-alt',
                            'label' => __('Dashboard'),
                        ],
                        'agents' => [
                            'name'  => 'agents',
                            'icon'  => 'fal fa-headset',
                            'label' => __('Agents'),
                        ],
                    ],
                ],
                'stats'            => $dashboardData['stats'],
                'chatEnabledShops' => $dashboardData['chatEnabledShops'],
                'table'            => $dashboardData['table'],
                'agents'           => $agents,
            ]
        )->table(
            IndexAgent::make()->tableStructure(prefix: 'agents')
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowShop::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-comment-alt',
                        'route' => [
                            'name'       => 'grp.org.shops.show.crm.chat.dashboard',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Chat'),
                    ],
                ],
            ]
        );
    }
}
