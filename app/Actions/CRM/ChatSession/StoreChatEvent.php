<?php

namespace App\Actions\CRM\ChatSession;

use App\Models\CRM\Livechat\ChatEvent;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use Exception;
use Illuminate\Support\Facades\Log;

class StoreChatEvent
{
    use AsAction;

    public function handle(
        ChatSession $chatSession,
        ChatEventTypeEnum $eventType,
        ChatActorTypeEnum $actorType,
        ?int $actorId = null,
        array $payload = []
    ): ChatEvent {
        try {
            return ChatEvent::create([
                'chat_session_id' => $chatSession->id,
                'event_type' => $eventType->value,
                'actor_type' => $actorType->value,
                'actor_id' => $actorId,
                'payload' => $payload,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to create chat event', [
                'session_id' => $chatSession->id,
                'event_type' => $eventType->value,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function openSession(
        ChatSession $chatSession,
        ChatActorTypeEnum $actorType,
        ?int $actorId = null,
        array $additionalPayload = []
    ): ChatEvent {
        $payload = [];
        data_set($payload, 'ip_address', request()->ip());
        data_set($payload, 'user_agent', request()->userAgent());
        data_set($payload, 'is_guest', $actorType === ChatActorTypeEnum::GUEST);

        $payload = array_merge($payload, $additionalPayload);

        return $this->handle(
            $chatSession,
            ChatEventTypeEnum::OPEN,
            $actorType,
            $actorId,
            $payload
        );
    }

    /**
     * Create MESSAGE event
     */
    public function messageSent(
        ChatSession $chatSession,
        ChatActorTypeEnum $actorType,
        ?int $actorId = null,
        int $messageId,
        string $messageType,
        bool $isGuestMessage = false
    ): ChatEvent {
        $payload = [];

        data_set($payload, 'chat_message_id', $messageId);
        data_set($payload, 'chat_message_type', $messageType);
        data_set($payload, 'is_guest_message', $isGuestMessage);

        return $this->handle(
            $chatSession,
            ChatEventTypeEnum::SEND,
            $actorType,
            $actorId,
            $payload
        );
    }

    /**
     * Create CLOSE event
     */
    public function closeSession(
        ChatSession $chatSession,
        ChatActorTypeEnum $actorType,
        ?int $actorId = null,
        array $additionalPayload = []
    ): ChatEvent {
        $payload = [];

        data_set($payload, 'closed_by_agent_id', $actorId);
        data_set($payload, 'closed_at', now()->toISOString());
        data_set($payload, 'session_duration', $chatSession->created_at->diffInMinutes(now()));
        data_set($payload, 'session_ulid', $chatSession->ulid);

        if ($chatSession->web_user_id) {
            data_set($payload, 'user_type', 'authenticated');
            data_set($payload, 'web_user_id', $chatSession->web_user_id);
        } else {
            data_set($payload, 'user_type', 'guest');
            data_set($payload, 'guest_identifier', $chatSession->guest_identifier);
        }

        $payload = array_merge($payload, $additionalPayload);

        return $this->handle(
            $chatSession,
            ChatEventTypeEnum::CLOSE,
            $actorType,
            $actorId,
            $payload
        );
    }


    public function transferSession(
        ChatSession $chatSession,
        int $fromAgentId,
        int $toAgentId,
        array $additionalPayload = []
    ): ChatEvent {
        $payload = [];

        data_set($payload, 'from_agent_id', $fromAgentId);
        data_set($payload, 'to_agent_id', $toAgentId);
        data_set($payload, 'transferred_at', now()->toISOString());
        data_set($payload, 'session_previous_status', $chatSession->getOriginal('status'));
        data_set($payload, 'session_new_status', $chatSession->status->value);

        $payload = array_merge($payload, $additionalPayload);

        return $this->handle(
            $chatSession,
            ChatEventTypeEnum::TRANSFER,
            ChatActorTypeEnum::AGENT,
            $fromAgentId,
            $payload
        );
    }

    /**
     * Create ASSIGNMENT event
     */
    public function assignSession(
        ChatSession $chatSession,
        int $agentId,
        int $assignedByAgentId,
        array $additionalPayload = []
    ): ChatEvent {
        $payload = [];

        data_set($payload, 'assigned_agent_id', $agentId);
        data_set($payload, 'assigned_by_agent_id', $assignedByAgentId);
        data_set($payload, 'assigned_at', now()->toISOString());

        $payload = array_merge($payload, $additionalPayload);

        return $this->handle(
            $chatSession,
            ChatEventTypeEnum::ASSIGNMENT,
            ChatActorTypeEnum::AGENT,
            $assignedByAgentId,
            $payload
        );
    }

    /**
     * Create SYSTEM event
     */
    public function systemEvent(
        ChatSession $chatSession,
        string $systemAction,
        array $additionalPayload = []
    ): ChatEvent {
        $payload = [];

        data_set($payload, 'system_action', $systemAction);
        data_set($payload, 'triggered_at', now()->toISOString());

        $payload = array_merge($payload, $additionalPayload);

        return $this->handle(
            $chatSession,
            ChatEventTypeEnum::SYSTEM,
            ChatActorTypeEnum::SYSTEM,
            null,
            $payload
        );
    }

    /**
     * Generic event creator for custom events
     */
    public function customEvent(
        ChatSession $chatSession,
        ChatEventTypeEnum $eventType,
        ChatActorTypeEnum $actorType,
        ?int $actorId = null,
        array $payload = []
    ): ChatEvent {
        return $this->handle($chatSession, $eventType, $actorType, $actorId, $payload);
    }
}
