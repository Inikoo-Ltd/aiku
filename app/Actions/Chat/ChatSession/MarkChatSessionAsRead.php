<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 15:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\MetaChatSession\MarkMetaChatMessagesAsRead;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Events\BroadcastChatListEvent;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Marks a conversation read from the inbox list without opening it, as opening it would:
 * the customer's messages become read for the whole team.
 */
class MarkChatSessionAsRead
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public function handle(ChatSession|MetaChatSession $chatSession): void
    {
        if ($chatSession instanceof MetaChatSession) {
            MarkMetaChatMessagesAsRead::run($chatSession);

            return;
        }

        MarkChatMessagesAsRead::run($chatSession, ChatSenderTypeEnum::AGENT);
        BroadcastChatListEvent::dispatch(null, $chatSession);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession): JsonResponse
    {
        return $this->respond($chatSession);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inWhatsapp(?string $organisation, MetaChatSession $metaChatSession): JsonResponse
    {
        return $this->respond($metaChatSession);
    }

    private function respond(ChatSession|MetaChatSession $chatSession): JsonResponse
    {
        if (!$this->getAuthorisedChatAgent($chatSession)) {
            return response()->json([
                'success' => false,
                'message' => __('Only agents working this shop can mark its chats as read'),
            ], 403);
        }

        $this->handle($chatSession);

        return response()->json(['success' => true]);
    }
}
