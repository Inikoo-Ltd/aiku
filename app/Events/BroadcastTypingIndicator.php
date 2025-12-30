<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class BroadcastTypingIndicator implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public string $userName;
    public bool $isTyping;
    public string $sessionUid;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(string $userName, bool $isTyping, string $sessionUid)
    {

        $this->userName = $userName;
        $this->isTyping = $isTyping;
        $this->sessionUid = $sessionUid;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("chat-session.{ $this->sessionUid}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'typing';
    }


    public function broadcastWith(): array
    {
        return [
            'user_name' => $this->userName,
            'is_typing' => $this->isTyping,
            'session_uid' => $this->sessionUid,
            'timestamp' => now()->toISOString(),
            'event_type' => 'typing_indicator'
        ];
    }
}
