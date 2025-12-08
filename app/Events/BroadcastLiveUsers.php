<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastLiveUsers implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public array $data;

    public mixed $user;

    public function __construct(array $data, $user)
    {
        $this->data = $data;
        $this->user = $user;
    }

    public function broadcastWith(): array
    {
        return [
            'active_page' => $this->data['active_page'],
            'user_id' => $this->user->id,
            'user_alias' => $this->user->alias,
        ];
    }

    public function broadcastAs(): string
    {
        return 'changePage';
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('org.live.users'),
        ];
    }
}
