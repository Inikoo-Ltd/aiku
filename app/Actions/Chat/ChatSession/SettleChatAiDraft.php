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
 * match or they retyped nearly all of it, even without pressing Use; sent after changes when
 * they took it, or kept most of its words, and rewrote it; and not used when they wrote their own. These counts decide whether drafts are ever trusted to go out on their own.
 */
class SettleChatAiDraft
{
    use AsAction;

    private const float RETYPED = 0.9;

    private const float REWORDED = 0.6;

    public function handle(ChatSession|MetaChatSession $chatSession, ChatMessage|MetaChatMessage $reply): ?ChatAiDraft
    {
        ClassifyChatTurn::recordReply($chatSession, $reply);

        $draft = DraftChatReply::pendingDraft($chatSession);

        if (!$draft) {
            return null;
        }

        $replyText = (string) $reply->message_text;
        $kept      = self::draftWordsKept($draft->text, $replyText);
        $status    = match (true) {
            self::normalised($draft->text) === self::normalised($replyText) => ChatAiDraftStatusEnum::USED,
            $kept >= self::RETYPED && self::wordCount($replyText) <= 1.5 * self::wordCount($draft->text) => ChatAiDraftStatusEnum::USED,
            $draft->taken_at !== null || $kept >= self::REWORDED => ChatAiDraftStatusEnum::EDITED,
            default => ChatAiDraftStatusEnum::SUPERSEDED,
        };

        $draft->update([
            'status'             => $status,
            'reply_message_id'   => $reply->id,
            'decided_by_user_id' => ChatAgent::find($reply->sender_id)?->user_id,
            'decided_at'         => now(),
        ]);

        return $draft;
    }

    /**
     * The share of the draft's words that are in the reply: an agent who retyped it, or
     * added a greeting and their name, kept nearly all of them.
     */
    public static function draftWordsKept(string $draft, string $reply): float
    {
        $draftWords = self::words((string) preg_replace(DraftChatReply::GAP, ' ', $draft));

        if (!$draftWords) {
            return 0;
        }

        return count(array_intersect($draftWords, self::words($reply))) / count($draftWords);
    }

    /**
     * @return array<int, string>
     */
    private static function words(string $text): array
    {
        return array_values(array_unique(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY)));
    }

    private static function wordCount(string $text): int
    {
        return count(preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY));
    }

    private static function normalised(string $text): string
    {
        return preg_replace('/\s+/u', ' ', trim(mb_strtolower($text)));
    }
}
