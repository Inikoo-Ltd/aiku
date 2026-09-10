<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Models\Chat\MetaChatAssignment;
use App\Models\Chat\MetaChatMessage;
use App\Models\Chat\MetaChatSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Rides the same presence channel as the website chat list so agents keep a single
 * subscription per shop, under its own event name so the inbox can tell the two apart.
 */
class BroadcastMetaChatListEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public ?MetaChatSession $metaChatSession;

    /**
     * @param  MetaChatMessage|null  $message          Present for new-message events.
     * @param  MetaChatSession|null  $metaChatSession  Present for assignment/status events (assign, take-over, close, reopen).
     */
    public function __construct(public ?MetaChatMessage $message = null, ?MetaChatSession $metaChatSession = null)
    {
        $this->metaChatSession = $metaChatSession ?? $message?->metaChatSession;
    }

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('chat-list.'.($this->metaChatSession?->shop_id ?? 0));
    }

    public function broadcastAs(): string
    {
        return 'meta-chatlist';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message ? [
                'sender_type'      => $this->message->sender_type->value,
                'sender_name'      => $this->resolveSenderName(),
                'text'             => $this->resolveMessageText(),
                'shop_id'          => $this->metaChatSession?->shop_id,
                'assigned_user_id' => $this->resolveAssignedAgentId(),
            ] : null,
            'session' => $this->metaChatSession ? [
                'ulid'                          => $this->metaChatSession->ulid,
                'shop_id'                       => $this->metaChatSession->shop_id,
                'status'                        => $this->metaChatSession->status?->value,
                'assigned_user_id'              => $this->resolveAssignedAgentId(),
                'assigned_agent_name'           => $this->resolveAssignedAgentName(),
                'can_send_non_template_message' => $this->metaChatSession->can_send_non_template_message,
            ] : null,
        ];
    }

    private function resolveSenderName(): string
    {
        return match ($this->message->sender_type->value) {
            'agent'  => $this->resolveAssignedAgentName() ?? 'Agent',
            'system' => 'System',
            default  => $this->metaChatSession?->guest_identifier ?? $this->metaChatSession?->phone_number ?? 'Customer',
        };
    }

    private function activeAssignment(): ?MetaChatAssignment
    {
        /** @var MetaChatAssignment|null $assignment */
        $assignment = $this->metaChatSession?->assignments()
            ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
            ->first();

        return $assignment;
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
        $text = $this->message->message_type->value === 'text'
            ? (string) $this->message->message_text
            : (string) ($this->message->message_text ?: 'New '.$this->message->message_type->value.' message');

        return Str::limit($text, 50, '…');
    }
}
