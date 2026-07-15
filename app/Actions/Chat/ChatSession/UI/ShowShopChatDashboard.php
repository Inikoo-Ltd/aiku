<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession\UI;

use App\Actions\Chat\ChatSession\GetShopChatDashboardData;
use App\Actions\Chat\WithChatScopeNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowShopChatDashboard extends OrgAction
{
    use WithCRMAuthorisation;
    use WithChatScopeNavigation;

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
        $dashboardData = GetShopChatDashboardData::run($shop);
        $routeParams   = $request->route()->originalParameters();
        $visitorsRoute = $this->chatRoute('dashboard-visitors');

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
                'dashboardVisitorsRoute' => route($visitorsRoute['name'], $visitorsRoute['parameters']),
                'stats'                  => $dashboardData,
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            $this->chatParentBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-comment-alt',
                        'route' => $this->chatRoute('dashboard'),
                        'label' => __('Chat'),
                    ],
                ],
            ]
        );
    }
}
