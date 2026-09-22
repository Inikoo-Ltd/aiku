<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\Reports;

use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatMessage;
use App\Models\HumanResources\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetChatResponseMetrics
{
    use AsAction;

    /**
     * First reply times split into the two that matter: the one inside working hours,
     * which is what the team is answerable for, and the one outside, which measures
     * coverage rather than any person.
     *
     * @return Collection<int, array{agent_id: int|null, agent: string|null, shop: string, date: string, in_hours: bool, wait_minutes: float}>
     */
    public function handle(Carbon $from, Carbon $to, ?Shop $onlyShop = null): Collection
    {
        $firstReplies = ChatMessage::query()
            ->selectRaw('chat_messages.chat_session_id, chat_messages.sender_id as agent_id, min(chat_messages.created_at) as first_reply')
            ->where('chat_messages.sender_type', ChatSenderTypeEnum::AGENT->value)
            ->whereBetween('chat_messages.created_at', [$from, $to])
            ->groupBy('chat_messages.chat_session_id', 'chat_messages.sender_id');

        $rows = ChatMessage::query()
            ->selectRaw('r.agent_id, r.first_reply, min(chat_messages.created_at) as first_visitor, chat_sessions.shop_id')
            ->joinSub($firstReplies, 'r', 'r.chat_session_id', '=', 'chat_messages.chat_session_id')
            ->join('chat_sessions', 'chat_sessions.id', '=', 'chat_messages.chat_session_id')
            ->whereIn('chat_messages.sender_type', [ChatSenderTypeEnum::GUEST->value, ChatSenderTypeEnum::USER->value])
            ->when($onlyShop, fn ($query) => $query->where('chat_sessions.shop_id', $onlyShop->id))
            ->groupBy('r.agent_id', 'r.first_reply', 'chat_sessions.shop_id')
            ->havingRaw('min(chat_messages.created_at) < r.first_reply')
            ->get();

        $shops     = Shop::with('organisation', 'timezone')->findMany($rows->pluck('shop_id')->unique())->keyBy('id');
        $employees = $this->employeesByAgent($rows->pluck('agent_id')->filter()->unique());

        return $rows->map(function ($row) use ($shops, $employees) {
            $shop = $shops->get($row->shop_id);

            if (!$shop) {
                return null;
            }

            $askedAt = Carbon::parse($row->first_visitor);

            return [
                'agent_id'     => $row->agent_id,
                'agent'        => $employees->get($row->agent_id)?->contact_name,
                'shop'         => $shop->code,
                'date'         => $askedAt->copy()->setTimezone($shop->timezoneName())->toDateString(),
                'in_hours'     => IsWithinWorkingHours::run($shop, $askedAt, $employees->get($row->agent_id)),
                'wait_minutes' => round(Carbon::parse($row->first_reply)->diffInSeconds($askedAt) / 60, 1),
            ];
        })->filter()->values();
    }

    /**
     * @return Collection<int, Employee>
     */
    private function employeesByAgent(Collection $agentIds): Collection
    {
        return ChatAgent::with('user')->findMany($agentIds)
            ->mapWithKeys(fn (ChatAgent $agent) => [
                $agent->id => $agent->user?->employees()->first(),
            ])
            ->filter();
    }
}
