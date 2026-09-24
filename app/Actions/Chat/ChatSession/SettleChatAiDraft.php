<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 03:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatAiDraftStatusEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatAiDraft;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatMessage;
use App\Models\Chat\MetaChatSession;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * An agent answered while a draft was waiting: it counts as sent as written when the words
 * match, sent after changes when the agent took it and rewrote it, and not used when they wrote
 * their own. These counts decide whether drafts are ever trusted to go out on their own.
 */
class SettleChatAiDraft
{
    use AsAction;

    public function handle(ChatSession|MetaChatSession $chatSession, ChatMessage|MetaChatMessage $reply): ?ChatAiDraft
    {
        $draft = DraftChatReply::pendingDraft($chatSession);

        if (!$draft) {
            return null;
        }

        $status = match (true) {
            !$draft->taken_at                                                      => ChatAiDraftStatusEnum::SUPERSEDED,
            self::normalised($draft->text) === self::normalised((string) $reply->message_text) => ChatAiDraftStatusEnum::USED,
            default                                                                => ChatAiDraftStatusEnum::EDITED,
        };

        $draft->update([
            'status'             => $status,
            'reply_message_id'   => $reply->id,
            'decided_by_user_id' => ChatAgent::find($reply->sender_id)?->user_id,
            'decided_at'         => now(),
        ]);

        return $draft;
    }

    private static function normalised(string $text): string
    {
        return preg_replace('/\s+/u', ' ', trim(mb_strtolower($text)));
    }
}
