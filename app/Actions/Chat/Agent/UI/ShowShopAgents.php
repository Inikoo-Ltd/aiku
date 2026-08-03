<?php

/*
 * Author: Andi Ferdiawan
 * Created: Wed, 15 Jul 2026 10:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\Agent\UI;

use App\Actions\Chat\WithChatScopeNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Http\Resources\CRM\Livechat\ChatAgentResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowShopAgents extends OrgAction
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
        $indexAgentAction = IndexAgent::make();
        $agents           = $indexAgentAction->handle($shop, 'agents');

        return Inertia::render(
            'Agent/Agents',
            [
                'breadcrumbs'      => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'            => __('CRM Agent'),
                'organisationSlug' => $shop->organisation->slug,
                'pageHeading'      => [
                    'title'   => __('CRM Agent'),
                    'icon'    => [
                        'title' => __('CRM Agent'),
                        'icon'  => ['fal', 'fa-headset'],
                    ],
                    'actions' => [
                        [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('Create CRM Agent'),
                            'label'   => __('Create CRM Agent'),
                            'route'   => [
                                'name'       => 'grp.org.chat.agents.create',
                                'parameters' => [$shop->organisation->slug],
                            ],
                        ],
                    ],
                ],
                'data'   => ChatAgentResource::collection($agents),
                'routes' => [
                    'delete'       => 'grp.org.chat.agents.delete',
                    'restore'      => 'grp.org.chat.agents.restore',
                    'force_delete' => 'grp.org.chat.agents.force_delete',
                ],
            ]
        )->table(
            $indexAgentAction->tableStructure(parent: $shop, prefix: 'agents')
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
                        'icon'  => 'fal fa-headset',
                        'route' => $this->chatRoute('agents.show'),
                        'label' => __('CRM Agent'),
                    ],
                ],
            ]
        );
    }
}
