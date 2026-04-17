<?php

namespace App\Actions\CRM\ChatSession\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Http\Resources\CRM\Livechat\ChatMessageResource;
use App\Http\Resources\CRM\Livechat\ChatSessionResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Livechat\ChatSession;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowChatSession extends OrgAction
{
    use WithCRMAuthorisation;

    public function handle(ChatSession $chatSession): ChatSession
    {
        return $chatSession;
    }

    public function asController(Organisation $organisation, Shop $shop, ChatSession $chatSession, ActionRequest $request): ChatSession
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($chatSession);
    }

    public function htmlResponse(ChatSession $chatSession, ActionRequest $request): Response
    {
        $chatSession->load([
            'webUser.customer',
            'assignments.chatAgent.user',
            'messages' => fn ($q) => $q->orderBy('created_at'),
        ]);

        $contactName = $chatSession->webUser?->customer?->contact_name
            ?? $chatSession->webUser?->username
            ?? $chatSession->guest_identifier
            ?? __('Guest');

        return Inertia::render(
            'Org/Shop/CRM/ChatSession',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Chat with :contact', ['contact' => $contactName]),
                'pageHead'    => [
                    'title' => $contactName,
                    'model' => __('Chat Session'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-comments'],
                        'title' => __('Chat Session')
                    ],
                ],
                'chatSession' => (new ChatSessionResource($chatSession))->resolve(),
                'messages'    => ChatMessageResource::collection($chatSession->messages)->resolve(),
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexChatSessions::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'label' => __('Chat Session'),
                        'icon'  => 'fal fa-comments'
                    ],
                ],
            ]
        );
    }
}
