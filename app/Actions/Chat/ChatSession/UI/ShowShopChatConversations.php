<?php

/*
 * Author: Andi Ferdiawan
 * Created: Wed, 15 Jul 2026 10:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\ChatSession\UI;

use App\Actions\Chat\ChatSession\IndexChatConversations;
use App\Actions\Chat\WithChatScopeNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Http\Resources\CRM\Livechat\ChatSessionResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowShopChatConversations extends OrgAction
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
        $chatSessions = IndexChatConversations::make()->handle($shop);
        $routeParams  = $request->route()->originalParameters();

        $exportRoute = $this->chatRoute('conversations.export');

        return Inertia::render(
            'Org/Chat/Conversations',
            [
                'breadcrumbs' => $this->getBreadcrumbs($routeParams),
                'title'       => __('Conversations'),
                'pageHead'    => [
                    'title'   => __('Conversations'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-comments'],
                        'title' => __('Conversations'),
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'secondary',
                            'label' => __('Export Data'),
                            'icon'  => ['fal', 'fa-file-download'],
                            'route' => $exportRoute,
                        ],
                    ],
                ],
                'exportRoute' => $exportRoute,
                'shops'       => [],
                'data'        => ChatSessionResource::collection($chatSessions),
            ]
        )->table(IndexChatConversations::make()->tableStructure(withShopColumn: false));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return $this->chatConversationsBreadcrumbs($routeParameters);
    }
}
