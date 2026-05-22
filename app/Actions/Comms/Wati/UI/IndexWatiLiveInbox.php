<?php

/*
 * Author: andiferdiawan (https://github.com/andiferdiawan)
 * Created: Thursday, 22 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, andiferdiawan
 */

namespace App\Actions\Comms\Wati\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\WithWatiSubNavigation;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexWatiLiveInbox extends OrgAction
{
    use WithWatiSubNavigation;

    public Shop $parent;

    public function htmlResponse(ActionRequest $request): Response
    {
        return Inertia::render(
            'Comms/WatiLiveInbox',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Live Inbox'),
                'pageHead'    => [
                    'title'         => __('Live Inbox'),
                    'icon'          => ['fal', 'fa-inbox-in'],
                    'subNavigation' => $this->getWatiSubNavigation($this->parent, $request),
                ],
            ]
        );
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): ActionRequest
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $request;
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return [
            [
                'type'   => 'simple',
                'simple' => [
                    'route' => [
                        'name'       => 'grp.org.shops.show.marketing.wati.live_inbox.index',
                        'parameters' => $routeParameters,
                    ],
                    'label' => __('Live Inbox'),
                    'icon'  => 'fal fa-bars',
                ],
            ],
        ];
    }
}
