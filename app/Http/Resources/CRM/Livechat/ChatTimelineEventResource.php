<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Http\Resources\CRM\Livechat;

use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Models\Chat\ChatAgent;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * Renders a ChatEvent or a MetaChatEvent as one timeline entry. The sentence is built
 * here rather than in the client so both chat components stay identical and the wording
 * follows the reader's language instead of whatever was stored when it happened.
 */
class ChatTimelineEventResource extends JsonResource
{
    public function toArray($request): array
    {
        $eventType = $this->event_type instanceof ChatEventTypeEnum
            ? $this->event_type
            : ChatEventTypeEnum::tryFrom((string) $this->event_type);

        return [
            'id'          => $this->id,
            'event_type'  => $eventType?->value,
            'actor_name'  => $this->actorName(),
            'description' => $this->describe($eventType),
            'created_at'  => $this->created_at,
            'timestamp'   => $this->created_at?->timestamp,
        ];
    }

    protected function payload(string $key, $default = null)
    {
        return Arr::get($this->payload ?? [], $key, $default);
    }

    protected function actorName(): string
    {
        $fromPayload = $this->payload('spammed_by_name')
            ?? $this->payload('unspammed_by_name')
            ?? $this->payload('assigned_agent_name')
            ?? $this->payload('to_agent_name');

        if ($fromPayload) {
            return $fromPayload;
        }

        $actorType = is_object($this->actor_type) ? $this->actor_type->value : $this->actor_type;

        if ($actorType !== 'agent') {
            return __('The customer');
        }

        return ChatAgent::with('user')->find($this->actor_id)?->user?->contact_name ?? __('An agent');
    }

    protected function describe(?ChatEventTypeEnum $eventType): string
    {
        $actor = $this->actorName();

        return match ($eventType) {
            ChatEventTypeEnum::SPAM     => __(':actor marked the chat as spam', ['actor' => $actor]),
            ChatEventTypeEnum::NOT_SPAM => __(':actor removed the chat from spam', ['actor' => $actor]),
            ChatEventTypeEnum::PRIORITY => $this->describePriority($actor),
            ChatEventTypeEnum::TRANSFER => __(':actor requested a transfer', ['actor' => $actor]),
            ChatEventTypeEnum::TRANSFER_ACCEPT => __(':actor accepted the transfer', ['actor' => $actor]),
            ChatEventTypeEnum::TRANSFER_REJECT => __(':actor rejected the transfer', ['actor' => $actor]),
            ChatEventTypeEnum::TRANSFER_TO_AGENT => $this->describeTransfer($actor),
            ChatEventTypeEnum::ASSIGNMENT_TO_SELF => __(':actor took the chat', ['actor' => $actor]),
            default => __(':actor updated the chat', ['actor' => $actor]),
        };
    }

    /**
     * The two channels record the change differently: website chat stores only the new
     * value under `values.priority`, WhatsApp keeps both sides of it.
     */
    protected function describePriority(string $actor): string
    {
        $current  = $this->payload('priority_current') ?? $this->payload('values.priority');
        $previous = $this->payload('priority_previous');

        if (!$current) {
            return __(':actor changed the priority', ['actor' => $actor]);
        }

        if ($previous && $previous !== $current) {
            return __(':actor changed the priority from :previous to :current', [
                'actor'    => $actor,
                'previous' => __(ucfirst($previous)),
                'current'  => __(ucfirst($current)),
            ]);
        }

        return __(':actor set the priority to :current', [
            'actor'   => $actor,
            'current' => __(ucfirst($current)),
        ]);
    }

    protected function describeTransfer(string $actor): string
    {
        $from = $this->payload('from_agent_name');

        if ($this->payload('action_type') === 'take_over') {
            return $from
                ? __(':actor took the chat over from :from', ['actor' => $actor, 'from' => $from])
                : __(':actor took the chat over', ['actor' => $actor]);
        }

        return $from
            ? __(':from transferred the chat to :actor', ['from' => $from, 'actor' => $actor])
            : __('The chat was transferred to :actor', ['actor' => $actor]);
    }
}
