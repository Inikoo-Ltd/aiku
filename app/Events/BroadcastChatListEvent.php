<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use App\Models\CRM\Livechat\ChatMessage;
use App\Models\CRM\WebUser;
use Illuminate\Support\Str;

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
        if (!$this->message) {
            return [
                'message' => null
            ];
        }

        $senderName = "Customer";

        if ($this->message->sender_type->value === 'guest') {
            $senderName = $this->message->chatSession?->guest_identifier;
        }

        if ($this->message->sender_type->value === 'user') {
            $webUser = WebUser::find($this->message->sender_id);
            $senderName = $webUser?->customer?->name;
        }

        if ($this->message->message_type->value === 'text') {
            $text = $this->message->original_text ?? $this->message->message_text;
        } else {
            $text = "New " .$this->message->message_type->value . " message";
        }
        $text = Str::limit($text, 50, '…');
        return [
            'message' => [
                'sender_type' => $this->message->sender_type,
                'sender_name' => $senderName,
                'text'        => $text,
            ]
        ];
    }
}
