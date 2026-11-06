<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 03:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Helpers\Translations\DetectLanguageWithJev;
use App\Enums\CRM\Livechat\ChatAiDraftStatusEnum;
use App\Models\Chat\ChatAiDraft;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatMessage;
use App\Models\Chat\MetaChatSession;
use App\Models\CRM\Customer;
use App\Models\Helpers\Language;

/**
 * What the drafts on a chat share. Since INI-128 aiku writes no draft itself: the only drafts are
 * the ones the agent's own assistant stores through the customer service connection
 * (StoreAssistantChatDraft). Older drafts written here are kept for the history views.
 */
class DraftChatReply
{
    /**
     * An open-ended reply written by aiku before INI-128, kept on old drafts.
     */
    public const string SUGGESTION = 'suggestion';

    /**
     * Written by the agent's own assistant through the customer service connection and saved here
     * for the agent to use, edit or discard. Never sent on its own and never counted towards auto-send.
     */
    public const string ASSISTANT = 'assistant';

    /**
     * A gap the agent fills before sending: [[what to decide]].
     */
    public const string GAP = '~\[\[[^\]]*\]\]~u';

    /**
     * An assistant draft written since the customer last wrote is waiting: the inbox's draft_waiting rule.
     */
    public static function freshAssistantDraftWaiting(ChatSession|MetaChatSession $chatSession): bool
    {
        return $chatSession->newQuery()->whereKey($chatSession->id)
            ->whereHas('aiDrafts', fn ($draft) => $draft->where('status', ChatAiDraftStatusEnum::PENDING->value)
                ->whereRaw("facts->>'mode' = ?", [self::ASSISTANT])
                ->whereColumn('chat_ai_drafts.created_at', '>=', $chatSession->getTable().'.last_visitor_message_at'))
            ->exists();
    }

    public static function pendingDraft(ChatSession|MetaChatSession $chatSession): ?ChatAiDraft
    {
        return ChatAiDraft::where($chatSession instanceof ChatSession ? 'chat_session_id' : 'meta_chat_session_id', $chatSession->id)
            ->where('status', ChatAiDraftStatusEnum::PENDING)
            ->first();
    }

    /**
     * The language of what the customer wrote, named to the model rather than left to it: told to
     * match the customer, it answered an English customer in Spanish. The message's detected
     * language when translation has already run, detected here when it has not (WhatsApp chats
     * never record one on the session), and the chat's language only when detection fails.
     */
    public static function replyLanguage(ChatSession|MetaChatSession $chatSession, ChatMessage|MetaChatMessage $trigger, string $text): ?Language
    {
        $chatLanguage = $chatSession->language ?? $chatSession->shop?->language;

        return $trigger->originalLanguage ?? DetectLanguageWithJev::inConversation($text, $chatSession) ?? $chatLanguage;
    }

    /**
     * Order facts only for somebody aiku already knows as this customer: logged in on the
     * website, or a WhatsApp number or email already linked to them. Never a stranger's say-so.
     */
    public static function knownCustomer(ChatSession|MetaChatSession $chatSession): ?Customer
    {
        $customer = $chatSession instanceof ChatSession ? $chatSession->webUser?->customer : $chatSession->customer;

        return $customer && $customer->shop_id === $chatSession->shop_id ? $customer : null;
    }
}
