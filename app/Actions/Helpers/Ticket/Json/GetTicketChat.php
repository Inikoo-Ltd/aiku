<?php

/*
 * Author Louis Perez
 * Created on 21-09-2026-15h-22m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket\Json;

use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\Helpers\Ticket;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\ActionRequest;

/**
 * The conversation a ticket was raised from, to read on the ticket itself. Read only on purpose:
 * whoever is working the ticket needs the story, not another place to answer the customer from.
 */
class GetTicketChat extends OrgAction
{
    private const MESSAGE_LIMIT = 200;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(Ticket $ticket): array
    {
        $session = $ticket->source;

        if (!$session instanceof ChatSession && !$session instanceof MetaChatSession) {
            return ['session' => null, 'messages' => [], 'truncated' => false];
        }

        $total    = $session->messages()->withTrashed()->count();
        $messages = $session->messages()
            ->withTrashed()
            ->with('media')
            ->orderByDesc('created_at')
            ->limit(self::MESSAGE_LIMIT)
            ->get()
            ->sortBy('created_at')
            ->values();

        return [
            'session'   => $this->sessionData($session),
            'messages'  => $this->messageData($messages),
            'truncated' => $total > self::MESSAGE_LIMIT,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionData(ChatSession|MetaChatSession $session): array
    {
        $assignment = $session->assignments()->latest('id')->first();

        return [
            'ulid'       => $session->ulid,
            'status'     => $session->status instanceof \BackedEnum ? $session->status->value : $session->status,
            'contact'    => $session instanceof MetaChatSession
                ? ($session->customer?->name ?: ($session->phone_number ?: $session->guest_identifier))
                : ($session->webUser?->contact_name ?: $session->guest_identifier),
            'agent'      => $assignment?->chatAgent?->user?->contact_name,
            'started_at' => $session->created_at,
            'closed_at'  => $session->closed_at,
        ];
    }

    /**
     * A message taken back keeps its place in the story, without its words, the same way it reads
     * in chat itself.
     *
     * @return array<int, array<string, mixed>>
     */
    private function messageData(Collection $messages): array
    {
        return $messages->map(function ($message) {
            $senderType = $message->sender_type instanceof ChatSenderTypeEnum
                ? $message->sender_type->value
                : $message->sender_type;

            return [
                'id'          => $message->id,
                'at'          => $message->created_at,
                'sender_type' => $senderType,
                'sender'      => $message->sender_name,
                'text'        => $message->deleted_at ? null : $message->message_text,
                'is_redacted' => (bool) $message->deleted_at,
                'attachments' => $message->media->count(),
            ];
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(Ticket $ticket, ActionRequest $request): array
    {
        abort_unless($ticket->isVisibleTo($request->user()), 403);
        $this->initialisationFromGroup($ticket->group, $request);

        return $this->handle($ticket);
    }
}
