<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use App\Models\CRM\Livechat\ChatSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class BroadcastMessagesRead implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public ChatSession $chatSession;
    public array $messageIds;
    public string $readAt;
    public string $readerType;

    /**
     * Create a new event instance.
     *
     * @param ChatSession $chatSession
     * @param array $messageIds
     * @param string $readerType
     */
    public function __construct(ChatSession $chatSession, array $messageIds, string $readerType)
    {
        $this->chatSession = $chatSession;
        $this->messageIds = $messageIds;
        $this->readAt = now()->toIsoString();
        $this->readerType = $readerType;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("chat-session.{$this->chatSession->ulid}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'messages.read';
    }

    public function broadcastWith(): array
    {
        return [
            'message_ids' => $this->messageIds,
            'read_at' => $this->readAt,
            'reader_type' => $this->readerType,
            'session_ulid' => $this->chatSession->ulid,
        ];
    }
}
