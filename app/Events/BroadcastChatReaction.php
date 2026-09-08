<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Events;

use App\Http\Resources\CRM\Livechat\ChatMessageResource;
use App\Models\Chat\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastChatReaction implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public string $ulid;

    public function __construct(public ChatMessage $message)
    {
        $this->ulid = $message->chatSession->ulid;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("chat-session.{$this->ulid}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'reaction';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => new ChatMessageResource($this->message),
        ];
    }
}
