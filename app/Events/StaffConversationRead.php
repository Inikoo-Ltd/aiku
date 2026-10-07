<?php

/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Events;

use App\Models\Chat\StaffConversation;
use App\Models\SysAdmin\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StaffConversationRead implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public StaffConversation $conversation, public User $reader, public string $readAt)
    {
    }

    public function broadcastOn(): array
    {
        return $this->conversation->activeParticipants
            ->reject(fn (User $participant) => $participant->id === $this->reader->id)
            ->map(fn (User $participant) => new PrivateChannel('grp.personal.'.$participant->id))
            ->values()
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'staff-conversation-read';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_ulid' => $this->conversation->ulid,
            'user_id'           => $this->reader->id,
            'last_read_at'      => $this->readAt,
        ];
    }
}
