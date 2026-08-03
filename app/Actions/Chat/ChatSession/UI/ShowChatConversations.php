<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession\UI;

use App\Actions\Chat\ChatSession\IndexChatConversations;
use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Http\Resources\CRM\Livechat\ChatSessionResource;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowChatConversations extends OrgAction
{
    use AsAction;
    use WithInertia;

    public function handle(Organisation $organisation): Organisation
    {
        return $organisation;
    }

    public function asController(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function htmlResponse(Organisation $organisation, ActionRequest $request): Response
    {
        $chatSessions = IndexChatConversations::make()->handle($organisation);

        return Inertia::render(
            'Org/Chat/Conversations',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
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
                            'route' => [
                                'name'       => 'grp.org.chat.conversations.export',
                                'parameters' => $request->route()->originalParameters(),
                            ],
                        ],
                    ],
                ],
                'exportRoute' => [
                    'name'       => 'grp.org.chat.conversations.export',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'shops' => $organisation->shops()
                    ->orderBy('name')
                    ->where('state', ShopStateEnum::OPEN)
                    ->get()
                    ->map(fn ($shop) => [
                        'id'   => $shop->id,
                        'name' => $shop->name,
                        'code' => $shop->code,
                    ]),
                'data' => ChatSessionResource::collection($chatSessions),
            ]
        )->table(IndexChatConversations::make()->tableStructure());
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-comments',
                        'route' => [
                            'name'       => 'grp.org.chat.conversations.show',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Conversations'),
                    ],
                ],
            ]
        );
    }
}
