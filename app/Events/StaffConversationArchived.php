<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 20:30:00 Malaysia Time, Kuala Lumpur, Malaysia
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

class StaffConversationArchived implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public StaffConversation $conversation, public User $user)
    {
    }

    public function broadcastOn(): array
    {
        return $this->conversation->participants
            ->map(fn (User $participant) => new PrivateChannel('grp.personal.'.$participant->id))
            ->values()
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'staff-conversation-archived';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_ulid' => $this->conversation->ulid,
            'user_id'           => $this->user->id,
            'user_name'         => $this->user->chatName(),
        ];
    }
}
