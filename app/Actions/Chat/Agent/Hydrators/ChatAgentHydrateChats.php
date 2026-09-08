<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Agent\Hydrators;

use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatAssignment;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

class ChatAgentHydrateChats implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(ChatAgent $chatAgent): string
    {
        return (string) $chatAgent->id;
    }

    public function handle(ChatAgent $chatAgent): void
    {
        $chatAgent->updateQuietly([
            'current_chat_count' => $this->getCurrentChatCount($chatAgent),
        ]);
    }

    public function getCurrentChatCount(ChatAgent $chatAgent): int
    {
        return ChatAssignment::where('chat_agent_id', $chatAgent->id)
            ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
            ->whereHas('chatSession', function (Builder $query) {
                $query->where('status', '!=', ChatSessionStatusEnum::CLOSED->value);
            })
            ->count();
    }
}
