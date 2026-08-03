<?php

namespace App\Events;

use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\CRM\WebUser;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class BroadcastChatListEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public ?ChatMessage $message;

    public ?ChatSession $chatSession;

    /**
     * @param  ChatMessage|null  $message      Present for new-message events.
     * @param  ChatSession|null  $chatSession  Present for assignment/status events (assign, take-over, close, reopen).
     */
    public function __construct(?ChatMessage $message = null, ?ChatSession $chatSession = null)
    {
        $this->message = $message;
        $this->chatSession = $chatSession ?? $message?->chatSession;
    }

    public function broadcastOn()
    {
        $shopId = $this->chatSession?->shop_id ?? 0;

        return new PresenceChannel("chat-list.{$shopId}");
    }

    public function broadcastAs(): string
    {
        return 'chatlist';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message ? [
                'sender_type'       => $this->message->sender_type->value,
                'sender_name'       => $this->resolveSenderName(),
                'text'              => $this->resolveMessageText(),
                'shop_id'           => $this->chatSession?->shop_id,
                'assigned_user_id'  => $this->resolveAssignedAgentId(),
            ] : null,
            'session' => $this->chatSession ? [
                'ulid'                => $this->chatSession->ulid,
                'shop_id'             => $this->chatSession->shop_id,
                'status'              => $this->chatSession->status?->value,
                'assigned_user_id'    => $this->resolveAssignedAgentId(),
                'assigned_agent_name' => $this->resolveAssignedAgentName(),
            ] : null,
        ];
    }

    private function resolveSenderName(): string
    {
        $senderName = "Customer";

        if ($this->message->sender_type->value === 'guest') {
            $senderName = $this->chatSession?->guest_identifier ?? "Guest";
        }

        if ($this->message->sender_type->value === 'user') {
            $webUser = WebUser::find($this->message->sender_id);
            $senderName = $webUser?->customer?->contact_name ?? $webUser?->username ?? "Customer";
        }

        return $senderName;
    }

    private function activeAssignment(): ?\App\Models\Chat\ChatAssignment
    {
        return $this->chatSession?->assignments()
            ->where('status', ChatAssignmentStatusEnum::ACTIVE)
            ->first();
    }

    private function resolveAssignedAgentId(): ?int
    {
        return $this->activeAssignment()?->chatAgent?->user?->id;
    }

    private function resolveAssignedAgentName(): ?string
    {
        return $this->activeAssignment()?->chatAgent?->user?->contact_name;
    }

    private function resolveMessageText(): string
    {
        if ($this->message->message_type->value === 'text') {
            $text = $this->message->original_text ?? $this->message->message_text;
        } else {
            $text = "New " . $this->message->message_type->value . " message";
        }

        return Str::limit($text, 50, '…');
    }
}
