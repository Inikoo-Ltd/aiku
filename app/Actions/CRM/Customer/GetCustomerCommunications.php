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
     * @return array{threads: array<int, array<string, mixed>>, has_more: bool}
     */
    public function handle(Customer $customer, int $limit = 30): array
    {
        $threads = $this->emailThreads($customer)
            ->concat($this->conversations($customer))
            ->concat($this->tickets($customer))
            ->sortByDesc('last_at')
            ->values();

        return ['threads' => $threads->take($limit)->all(), 'has_more' => $threads->count() > $limit];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function emailThreads(Customer $customer): Collection
    {
        return EmailArchiveMessage::where('customer_id', $customer->id)
            ->orderBy('sent_at')
            ->get()
            ->groupBy('gmail_thread_id')
            ->map(fn (Collection $messages, string $threadId) => [
                'id'       => 'email_'.$threadId,
                'kind'     => 'email_archive',
                'title'    => $messages->first()->subject ?: __('(no subject)'),
                'first_at' => $messages->first()->sent_at->toIso8601String(),
                'last_at'  => $messages->last()->sent_at->toIso8601String(),
                'url'      => null,
                'messages' => $messages->take(self::MESSAGES_PER_THREAD)->map(fn (EmailArchiveMessage $message) => [
                    'from_us' => $message->is_outbound,
                    'at'      => $message->sent_at->toIso8601String(),
                    'text'    => $message->text,
                ])->values()->all(),
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function conversations(Customer $customer): Collection
    {
        $topics = ChatTopicEnum::labels();

        return GetChatCustomerProfile::make()->conversationsWith($customer)
            ->map(function (ChatSession|MetaChatSession $chatSession) use ($topics, $customer) {
                $channel  = GetChatCustomerProfile::channelOf($chatSession);
                $messages = $chatSession->messages()
                    ->whereIn('sender_type', [ChatSenderTypeEnum::USER, ChatSenderTypeEnum::GUEST, ChatSenderTypeEnum::AGENT])
                    ->orderBy('created_at')
                    ->limit(self::MESSAGES_PER_THREAD)
                    ->get(['sender_type', 'message_text', 'original_text', 'created_at']);

                return [
                    'id'       => $channel.'_'.$chatSession->id,
                    'kind'     => $channel,
                    'title'    => Arr::get($chatSession->metadata ?? [], 'email_subject') ?? $topics[$chatSession->topic] ?? __('Conversation'),
                    'summary'  => Arr::get($chatSession->metadata ?? [], 'ai_summary.summary'),
                    'first_at' => $chatSession->created_at?->toIso8601String(),
                    'last_at'  => ($messages->last()?->created_at ?? $chatSession->created_at)?->toIso8601String(),
                    'url'      => $channel === 'whatsapp'
                        ? route('grp.org.chat.inbox', [$customer->organisation->slug, 'channel' => 'whatsapp', 'session' => $chatSession->ulid])
                        : route('grp.org.chat.inbox.conversation', [$customer->organisation->slug, $chatSession->ulid]),
                    'messages' => $messages->map(fn ($message) => [
                        'from_us' => $message->sender_type === ChatSenderTypeEnum::AGENT,
                        'at'      => $message->created_at?->toIso8601String(),
                        'text'    => $message->original_text ?? $message->message_text,
                    ])->values()->all(),
                ];
            });
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
