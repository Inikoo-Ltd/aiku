<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Http\Resources\Chat\StaffMessageResource;
use App\Models\Chat\StaffMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StaffMessageSent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public StaffMessage $message, public string $event = 'staff-message')
    {
    }

    public function broadcastOn(): array
    {
        $channels = [];
        foreach ($this->message->conversation->participants as $participant) {
            $channels[] = new PrivateChannel('grp.personal.'.$participant->id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return $this->event;
    }

    public function broadcastWith(): array
    {
        $this->message->load(['user', 'translations', 'reactions', 'conversation']);

        return (new StaffMessageResource($this->message))->resolve();
    }
}
