<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 03:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Chat\ChatAiDraft;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells an open conversation that a draft is waiting, and nothing more. The website chat
 * channel is public and the customer's own widget listens on it, so the words travel only
 * through the staff endpoint that fetches them.
 */
class BroadcastChatAiDraft implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public string $channelName;
    public int $draftId;

    public function __construct(ChatSession|MetaChatSession $chatSession, ChatAiDraft $draft)
    {
        $this->channelName = ($chatSession instanceof MetaChatSession ? 'meta-chat-session.' : 'chat-session.').$chatSession->ulid;
        $this->draftId     = $draft->id;
    }

    public function broadcastOn(): array
    {
        return [
            str_starts_with($this->channelName, 'meta-') ? new PrivateChannel($this->channelName) : new Channel($this->channelName),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ai-draft';
    }

    public function broadcastWith(): array
    {
        return ['draft_id' => $this->draftId];
    }
}
