<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 04:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\Chat\ChatSession\GetChatCustomerProfile;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatTopicEnum;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\Comms\EmailArchiveMessage;
use App\Models\CRM\Customer;
use App\Models\Helpers\Ticket;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Everything the customer and customer service said to each other, newest first: email threads
 * from the mailbox history, the conversations of the chat inbox (email, website chat, WhatsApp)
 * and the tickets raised for them. Each with its messages, so the customer's page shows the whole
 * story without opening the inbox.
 */
class GetCustomerCommunications
{
    use AsAction;

    private const int MESSAGES_PER_THREAD = 100;

    /**
     * The latest threads of every kind are picked first by their last message, and only those
     * are read in full, each with its latest messages.
     *
     * @return array{threads: array<int, array<string, mixed>>, has_more: bool}
     */
    public function handle(Customer $customer, int $limit = 30): array
    {
        $heads = $this->emailThreadHeads($customer, $limit)
            ->concat($this->conversationHeads($customer))
            ->concat($this->tickets($customer))
            ->sortByDesc('last_at')
            ->values();

        return [
            'threads'  => $heads->take($limit)->map(fn (array $head) => match ($head['kind']) {
                'email_archive' => $this->emailThread($customer, $head),
                'ticket'        => $head,
                default         => $this->conversation($customer, $head),
            })->all(),
            'has_more' => $heads->count() > $limit,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function emailThreadHeads(Customer $customer, int $limit): Collection
    {
        return EmailArchiveMessage::where('customer_id', $customer->id)
            ->groupBy('gmail_thread_id')
            ->selectRaw('gmail_thread_id, min(sent_at) as first_at, max(sent_at) as last_at')
            ->orderByDesc('last_at')
            ->limit($limit + 1)
            ->get()
            ->map(fn (EmailArchiveMessage $thread) => [
                'id'        => 'email_'.$thread->gmail_thread_id,
                'kind'      => 'email_archive',
                'thread_id' => $thread->gmail_thread_id,
                'first_at'  => Carbon::parse($thread->first_at)->toIso8601String(),
                'last_at'   => Carbon::parse($thread->last_at)->toIso8601String(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $head
     * @return array<string, mixed>
     */
    private function emailThread(Customer $customer, array $head): array
    {
        $messages = EmailArchiveMessage::where('customer_id', $customer->id)
            ->where('gmail_thread_id', $head['thread_id'])
            ->latest('sent_at')
            ->limit(self::MESSAGES_PER_THREAD)
            ->get(['subject', 'is_outbound', 'sent_at', 'text'])
            ->reverse();

        return Arr::except($head, 'thread_id') + [
            'title'    => $messages->first()?->subject ?: __('(no subject)'),
            'url'      => null,
            'messages' => $messages->map(fn (EmailArchiveMessage $message) => [
                'from_us' => $message->is_outbound,
                'at'      => $message->sent_at->toIso8601String(),
                'text'    => $message->text,
            ])->values()->all(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function conversationHeads(Customer $customer): Collection
    {
        return GetChatCustomerProfile::make()->conversationsWith($customer)
            ->map(fn (ChatSession|MetaChatSession $chatSession) => [
                'id'       => GetChatCustomerProfile::channelOf($chatSession).'_'.$chatSession->id,
                'kind'     => GetChatCustomerProfile::channelOf($chatSession),
                'session'  => $chatSession,
                'first_at' => $chatSession->created_at?->toIso8601String(),
                'last_at'  => collect([$chatSession->created_at, $chatSession->last_visitor_message_at, $chatSession->last_agent_message_at])
                    ->filter()
                    ->map(fn ($at) => Carbon::parse($at))
                    ->max()
                    ?->toIso8601String(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $head
     * @return array<string, mixed>
     */
    private function conversation(Customer $customer, array $head): array
    {
        $chatSession = $head['session'];
        $messages    = $chatSession->messages()
            ->whereIn('sender_type', [ChatSenderTypeEnum::USER, ChatSenderTypeEnum::GUEST, ChatSenderTypeEnum::AGENT])
            ->latest('created_at')
            ->latest('id')
            ->limit(self::MESSAGES_PER_THREAD)
            ->get(['sender_type', 'message_text', 'original_text', 'created_at'])
            ->reverse();

        return Arr::except($head, 'session') + [
            'title'    => Arr::get($chatSession->metadata ?? [], 'email_subject') ?? ChatTopicEnum::labels()[$chatSession->topic] ?? __('Conversation'),
            'summary'  => Arr::get($chatSession->metadata ?? [], 'ai_summary.summary'),
            'url'      => $head['kind'] === 'whatsapp'
                ? route('grp.org.chat.inbox', [$customer->organisation->slug, 'channel' => 'whatsapp', 'session' => $chatSession->ulid])
                : route('grp.org.chat.inbox.conversation', [$customer->organisation->slug, $chatSession->ulid]),
            'messages' => $messages->map(fn ($message) => [
                'from_us' => $message->sender_type === ChatSenderTypeEnum::AGENT,
                'at'      => $message->created_at?->toIso8601String(),
                'text'    => $message->original_text ?? $message->message_text,
            ])->values()->all(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function tickets(Customer $customer): Collection
    {
        return Ticket::where('customer_id', $customer->id)
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (Ticket $ticket) => [
                'id'       => 'ticket_'.$ticket->id,
                'kind'     => 'ticket',
                'title'    => $ticket->reference.' · '.$ticket->subject,
                'summary'  => $ticket->status?->value,
                'first_at' => $ticket->created_at?->toIso8601String(),
                'last_at'  => $ticket->updated_at?->toIso8601String(),
                'url'      => route('grp.tickets.show', $ticket->reference),
                'messages' => [],
            ]);
    }
}
