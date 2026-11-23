<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Http\Resources\CRM\Livechat;

use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketKindEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use App\Models\Helpers\Ticket;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

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
            'note'        => $eventType === ChatEventTypeEnum::INTERNAL_NOTE ? Str::ucfirst(preg_replace('/^\s*[\pL ]{0,20}\bCS routine:\s*/iu', '', (string) $this->payload('text'))) : null,
            'note_follow_up' => $eventType === ChatEventTypeEnum::INTERNAL_NOTE && $this->payload('follow_up_in') ? Arr::first($this->noteLinks((string) $this->payload('follow_up_in'), auth()->user()?->group_id)) : null,
            'note_answer_in' => $eventType === ChatEventTypeEnum::INTERNAL_NOTE && $this->payload('answer_in') ? Arr::first($this->noteLinks((string) $this->payload('answer_in'), auth()->user()?->group_id)) : null,
            'note_links'  => $eventType === ChatEventTypeEnum::INTERNAL_NOTE ? $this->noteLinks((string) $this->payload('text'), auth()->user()?->group_id) : [],
            'note_tickets' => match ($eventType) {
                ChatEventTypeEnum::INTERNAL_NOTE => $this->noteTickets((string) $this->payload('text'), auth()->user()?->group_id),
                ChatEventTypeEnum::TICKET => $this->noteTickets((string) $this->payload('key'), auth()->user()?->group_id),
                default => [],
            },
            'created_at'  => $this->created_at,
            'timestamp'   => $this->created_at?->timestamp,
        ];
    }

    /**
     * Chats Aiku names in a note, by id, become links to that conversation with its shop,
     * so a colleague filtering the inbox to another shop still lands on it in one click.
     *
     * @return array<int, array{ulid: string, shop: string|null, url: string}>
     */
    protected function noteLinks(string $text, ?int $groupId): array
    {
        preg_match_all('/\\b[0-9A-HJKMNP-TV-Z]{26}\\b/', $text, $matches);

        if (!$groupId || !$matches[0]) {
            return [];
        }

        return ChatSession::with('shop.organisation')
            ->whereIn('ulid', array_unique($matches[0]))
            ->whereHas('shop', fn ($query) => $query->where('group_id', $groupId))
            ->get()
            ->map(fn (ChatSession $chatSession) => [
                'ulid' => $chatSession->ulid,
                'shop' => $chatSession->shop?->name,
                'url'  => route('grp.org.chat.inbox.conversation', [$chatSession->shop->organisation->slug, $chatSession->ulid]),
            ])->values()->all();
    }

    /**
     * Tickets a note names become a chip with the ticket's live status, so staff see at a glance
     * whether engineers are still on it or it is done, without the note having to say so.
     *
     * @return array<int, array{reference: string, subject: string, status_icon: array, url: string}>
     */
    protected function noteTickets(string $text, ?int $groupId): array
    {
        preg_match_all('/\\b(?:PRE|HELP|INI|CUS|MER)-\\d+\\b/', $text, $matches);

        if (!$groupId || !$matches[0]) {
            return [];
        }

        return Ticket::where('group_id', $groupId)
            ->whereIn('reference', array_unique($matches[0]))
            ->with('assignee.image')
            ->get()
            ->map(fn (Ticket $ticket) => self::ticketChip($ticket))
            ->values()->all();
    }

    /**
     * What a ticket chip in the chat shows: its live status, who has it and what kind of ticket it is.
     *
     * @return array{reference: string, subject: string, status_icon: array, progress: string, type_icon: array|null, kind_label: string|null, priority_label: string, assignee: string|null, assignee_avatar: array|null, resolved_at: string|null, url: string}
     */
    public static function ticketChip(Ticket $ticket): array
    {
        $assignee = $ticket->assignee;

        return [
            'reference'       => $ticket->reference,
            'subject'         => $ticket->subject,
            'status_icon'     => TicketStatusEnum::stateIcon()[$ticket->status->value],
            'progress'        => match ($ticket->status) {
                TicketStatusEnum::RESOLVED => 'fixed',
                TicketStatusEnum::IN_PROGRESS, TicketStatusEnum::PENDING_DEPLOY => 'fixing',
                TicketStatusEnum::CANCELLED => 'closed',
                default => 'not_fixed',
            },
            'type_icon'       => $ticket->type?->icon(),
            'kind_label'      => $ticket->kind ? TicketKindEnum::labels()[$ticket->kind->value] : null,
            'priority_label'  => ChatPriorityEnum::labels()[$ticket->priority->value],
            'assignee'        => $assignee ? ($assignee->contact_name ?: $assignee->username) : null,
            'assignee_avatar' => $assignee?->image ? ['original' => GetImgProxyUrl::run($assignee->image->getImage()->resize(48, 48))] : null,
            'resolved_at'     => $ticket->resolved_at?->toIso8601String(),
            'url'             => route('grp.tickets.show', $ticket->reference),
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
            return $fromPayload === 'Aiku' ? 'Aura' : $fromPayload;
        }

        $actorType = is_object($this->actor_type) ? $this->actor_type->value : $this->actor_type;

        if ($actorType === 'ai') {
            return 'Aura';
        }

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
            ChatEventTypeEnum::MOVED_TO_COURIERS => __(':actor moved the chat to Couriers and added :domain to the courier list', ['actor' => $actor, 'domain' => $this->payload('domain')]),
            ChatEventTypeEnum::FORWARDED_BY_EMAIL => __(':actor forwarded the chat by email to :recipients', ['actor' => $actor, 'recipients' => implode(', ', (array) $this->payload('recipients', []))]),
            ChatEventTypeEnum::MOVED_TO_SHOP => __(':actor moved the chat from :from to :to', ['actor' => $actor, 'from' => $this->payload('from_shop_name'), 'to' => $this->payload('to_shop_name')]),
            ChatEventTypeEnum::INTERNAL_NOTE => __('Internal note by Aura, not visible to the customer'),
            ChatEventTypeEnum::TICKET => __(':actor raised a ticket', ['actor' => $this->payload('created_by_agent_name') ?? $actor]),
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
