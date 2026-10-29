<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 03:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatAiDraftStatusEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatAiDraft;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatMessage;
use App\Models\Chat\MetaChatSession;
use Illuminate\Support\Collection;
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

        $pending = DraftChatReply::pendingDraft($chatSession);

        if (!$pending) {
            return null;
        }

        $replyText = (string) $reply->message_text;
        $decision  = [
            'reply_message_id'   => $reply->id,
            'decided_by_user_id' => ChatAgent::find($reply->sender_id)?->user_id,
            'decided_at'         => now(),
        ];

        $replaced = self::replacedSinceLastAgentReply($chatSession, $reply, $pending)
            ->map(fn (ChatAiDraft $draft) => [$draft, self::statusFor($draft, $replyText)])
            ->reject(fn (array $settled) => $settled[1] === ChatAiDraftStatusEnum::SUPERSEDED)
            ->sortBy(fn (array $settled) => $settled[1] === ChatAiDraftStatusEnum::USED ? 0 : 1)
            ->first();

        $pendingStatus = self::statusFor($pending, $replyText);

        if ($replaced && ($pendingStatus === ChatAiDraftStatusEnum::SUPERSEDED || ($pendingStatus === ChatAiDraftStatusEnum::EDITED && $replaced[1] === ChatAiDraftStatusEnum::USED))) {
            $pending->update(['status' => ChatAiDraftStatusEnum::SUPERSEDED] + $decision);
            $replaced[0]->update(['status' => $replaced[1]] + $decision);

            return MeasureChatAiDraft::run($replaced[0]);
        }

        $pending->update(['status' => $pendingStatus] + $decision);

        return MeasureChatAiDraft::run($pending);
    }

    private static function statusFor(ChatAiDraft $draft, string $replyText): ChatAiDraftStatusEnum
    {
        $kept = self::draftWordsKept($draft->text, $replyText);

        return match (true) {
            self::normalised($draft->text) === self::normalised($replyText) => ChatAiDraftStatusEnum::USED,
            $kept >= self::RETYPED && self::wordCount($replyText) <= 1.5 * self::wordCount($draft->text) => ChatAiDraftStatusEnum::USED,
            $draft->taken_at !== null || $kept >= self::REWORDED => ChatAiDraftStatusEnum::EDITED,
            default => ChatAiDraftStatusEnum::SUPERSEDED,
        };
    }

    /**
     * Drafts a newer customer message replaced while the agent was still writing: the agent
     * may be sending one of those rather than the one waiting now.
     *
     * @return Collection<int, ChatAiDraft>
     */
    private static function replacedSinceLastAgentReply(ChatSession|MetaChatSession $chatSession, ChatMessage|MetaChatMessage $reply, ChatAiDraft $pending): Collection
    {
        $lastAgentReplyAt = $chatSession->messages()
            ->where('sender_type', ChatSenderTypeEnum::AGENT)
            ->where('id', '<', $reply->id)
            ->max('created_at');

        return ChatAiDraft::where($chatSession instanceof ChatSession ? 'chat_session_id' : 'meta_chat_session_id', $chatSession->id)
            ->where('status', ChatAiDraftStatusEnum::SUPERSEDED)
            ->whereNull('reply_message_id')
            ->where('id', '<', $pending->id)
            ->when($lastAgentReplyAt, fn ($query) => $query->where('created_at', '>', $lastAgentReplyAt))
            ->orderByDesc('id')
            ->get();
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

    public static function wordCount(string $text): int
    {
        return count(preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY));
    }

    public static function normalised(string $text): string
    {
        return preg_replace('/\s+/u', ' ', trim(mb_strtolower($text)));
    }
}
