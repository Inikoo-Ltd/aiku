<?php

/*
 * Author: Yudhistira <artha@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\ChatSession\UI;

use App\Actions\Chat\ChatSession\GetChatSessions;
use App\Actions\Chat\ChatSession\WaitForCustomerReply;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrgChatSupervision extends OrgAction
{
    use WithChatAgentAuthorisation;

    public const int QUEUE_LIMIT = 100;

    public function authorize(ActionRequest $request): bool
    {
        $user = $request->user();

        return $this->userCanWorkChatOnOrganisation($user, $this->organisation)
            || $user->authTo([
                'accounting.'.$this->organisation->id.'.view',
                'org-supervisor.'.$this->organisation->id,
                'shops-view.'.$this->organisation->id,
            ]);
    }

    public function asController(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation($organisation, $request);

        return $organisation;
    }

    /**
     * @return array{shops: array<int, array{id: int, code: string, name: string, can_act: bool}>, stats: array<string, mixed>, queue: array<int, array<string, mixed>>, agents: array<int, array<string, mixed>>}
     */
    public function handle(Organisation $organisation, User $user): array
    {
        $shops = Shop::where('state', ShopStateEnum::OPEN)
            ->where('organisation_id', $organisation->id)
            ->orderBy('name')
            ->get()
            ->filter(fn (Shop $shop) => $this->userCanViewChatOnShop($user, $shop))
            ->values();

        $shopIds = $shops->pluck('id')->all();

        return [
            'shops'  => $shops->map(fn (Shop $shop) => [
                'id'      => $shop->id,
                'code'    => $shop->code,
                'name'    => $shop->name,
                'can_act' => $this->userCanActOnChatOnShop($user, $shop),
            ])->all(),
            'stats'  => $this->stats($shopIds, $shops),
            'queue'  => $this->queue($shopIds, $organisation),
            'agents' => ShowOrgChatInbox::make()->agentsCovering($shopIds),
        ];
    }

    /**
     * @param  array<int, int>  $shopIds
     */
    private function unansweredChats(array $shopIds): Builder
    {
        return ChatSession::query()
            ->whereIn('chat_sessions.shop_id', $shopIds)
            ->whereIn('chat_sessions.status', [ChatSessionStatusEnum::WAITING->value, ChatSessionStatusEnum::ACTIVE->value])
            ->where('chat_sessions.is_spam', false)
            ->where('chat_sessions.is_rubbish', false)
            ->where('chat_sessions.is_carrier', false)
            ->where('chat_sessions.is_colleague', false)
            ->where(fn ($query) => GetChatSessions::scopeReplyState($query, 'unanswered'));
    }

    /**
     * @param  array<int, int>  $shopIds
     */
    private function unansweredWhatsapps(array $shopIds): Builder
    {
        return MetaChatSession::query()
            ->whereIn('meta_chat_sessions.shop_id', $shopIds)
            ->where('meta_chat_sessions.status', '!=', ChatSessionStatusEnum::CLOSED->value)
            ->where('meta_chat_sessions.is_spam', false)
            ->whereNotNull('meta_chat_sessions.last_visitor_message_at')
            ->where(fn ($last) => $last->whereNull('meta_chat_sessions.last_agent_message_at')
                ->orWhereColumn('meta_chat_sessions.last_visitor_message_at', '>', 'meta_chat_sessions.last_agent_message_at'))
            ->whereRaw("meta_chat_sessions.metadata->'".WaitForCustomerReply::KEY."'->>'until' is null");
    }

    private function unassigned(Builder $query): Builder
    {
        return $query->whereDoesntHave('assignments', fn ($assignment) => $assignment->where('status', ChatAssignmentStatusEnum::ACTIVE->value));
    }

    private function oldestWaitingSince(Builder $query, string $sessionTable, string $messageTable, string $sessionKey): ?string
    {
        $oldest = $query->selectRaw('min('.GetChatSessions::waitingSinceSql($sessionTable, $messageTable, $sessionKey).') as oldest')->value('oldest');

        return $oldest ? Carbon::parse($oldest)->toIso8601String() : null;
    }

    /**
     * @param  array<int, int>  $shopIds
     * @param  Collection<int, Shop>  $shops
     * @return array<string, mixed>
     */
    private function stats(array $shopIds, Collection $shops): array
    {
        $websiteChats = fn () => $this->unansweredChats($shopIds)->where('chat_sessions.channel', ChatChannelEnum::WEBSITE->value);
        $emails       = fn () => $this->unansweredChats($shopIds)->where('chat_sessions.channel', ChatChannelEnum::EMAIL->value);

        $oldestChat = collect([
            $this->oldestWaitingSince($websiteChats(), 'chat_sessions', 'chat_messages', 'chat_session_id'),
            $this->oldestWaitingSince($this->unansweredWhatsapps($shopIds), 'meta_chat_sessions', 'meta_chat_messages', 'meta_chat_session_id'),
        ])->filter()->sort()->first();

        $unassignedByShop = $this->unassigned($this->unansweredChats($shopIds))
            ->groupBy('chat_sessions.shop_id')
            ->selectRaw('chat_sessions.shop_id, count(*) as total')
            ->pluck('total', 'shop_id');

        foreach ($this->unassigned($this->unansweredWhatsapps($shopIds))->groupBy('meta_chat_sessions.shop_id')->selectRaw('meta_chat_sessions.shop_id, count(*) as total')->pluck('total', 'shop_id') as $shopId => $total) {
            $unassignedByShop[$shopId] = ($unassignedByShop[$shopId] ?? 0) + $total;
        }

        $busiestShopId = $unassignedByShop->sortDesc()->keys()->first();

        $startOfDay = now()->startOfDay();

        return [
            'waiting_chats'          => $websiteChats()->count() + $this->unansweredWhatsapps($shopIds)->count(),
            'waiting_chats_since'    => $oldestChat,
            'unanswered_emails'      => $emails()->count(),
            'unanswered_emails_since' => $this->oldestWaitingSince($emails(), 'chat_sessions', 'chat_messages', 'chat_session_id'),
            'unassigned'             => (int) $unassignedByShop->sum(),
            'unassigned_busiest_shop' => $busiestShopId ? [
                'code'  => $shops->firstWhere('id', $busiestShopId)?->code,
                'count' => (int) $unassignedByShop[$busiestShopId],
            ] : null,
            'answered_today'         => ChatSession::whereIn('shop_id', $shopIds)->where('last_agent_message_at', '>=', $startOfDay)->count()
                + MetaChatSession::whereIn('shop_id', $shopIds)->where('last_agent_message_at', '>=', $startOfDay)->count(),
        ];
    }

    /**
     * The conversations nobody has answered yet, the longest waiting first, across every channel:
     * a supervisor reads this from the top to decide who picks up what.
     *
     * @param  array<int, int>  $shopIds
     * @return array<int, array<string, mixed>>
     */
    private function queue(array $shopIds, Organisation $organisation): array
    {
        $activeAssignment = fn ($assignment) => $assignment->where('status', ChatAssignmentStatusEnum::ACTIVE->value)->with('chatAgent.user');
        $lastVisitorLine  = fn ($message) => $message->whereIn('sender_type', [ChatSenderTypeEnum::GUEST->value, ChatSenderTypeEnum::USER->value])->latest()->limit(1);

        $chats = $this->unansweredChats($shopIds)
            ->select('chat_sessions.*')
            ->selectRaw(GetChatSessions::waitingSinceSql('chat_sessions', 'chat_messages', 'chat_session_id').' as waiting_since')
            ->with(['shop', 'webUser.customer', 'activeUserLanguage', 'userLanguage', 'assignments' => $activeAssignment, 'messages' => $lastVisitorLine])
            ->orderBy('waiting_since')
            ->limit(self::QUEUE_LIMIT)
            ->get()
            ->map(fn (ChatSession $session) => [
                'ulid'          => (string) $session->ulid,
                'channel'       => $session->channel?->value ?? ChatChannelEnum::WEBSITE->value,
                'contact_name'  => $session->webUser?->customer?->contact_name
                    ?? $session->webUser?->contact_name
                    ?? Arr::get($session->metadata ?? [], 'email_from_name')
                    ?? Arr::get($session->metadata ?? [], 'name')
                    ?? $session->guest_identifier
                    ?? __('Guest'),
                'is_customer'   => (bool) $session->web_user_id,
                'subject'       => Arr::get($session->metadata ?? [], 'email_subject')
                    ?? Str::limit((string) $session->messages->first()?->message_text, 80),
                'language'      => ($session->activeUserLanguage ?? $session->userLanguage)?->name,
                'waiting_since' => Carbon::parse($session->waiting_since)->toIso8601String(),
                'owner'         => $this->owner($session->assignments->first()?->chatAgent),
                'shop'          => $session->shop ? ['id' => $session->shop->id, 'code' => $session->shop->code] : null,
                'can_assign'    => true,
                'url'           => route('grp.org.chat.supervision.conversation', [$organisation->slug, $session->ulid]),
            ]);

        $whatsapps = $this->unansweredWhatsapps($shopIds)
            ->select('meta_chat_sessions.*')
            ->selectRaw(GetChatSessions::waitingSinceSql('meta_chat_sessions', 'meta_chat_messages', 'meta_chat_session_id').' as waiting_since')
            ->with(['shop', 'customer', 'activeUserLanguage', 'userLanguage', 'assignments' => $activeAssignment, 'messages' => $lastVisitorLine])
            ->orderBy('waiting_since')
            ->limit(self::QUEUE_LIMIT)
            ->get()
            ->map(fn (MetaChatSession $session) => [
                'ulid'          => (string) $session->ulid,
                'channel'       => 'whatsapp',
                'contact_name'  => $session->customer?->contact_name
                    ?? Arr::get($session->metadata ?? [], 'name')
                    ?? $session->phone_number
                    ?? __('Guest'),
                'is_customer'   => (bool) $session->customer_id,
                'subject'       => Str::limit((string) $session->messages->first()?->message_text, 80),
                'language'      => ($session->activeUserLanguage ?? $session->userLanguage)?->name,
                'waiting_since' => Carbon::parse($session->waiting_since)->toIso8601String(),
                'owner'         => $this->owner($session->assignments->first()?->chatAgent),
                'shop'          => $session->shop ? ['id' => $session->shop->id, 'code' => $session->shop->code] : null,
                'can_assign'    => false,
                'url'           => route('grp.org.chat.supervision', [$organisation->slug]).'?channel=whatsapp&session='.$session->ulid,
            ]);

        return $chats->concat($whatsapps)
            ->sortBy('waiting_since')
            ->take(self::QUEUE_LIMIT)
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string|null}|null
     */
    private function owner(?ChatAgent $agent): ?array
    {
        return $agent ? ['id' => $agent->id, 'name' => $agent->user?->contact_name] : null;
    }

    public function htmlResponse(Organisation $organisation, ActionRequest $request): Response
    {
        $title = __('Supervision');

        return Inertia::render(
            'Org/Chat/Supervision',
            [
                'breadcrumbs'  => $this->getBreadcrumbs($organisation),
                'title'        => $title,
                'pageHead'     => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-user-headset'],
                        'title' => $title,
                    ],
                ],
                'organisation' => [
                    'id'   => $organisation->id,
                    'slug' => $organisation->slug,
                ],
                ...$this->handle($organisation, $request->user()),
            ]
        );
    }

    public function getBreadcrumbs(Organisation $organisation): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-user-headset',
                        'route' => [
                            'name'       => 'grp.org.chat.supervision.dashboard',
                            'parameters' => ['organisation' => $organisation->slug],
                        ],
                        'label' => __('Supervision'),
                    ],
                ],
            ]
        );
    }
}
