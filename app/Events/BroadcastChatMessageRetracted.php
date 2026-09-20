<?php

namespace App\Events;

use App\Enums\CRM\Livechat\ChatRetractionReasonEnum;
use App\Models\Chat\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The session channel is public and the customer's widget listens on it, so a retraction
 * carries nothing but the identity of the message being taken back.
 */
class BroadcastChatMessageRetracted implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public string $ulid;

    public function __construct(public ChatMessage $message)
    {
        $this->ulid = $message->chatSession->ulid;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("chat-session.{$this->ulid}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.retracted';
    }

    public function broadcastWith(): array
    {
        return [
            'id'           => $this->message->id,
            'retracted_at' => $this->message->deleted_at?->toISOString(),
            'retraction_reason' => ChatRetractionReasonEnum::tryFrom(
                (string) ($this->message->metadata['retraction_reason'] ?? '')
            )?->label(),
        ];
    }
}
