<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat;

use App\Actions\Chat\ChatSession\GetChatCustomerProfile;
use App\Actions\CRM\Customer\UI\GetCustomerTimeline;
use App\Enums\CRM\Livechat\ChatTopicEnum;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Everything we hold on the person the agent is talking to, in one line: what they bought,
 * sent back and still owe, and every conversation they have had with us on any channel.
 * The agent is looking at a record we already keep, so the whole of it belongs beside the
 * thread rather than behind three tabs.
 */
class GetChatContactTimeline
{
    use AsObject;

    private const CHANNELS = [
        'website'  => ['title' => 'Website chat', 'icon' => 'fa-comments'],
        'email'    => ['title' => 'Email conversation', 'icon' => 'fa-envelope-open-text'],
        'whatsapp' => ['title' => 'WhatsApp conversation', 'icon' => 'fa-comment-dots'],
    ];

    public function handle(Customer $customer, ChatSession|MetaChatSession $current): array
    {
        $events = collect(GetCustomerTimeline::run($customer)['events'])
            ->concat($this->conversationEvents($customer, $current))
            ->sortByDesc(fn (array $event) => $event['datetime'] ? Carbon::parse($event['datetime'])->getTimestamp() : 0)
            ->values()
            ->all();

        return ['events' => $events];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function conversationEvents(Customer $customer, ChatSession|MetaChatSession $current): Collection
    {
        $topicLabels = ChatTopicEnum::labels();

        return GetChatCustomerProfile::make()
            ->conversationsWith($customer, $current)
            ->map(function (ChatSession|MetaChatSession $chatSession) use ($topicLabels) {
                $channel  = GetChatCustomerProfile::channelOf($chatSession);
                $summary  = Arr::get($chatSession->metadata ?? [], 'ai_summary.summary');
                $spokeAt  = $chatSession->last_visitor_message_at
                    ?? $chatSession->last_agent_message_at
                    ?? $chatSession->created_at;
                $spokeAt  = $spokeAt ? Carbon::parse($spokeAt) : null;

                return [
                    'id'        => "conversation_{$channel}_{$chatSession->id}",
                    'type'      => 'conversation',
                    'datetime'  => $spokeAt?->toIso8601String(),
                    'title'     => __(self::CHANNELS[$channel]['title']),
                    'subtitle'  => $topicLabels[$chatSession->topic] ?? null,
                    'comment'   => $summary,
                    'icon'      => ['fal', self::CHANNELS[$channel]['icon']],
                    'color'     => 'sky',
                    'metadata'  => [
                        'ulid'    => $chatSession->ulid,
                        'channel' => $channel,
                        'status'  => $chatSession->status?->value,
                        'topic'   => $topicLabels[$chatSession->topic] ?? null,
                        'summary' => $summary,
                        'outcome' => Arr::get($chatSession->metadata ?? [], 'ai_summary.status'),
                    ],
                ];
            });
    }
}
