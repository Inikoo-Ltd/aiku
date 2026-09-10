<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Events;

use App\Http\Resources\CRM\Livechat\MetaChatMessageResource;
use App\Models\Chat\MetaChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastMetaChatReaction implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public string $ulid;

    public function __construct(public MetaChatMessage $message)
    {
        $this->ulid = $message->metaChatSession->ulid;
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("meta-chat-session.{$this->ulid}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'reaction';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => new MetaChatMessageResource($this->message),
        ];
    }
}
