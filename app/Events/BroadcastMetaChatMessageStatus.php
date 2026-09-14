<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Chat\MetaChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class BroadcastMetaChatMessageStatus implements ShouldBroadcastNow
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
        return 'status';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id'   => $this->message->id,
            'status'       => Arr::get($this->message->metadata, 'wa_status'),
            'error'        => Arr::get($this->message->metadata, 'wa_error.message'),
            'is_read'      => $this->message->is_read,
            'delivered_at' => $this->message->delivered_at?->toISOString(),
            'read_at'      => $this->message->read_at?->toISOString(),
        ];
    }
}
