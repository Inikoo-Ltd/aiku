<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 23 Oct 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatAiOutcomeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\ChatAiOutcome;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Records what the sweep did with the customer's latest message, one row per message. Asked again
 * for the same message, the stronger outcome stays (ChatAiOutcomeEnum order) and the latest of
 * equal strength replaces it, so the coverage report never counts a chat twice. A suggestion already
 * waiting holds only while its draft is newer than the customer's message; once stale, the next outcome replaces it.
 */
class StoreChatAiOutcome
{
    use AsAction;

    /**
     * @param array{reason?: ?string, chat_ai_draft_id?: ?int, note_event_id?: ?int, run_id?: ?int, trigger_message_id?: ?int} $details
     */
    public function handle(ChatSession|MetaChatSession $session, ChatAiOutcomeEnum $outcome, array $details = []): ChatAiOutcome
    {
        $sessionColumn = $session instanceof MetaChatSession ? 'meta_chat_session_id' : 'chat_session_id';
        $triggerId     = $details['trigger_message_id'] ?? self::latestCustomerMessageId($session);
        $shop          = $session->shop;

        $values = [
            'group_id'         => $shop->group_id,
            'organisation_id'  => $shop->organisation_id,
            'shop_id'          => $shop->id,
            'channel'          => $session instanceof MetaChatSession ? 'whatsapp' : $session->channel->value,
            'outcome'          => $outcome,
            'reason'           => isset($details['reason']) ? mb_substr(trim($details['reason']), 0, 500) : null,
            'chat_ai_draft_id' => $details['chat_ai_draft_id'] ?? null,
            'note_event_id'    => $details['note_event_id'] ?? null,
            'run_id'           => $details['run_id'] ?? null,
        ];

        if (!$triggerId) {
            return ChatAiOutcome::create([$sessionColumn => $session->id, 'trigger_message_id' => null, ...$values]);
        }

        return DB::transaction(function () use ($sessionColumn, $session, $triggerId, $values, $outcome) {
            $existing = ChatAiOutcome::where($sessionColumn, $session->id)
                ->where('trigger_message_id', $triggerId)
                ->lockForUpdate()
                ->first();

            if (!$existing) {
                return ChatAiOutcome::create([$sessionColumn => $session->id, 'trigger_message_id' => $triggerId, ...$values]);
            }

            $staleWaiting = $existing->outcome === ChatAiOutcomeEnum::SUGGESTION_ALREADY_WAITING && !DraftChatReply::freshAssistantDraftWaiting($session);

            if (!$staleWaiting && $existing->outcome->rank() < $outcome->rank()) {
                return $existing;
            }

            $existing->update($values);

            return $existing;
        });
    }

    public static function latestCustomerMessageId(ChatSession|MetaChatSession $session): ?int
    {
        return $session->messages()
            ->whereIn('sender_type', [ChatSenderTypeEnum::GUEST, ChatSenderTypeEnum::USER])
            ->max('id');
    }
}
