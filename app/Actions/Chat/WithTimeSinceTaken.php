<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Nov 2026 16:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat;

use App\Enums\CRM\Livechat\ChatAssignmentClaimedViaEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;

trait WithTimeSinceTaken
{
    /**
     * HELP-4896: on an agent's first reply after taking the conversation with a button (Assign to
     * me or a take-over), how long the customer waited on them since they took it. It is the
     * customer's wait, clock time, not time worked: a lock held over lunch counts. A conversation
     * picked up by replying has nothing to add, the reply is the take.
     *
     * @return array<string, mixed>
     */
    protected function timeSinceTaken(ChatSession|MetaChatSession $session, ?int $agentId): array
    {
        if (!$agentId) {
            return [];
        }

        $assignment = $session->assignments()
            ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
            ->where('chat_agent_id', $agentId)
            ->whereNotNull('claimed_via')
            ->where('claimed_via', '!=', ChatAssignmentClaimedViaEnum::REPLY->value)
            ->first();

        if (!$assignment?->assigned_at) {
            return [];
        }

        $alreadyReplied = $session->messages()
            ->where('sender_type', ChatSenderTypeEnum::AGENT->value)
            ->where('sender_id', $agentId)
            ->where('created_at', '>=', $assignment->assigned_at)
            ->exists();

        if ($alreadyReplied) {
            return [];
        }

        return [
            'taken_via'           => $assignment->claimed_via->value,
            'seconds_since_taken' => (int) $assignment->assigned_at->diffInSeconds(now(), true),
        ];
    }
}
