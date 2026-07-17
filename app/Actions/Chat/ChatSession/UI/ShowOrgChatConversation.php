<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession\UI;

use App\Actions\Chat\WithChatScopeNavigation;
use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Http\Resources\CRM\Livechat\ChatMessageResource;
use App\Http\Resources\CRM\Livechat\ChatSessionResource;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowOrgChatConversation extends OrgAction
{
    use AsAction;
    use WithInertia;
    use WithChatScopeNavigation;

    public function handle(ChatSession $chatSession): ChatSession
    {
        return $chatSession;
    }

    public function asController(Organisation $organisation, ChatSession $chatSession, ActionRequest $request): ChatSession
    {
        $this->initialisation($organisation, $request);

        return $this->handle($chatSession);
    }

    public function inShop(Organisation $organisation, Shop $shop, ChatSession $chatSession, ActionRequest $request): ChatSession
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($chatSession);
    }

    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, ChatSession $chatSession, ActionRequest $request): ChatSession
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($chatSession);
    }

    public function htmlResponse(ChatSession $chatSession, ActionRequest $request): Response
    {
        $chatSession->load([
            'webUser.customer',
            'assignments.chatAgent.user',
            'shop',
            'messages' => fn ($q) => $q->orderBy('created_at'),
        ]);

        $contactName = $chatSession->webUser?->customer?->contact_name
            ?? $chatSession->webUser?->username
            ?? $chatSession->guest_identifier
            ?? __('Guest');

        $customer = $chatSession->webUser?->customer;

        return Inertia::render(
            'Org/Chat/ConversationDetail',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Chat: :contact', ['contact' => $contactName]),
                'pageHead'    => [
                    'title' => $contactName,
                    'model' => __('Conversation'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-comments'],
                        'title' => __('Conversation'),
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'secondary',
                            'label' => __('Back to List Conversations'),
                            'icon'  => ['fal', 'fa-long-arrow-left'],
                            'route' => $this->chatRoute('conversations.show'),
                        ],
                    ],
                ],
                'chatSession'      => (new ChatSessionResource($chatSession))->resolve(),
                'customerNote'     => $customer ? [
                    'internal_notes' => $customer->internal_notes,
                    'update_route'   => [
                        'name'       => 'grp.models.customer.update',
                        'parameters' => ['customer' => $customer->id],
                    ],
                ] : null,
                'messages'         => $this->resolveMessagesWithSenderNames($chatSession),
                'slackConfigured'  => !empty(Arr::get($chatSession->shop?->settings ?? [], 'chat.slack_token'))
                    && !empty(Arr::get($chatSession->shop?->settings ?? [], 'chat.slack_channels')),
                'slackCurrentConfig' => [
                    'token'    => Arr::get($chatSession->shop?->settings ?? [], 'chat.slack_token') ?? '',
                    'channels' => Arr::get($chatSession->shop?->settings ?? [], 'chat.slack_channels') ?? [],
                ],
                'slackUpdateRoute' => [
                    'name'       => 'grp.models.org.shop.update',
                    'parameters' => [
                        'organisation' => $this->organisation->id,
                        'shop'         => $chatSession->shop?->id,
                    ],
                ],
            ]
        );
    }

    private function resolveMessagesWithSenderNames(ChatSession $chatSession): array
    {
        $agentIds = $chatSession->messages
            ->where('sender_type', ChatSenderTypeEnum::AGENT)
            ->pluck('sender_id')
            ->filter()
            ->unique();

        $agentNames = $agentIds->isNotEmpty()
            ? ChatAgent::whereIn('id', $agentIds)
                ->with('user')
                ->get()
                ->mapWithKeys(fn ($agent) => [
                    $agent->id => $agent->user?->contact_name ?? $agent->user?->username ?? __('Agent'),
                ])
            : collect();

        return $chatSession->messages->map(function ($msg) use ($agentNames) {
            $data = (new ChatMessageResource($msg))->resolve();
            $data['sender_name'] = match ($msg->sender_type) {
                ChatSenderTypeEnum::AGENT => $agentNames[$msg->sender_id] ?? __('Agent'),
                default                   => null,
            };
            return $data;
        })->values()->all();
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            $this->chatConversationsBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-comments',
                        'label' => __('Conversation'),
                    ],
                ],
            ]
        );
    }
}
