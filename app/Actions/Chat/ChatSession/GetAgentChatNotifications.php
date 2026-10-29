<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\Chat\WithUnclaimedChatSessions;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAgentChatNotifications
{
    use AsAction;
    use WithChatAgentAuthorisation;
    use WithUnclaimedChatSessions;

    private ?string $channel = null;

    private array $visitorSenderTypes = [
        ChatSenderTypeEnum::GUEST->value,
        ChatSenderTypeEnum::USER->value,
    ];

    /**
     * The folders follow the shops picked on the rail, so their numbers do too: none picked
     * means every shop, which is how the folders read then. With a channel named the folders
     * hold that channel only, and so do their numbers.
     *
     * @param  array<int, int>  $shownShopIds
     */
    public function handle(ChatAgent $agent, array $shownShopIds = [], ?string $channel = null): array
    {
        $this->channel = $channel;

        $shopIds = collect($this->shopIdsWorkedBy($agent->user_id))
            ->when($shownShopIds !== [], fn ($ids) => $ids->intersect($shownShopIds)->values());

        // Not limited to the shops this agent works: the point of the unclaimed queue is that
        // somebody who does not work the shop is the one who ends up noticing.
        $unclaimed = $this->onChannel($this->unclaimedChatSessions())
            ->when($shownShopIds !== [], fn ($query) => $query->whereIn('shop_id', $shownShopIds))
            ->count()
            + ($this->countsWhatsapp() ? $this->unclaimedMetaChatSessions()
                ->when($shownShopIds !== [], fn ($query) => $query->whereIn('shop_id', $shownShopIds))
                ->count() : 0);

        $mailboxShopIds = $this->mailboxShopIds($agent, $shownShopIds, $shopIds);
        $carriers       = $mailboxShopIds->isEmpty() ? 0 : $this->carrierCount($mailboxShopIds);
        $colleagues     = $mailboxShopIds->isEmpty() ? 0 : $this->colleagueCount($mailboxShopIds);

        if ($shopIds->isEmpty()) {
            return ['team_unread' => [], 'unclaimed' => $unclaimed, 'spam' => $mailboxShopIds->isEmpty() ? 0 : $this->spamCount($mailboxShopIds), 'carriers' => $carriers, 'colleagues' => $colleagues, 'promised' => 0];
        }

        return [
            'team_unread' => $this->teamUnreadByShop($agent, $shopIds),
            'unclaimed'   => $unclaimed,
            'spam'        => $this->spamCount($shopIds),
            'carriers'    => $carriers,
            'colleagues'  => $colleagues,
            'promised'    => $this->promisedCount($shopIds),
        ];
    }

    /**
     * What the Spam folder holds, both channels together and over the shops this agent works,
     * which is how the folder itself is scoped: a number counted any wider would promise rows
     * the list then refuses to show.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $shopIds
     */
    /**
     * Courier conversations still open in the shops this agent works: what the Couriers folder
     * badge promises.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $shopIds
     */
    private function carrierCount($shopIds): int
    {
        return ChatSession::query()
            ->where('channel', ChatChannelEnum::EMAIL->value)
            ->where('is_carrier', true)
            ->where('is_spam', false)
            ->where('is_rubbish', false)
            ->where('status', '!=', ChatSessionStatusEnum::CLOSED)
            ->whereIn('shop_id', $shopIds)
            ->count();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $shopIds
     */
    private function colleagueCount($shopIds): int
    {
        return ChatSession::query()
            ->where('channel', ChatChannelEnum::EMAIL->value)
            ->where('is_colleague', true)
            ->where('is_spam', false)
            ->where('is_rubbish', false)
            ->where('status', '!=', ChatSessionStatusEnum::CLOSED)
            ->whereIn('shop_id', $shopIds)
            ->count();
    }

    /**
     * Told while we were closed that we would answer when we open, and not answered yet: what
     * the Promised capsule lists over the same shops.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $shopIds
     */
    private function promisedCount($shopIds): int
    {
        return $this->onChannel(ChatSession::query())
            ->tap(fn ($query) => GetChatReplyPromise::scopeWaiting($query))
            ->whereHas('messages')
            ->where('is_spam', false)
            ->where('is_rubbish', false)
            ->where('is_carrier', false)
            ->where('is_colleague', false)
            ->whereIn('shop_id', $shopIds)
            ->count()
            + ($this->countsWhatsapp() ? MetaChatSession::query()
                ->tap(fn ($query) => GetChatReplyPromise::scopeWaiting($query))
                ->where('is_spam', false)
                ->whereIn('shop_id', $shopIds)
                ->count() : 0);
    }

    private function spamCount($shopIds): int
    {
        return $this->onChannel(ChatSession::query())
            ->whereHas('messages')
            ->where('is_spam', true)
            ->where('is_rubbish', false)
            ->whereIn('shop_id', $shopIds)
            ->count()
            + ($this->countsWhatsapp() ? MetaChatSession::query()
                ->where('is_spam', true)
                ->whereIn('shop_id', $shopIds)
                ->count() : 0);
    }

    /**
     * Website chats and emails share a table, WhatsApp has its own: naming WhatsApp leaves
     * the shared table out altogether.
     *
     * @param  Builder<ChatSession>  $query
     * @return Builder<ChatSession>
     */
    private function onChannel(Builder $query): Builder
    {
        return match ($this->channel) {
            null        => $query,
            'whatsapp'  => $query->whereRaw('false'),
            'chat'      => $query->where('chat_sessions.channel', ChatChannelEnum::WEBSITE->value),
            default     => $query->where('chat_sessions.channel', $this->channel),
        };
    }

    /**
     * Couriers and Colleagues list every shop picked on the rail that the person may look at,
     * supervisors included, so their numbers are counted over exactly those shops.
     *
     * @param  array<int, int>  $shownShopIds
     * @param  \Illuminate\Support\Collection<int, int>  $workedShopIds
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function mailboxShopIds(ChatAgent $agent, array $shownShopIds, $workedShopIds)
    {
        if ($shownShopIds === [] || !$agent->user) {
            return $workedShopIds;
        }

        return Shop::with('fulfilment')
            ->whereIn('id', $shownShopIds)
            ->get()
            ->filter(fn (Shop $shop) => $this->userCanViewChatOnShop($agent->user, $shop))
            ->pluck('id')
            ->values();
    }

    private function countsWhatsapp(): bool
    {
        return in_array($this->channel, [null, 'whatsapp', 'chat'], true);
    }

    /**
     * Informational per-shop count of team chats (active or closed) that carry unread
     * customer messages and are handled by a teammate — so others can spot and take them
     * over. Keyed by shop_id so the UI can show the count for the selected inbox only.
     *
     * @return array<int, int>
     */
    private function teamUnreadByShop(ChatAgent $agent, $shopIds): array
    {
        return ChatSession::query()
            ->where('is_spam', false)
            ->whereIn('shop_id', $shopIds)
            ->whereIn('status', [
                ChatSessionStatusEnum::ACTIVE->value,
                ChatSessionStatusEnum::CLOSED->value,
            ])
            ->whereHas('assignments', function ($assignmentQuery) use ($agent) {
                $assignmentQuery->where('chat_agent_id', '!=', $agent->id)
                    ->whereIn('status', [
                        ChatAssignmentStatusEnum::ACTIVE->value,
                        ChatAssignmentStatusEnum::RESOLVED->value,
                    ]);
            })
            ->whereDoesntHave('assignments', function ($assignmentQuery) use ($agent) {
                $assignmentQuery->where('chat_agent_id', $agent->id)
                    ->where('status', ChatAssignmentStatusEnum::ACTIVE->value);
            })
            ->whereHas('messages', function ($messageQuery) {
                $messageQuery->where('is_read', false)
                    ->whereIn('sender_type', $this->visitorSenderTypes);
            })
            ->selectRaw('shop_id, count(*) as aggregate')
            ->groupBy('shop_id')
            ->pluck('aggregate', 'shop_id')
            ->toArray();
    }

    /**
     * The id in the URL is the caller's own: user ids are sequential, and an agent's queue names
     * the customers they are talking to, so another agent's is not theirs to read.
     */
    public function asController(ActionRequest $request, $userId): JsonResponse
    {
        abort_unless((int) $userId === $request->user()?->id, 403);

        $user = User::find($userId);

        if (!$user || !$user->chatAgent) {
            return response()->json([
                'success' => true,
                'message' => 'User is not a chat agent',
                'data'    => ['team_unread' => (object) [], 'unclaimed' => 0, 'spam' => 0, 'carriers' => 0],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Agent chat notifications retrieved successfully',
            'data'    => $this->handle(
                $user->chatAgent,
                array_map('intval', array_filter((array) $request->query('shop_ids', []), 'is_numeric')),
                in_array($request->query('channel'), [ChatChannelEnum::WEBSITE->value, ChatChannelEnum::EMAIL->value, 'whatsapp', 'chat'], true) ? $request->query('channel') : null
            ),
        ]);
    }
}
