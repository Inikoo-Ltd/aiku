<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use App\Models\CRM\Livechat\ChatMessage;
use App\Http\Resources\CRM\Livechat\ChatMessageResource;

class BroadcastChatListEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $message;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(?ChatMessage $message = null)
    {
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PresenceChannel('chat-list');
    }

    public function broadcastAs(): string
    {
        return 'chatlist';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message ? new ChatMessageResource($this->message) : null,
        ];
    }
}
