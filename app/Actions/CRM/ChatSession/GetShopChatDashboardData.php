<?php

/*
 * Author: stewicca
 * Created: Thu, 17 Apr 2025
 * Copyright (c) 2025, Inikoo Ltd
 */

namespace App\Actions\CRM\ChatSession;

use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Livechat\ChatMessage;
use App\Models\CRM\Livechat\ChatSession;
use App\Models\CRM\Livechat\ShopHasChatAgent;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class GetShopChatDashboardData
{
    use AsAction;

    public function handle(Shop $shop): array
    {
        $sessionQuery = ChatSession::query()->where('shop_id', $shop->id)->whereHas('messages');

        return [
            'chatEnabled'         => (bool) Arr::get($shop->settings, 'chat.enable_chat', false),
            'chatAgents'          => ShopHasChatAgent::query()->where('shop_id', $shop->id)->join('chat_agents', 'chat_agents.id', '=', 'shop_has_chat_agents.chat_agent_id')->whereNull('chat_agents.deleted_at')->distinct('chat_agent_id')->count('chat_agent_id'),
            'chatSessionsTotal'   => (clone $sessionQuery)->count(),
            'chatSessionsWaiting' => (clone $sessionQuery)->where('status', ChatSessionStatusEnum::WAITING)->count(),
            'chatSessionsActive'  => (clone $sessionQuery)->where('status', ChatSessionStatusEnum::ACTIVE)->count(),
            'chatSessionsClosed'  => (clone $sessionQuery)->where('status', ChatSessionStatusEnum::CLOSED)->count(),
            'chatMessagesTotal'   => $this->countMessages($shop),
            'chatMessagesUnread'  => $this->countUnreadMessages($shop),
        ];
    }

    private function countMessages(Shop $shop): int
    {
        return ChatMessage::query()
            ->join('chat_sessions', 'chat_sessions.id', '=', 'chat_messages.chat_session_id')
            ->where('chat_sessions.shop_id', $shop->id)
            ->count();
    }

    private function countUnreadMessages(Shop $shop): int
    {
        return ChatMessage::query()
            ->join('chat_sessions', 'chat_sessions.id', '=', 'chat_messages.chat_session_id')
            ->where('chat_sessions.shop_id', $shop->id)
            ->where('chat_messages.is_read', false)
            ->count();
    }
}
