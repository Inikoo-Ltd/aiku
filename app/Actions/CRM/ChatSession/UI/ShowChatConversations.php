<?php

namespace App\Actions\CRM\ChatSession\UI;

use App\Actions\CRM\ChatSession\IndexChatConversations;
use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
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
                    'title' => __('Conversations'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-comments'],
                        'title' => __('Conversations'),
                    ],
                ],
                'data' => ChatSessionResource::collection($chatSessions),
            ]
        )->table(IndexChatConversations::make()->tableStructure());
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowChatDashboard::make()->getBreadcrumbs('grp.org.chat.dashboard', $routeParameters),
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
