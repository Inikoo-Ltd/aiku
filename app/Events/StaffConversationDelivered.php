<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\Chat\StaffConversation;
use App\Models\SysAdmin\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the other participants that this person's aiku has received the conversation's messages up to a moment, which
 * turns their ticks from one grey to two grey.
 */
class StaffConversationDelivered implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public StaffConversation $conversation, public User $receiver, public string $deliveredAt)
    {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return $this->conversation->activeParticipants()->where('users.id', '!=', $this->receiver->id)->pluck('users.id')
            ->map(fn (int $userId) => new PrivateChannel('grp.personal.'.$userId))
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'staff-conversation-delivered';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_ulid' => $this->conversation->ulid,
            'user_id'           => $this->receiver->id,
            'last_delivered_at' => $this->deliveredAt,
        ];
    }
}
