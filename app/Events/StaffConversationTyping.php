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

/**
 * "Someone is typing" for the internal staff chat: only the fact, never the text, and nothing is stored.
 */
class StaffConversationTyping implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public StaffConversation $conversation, public User $typist)
    {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return $this->conversation->activeParticipants()->where('users.id', '!=', $this->typist->id)->pluck('users.id')
            ->map(fn (int $userId) => new PrivateChannel('grp.personal.'.$userId))
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'staff-typing';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_ulid' => $this->conversation->ulid,
            'user_id'           => $this->typist->id,
            'user_name'         => $this->typist->chatName(),
        ];
    }
}
