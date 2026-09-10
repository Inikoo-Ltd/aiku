<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Models\Chat\MetaChatEvent;
use App\Models\Chat\MetaChatSession;
use Exception;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreMetaChatEvent
{
    use AsAction;

    public function handle(
        MetaChatSession $metaChatSession,
        ChatEventTypeEnum $eventType,
        ChatActorTypeEnum $actorType,
        ?int $actorId = null,
        array $payload = []
    ): MetaChatEvent {
        try {
            return MetaChatEvent::create([
                'meta_channel_id'      => $metaChatSession->meta_channel_id,
                'meta_chat_session_id' => $metaChatSession->id,
                'event_type'           => $eventType->value,
                'actor_type'           => $actorType->value,
                'actor_id'             => $actorId,
                'payload'              => $payload,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to create meta chat event', [
                'meta_chat_session_id' => $metaChatSession->id,
                'event_type'           => $eventType->value,
                'error'                => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function closeSession(
        MetaChatSession $metaChatSession,
        ChatActorTypeEnum $actorType,
        ?int $actorId = null,
        array $additionalPayload = []
    ): MetaChatEvent {
        $payload = [];

        data_set($payload, 'closed_by_agent_id', $actorId);
        data_set($payload, 'closed_at', now()->toISOString());
        data_set($payload, 'session_duration', $metaChatSession->created_at->diffInMinutes(now()));
        data_set($payload, 'session_ulid', $metaChatSession->ulid);
        data_set($payload, 'phone_number', $metaChatSession->phone_number);

        $payload = array_merge($payload, $additionalPayload);

        return $this->handle(
            $metaChatSession,
            ChatEventTypeEnum::CLOSE,
            $actorType,
            $actorId,
            $payload
        );
    }

    public function reopenSession(
        MetaChatSession $metaChatSession,
        ChatActorTypeEnum $actorType,
        ?int $actorId = null,
        array $additionalPayload = []
    ): MetaChatEvent {
        $payload = [];

        data_set($payload, 'reopened_at', now()->toISOString());
        data_set($payload, 'session_ulid', $metaChatSession->ulid);

        $payload = array_merge($payload, $additionalPayload);

        return $this->handle(
            $metaChatSession,
            ChatEventTypeEnum::REOPEN,
            $actorType,
            $actorId,
            $payload
        );
    }
}
