<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells the agent looking at the conversation when it will get a 👍 and close, or that it no
 * longer will, so the countdown on their screen follows the job.
 */
class BroadcastChatClosingAfterThanks implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public string $ulid;

    public bool $isWhatsapp;

    public function __construct(ChatSession|MetaChatSession $chatSession, public ?string $closingAt)
    {
        $this->ulid       = $chatSession->ulid;
        $this->isWhatsapp = $chatSession instanceof MetaChatSession;
    }

    public function broadcastOn(): array
    {
        return [
            $this->isWhatsapp ? new PrivateChannel("meta-chat-session.{$this->ulid}") : new Channel("chat-session.{$this->ulid}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'closing';
    }

    public function broadcastWith(): array
    {
        return ['closing_at' => $this->closingAt];
    }
}
