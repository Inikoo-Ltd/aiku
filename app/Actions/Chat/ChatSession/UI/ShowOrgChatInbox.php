<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\ChatSession\UI;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatIgnoreReasonEnum;
use App\Enums\CRM\Livechat\ChatPhoneCallContactTypeEnum;
use App\Enums\CRM\Livechat\ChatPhoneCallStatusEnum;
use App\Actions\Chat\ChatSession\GetChatSessions;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Http\Resources\CRM\Livechat\ChatSessionListResource;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatPhoneCall;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowOrgChatInbox extends OrgAction
{
    use WithChatAgentAuthorisation;

    use AsAction;
    use WithInertia;

    private ?ChatSession $selectedSession = null;

    private ?int $preselectShopId = null;

    private bool $supervising = false;

    public function authorize(ActionRequest $request): bool
    {
        $user = $request->user();

        if (isset($this->shop)) {
            return $this->userCanViewChatOnShop($user, $this->shop)
                || $user->authTo(["accounting.{$this->shop->organisation_id}.view"]);
        }

        return $this->userCanWorkChatOnOrganisation($user, $this->organisation)
            || $user->authTo([
                'accounting.'.$this->organisation->id.'.view',
                'org-supervisor.'.$this->organisation->id,
                'shops-view.'.$this->organisation->id,
            ]);
    }

    public function handle(Organisation $organisation): Organisation
    {
        return $organisation;
    }

    public function asController(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function inConversation(Organisation $organisation, ChatSession $chatSession, ActionRequest $request): Organisation
    {
        $this->selectedSession = $chatSession;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): Organisation
    {
        $this->preselectShopId = $shop->id;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($organisation);
    }

    public function supervision(Organisation $organisation, ActionRequest $request): Organisation
    {
        $this->supervising = true;

        return $this->asController($organisation, $request);
    }

    public function supervisionInConversation(Organisation $organisation, ChatSession $chatSession, ActionRequest $request): Organisation
    {
        $this->supervising = true;

        return $this->inConversation($organisation, $chatSession, $request);
    }

    public function supervisionInShop(Organisation $organisation, Shop $shop, ActionRequest $request): Organisation
    {
        $this->supervising = true;

        return $this->inShop($organisation, $shop, $request);
    }

    public function htmlResponse(Organisation $organisation, ActionRequest $request): Response|RedirectResponse
    {
        $user = $request->user();

        // This is the agent's own view, so it holds the shops they are on the rota for and no
        // others: overseeing a shop is not working it, and a manager's shops in here would put
        // conversations nobody routed to them in their queue.
        //
        // Chat does not belong to the organisation in the address bar either. The shops come
        // from the positions, across organisations, so the view is the same wherever it is
        // opened from and the scope in the url only decides which menu item is lit.
        $openShops = Shop::where('state', ShopStateEnum::OPEN)->orderBy('name')->get();

        if ($this->supervising) {
            // Overseeing, unlike working, does follow the address: whoever runs an organisation
            // holds every shop in the group, and all of them at once was a wall of forty empty
            // queues. The organisation or the shop in the url is what they came to look at.
            $inboxShops = $openShops
                ->filter(fn ($shop) => isset($this->shop) ? $shop->id === $this->shop->id : $shop->organisation_id === $organisation->id)
                ->filter(fn ($shop) => $this->userCanViewChatOnShop($user, $shop));
        } else {
            $inboxShops = $openShops->filter(fn ($shop) => $this->userCanWorkChatOnShop($user, $shop));

            if ($inboxShops->isEmpty()) {
                return $this->redirectToSupervision($organisation);
            }
        }

        $title = $this->supervising ? __('Supervision') : __('Customer Inbox');
        $icon  = $this->supervising ? 'fa-user-headset' : 'fa-inbox';

        return Inertia::render(
            'Org/Chat/Inbox',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', $icon],
                        'title' => $title,
                    ],
                ],
                'organisation' => [
                    'id'   => $organisation->id,
                    'slug' => $organisation->slug,
                    'name' => $organisation->name,
                ],
                'is_read_only'         => false,
                // Overseeing shows everybody's conversations on a shop, whoever holds them, and
                // the people holding them down the side.
                'supervisor'           => $this->supervising,
                'agents'               => $this->supervising ? $this->agentsCovering($inboxShops->pluck('id')->all()) : [],
                // Why a conversation was put aside, chosen from a list: a bulk job done dozens
                // of times an hour, and what has to be typed becomes blank within a day.
                'ignoreReasons'        => ChatIgnoreReasonEnum::options(),
                'inboxes'              => $this->mapInboxes($inboxShops),
                'selectedSessionUlid'  => $this->selectedSession ? (string) $this->selectedSession->ulid : null,
                'initialSession'       => $this->resolveSelectedSession(),
                'preselectShopId'      => $this->preselectShopId,
            ]
        );
    }

    private function redirectToSupervision(Organisation $organisation): RedirectResponse
    {
        if (isset($this->shop)) {
            return redirect()->route('grp.org.shops.show.chat.supervision', [$organisation->slug, $this->shop->slug]);
        }

        if ($this->selectedSession) {
            return redirect()->route('grp.org.chat.supervision.conversation', [$organisation->slug, $this->selectedSession->ulid]);
        }

        return redirect()->route('grp.org.chat.supervision', [$organisation->slug]);
    }

    /**
     * Who is on these shops, whether they are there, and how much they are holding. The load is
     * counted from the conversations rather than read off the agent's own counter, which drifts.
     *
     * @param  array<int, int>  $shopIds
     * @return array<int, array{id: int, name: string|null, presence: string, open: int, max: int, on_call: bool, on_call_since: string|null}>
     */
    private function agentsCovering(array $shopIds): array
    {
        // ponytail: website and email only; WhatsApp assignments live in their own table and
        // join this count when somebody asks why an agent busy on WhatsApp reads as idle.
        $open = DB::table('chat_assignments')
            ->join('chat_sessions', 'chat_sessions.id', '=', 'chat_assignments.chat_session_id')
            ->where('chat_assignments.status', ChatAssignmentStatusEnum::ACTIVE->value)
            ->where('chat_sessions.status', ChatSessionStatusEnum::ACTIVE->value)
            ->whereNull('chat_sessions.deleted_at')
            ->whereIn('chat_sessions.shop_id', $shopIds)
            ->groupBy('chat_assignments.chat_agent_id')
            ->selectRaw('chat_assignments.chat_agent_id, count(*) as open')
            ->pluck('open', 'chat_agent_id');

        // Somebody away from the keyboard on the telephone reads as idle otherwise, and the
        // conversations they are not answering look like neglect rather than a call in progress.
        $onCall = ChatPhoneCall::inProgress()
            ->orderBy('started_at')
            ->pluck('started_at', 'chat_agent_id');

        return ChatAgent::with('user')->get()
            ->filter(fn (ChatAgent $agent) => $agent->user?->status
                && array_intersect($shopIds, $this->workableShopIdsFor($agent->user)) !== [])
            ->map(fn (ChatAgent $agent) => [
                'id'            => $agent->id,
                'name'          => $agent->user->contact_name,
                'presence'      => $agent->presenceStatus()->value,
                'open'          => (int) ($open[$agent->id] ?? 0),
                'max'           => $agent->max_concurrent_chats,
                'on_call'       => $onCall->has($agent->id),
                'on_call_since' => $onCall->get($agent->id)?->toIso8601String(),
            ])
            ->sortBy([
                fn (array $a, array $b) => array_search($a['presence'], ['online', 'away', 'offline']) <=> array_search($b['presence'], ['online', 'away', 'offline']),
                ['open', 'desc'],
                ['name', 'asc'],
            ])
            ->values()
            ->all();
    }

    private function resolveSelectedSession(): ?array
    {
        if (!$this->selectedSession) {
            return null;
        }

        $this->selectedSession->loadMissing([
            'messages' => fn ($q) => $q->latest()->limit(1),
            'chatEvents' => fn ($q) => $q->where('event_type', ChatEventTypeEnum::GUEST_PROFILE)->latest()->limit(1),
            'webUser',
            'shop',
            'assignments.chatAgent.user',
        ]);

        return (new ChatSessionListResource($this->selectedSession))->resolve();
    }

    /**
     * @return array<int, array{id: int, name: string, slug: string, type: string|null, is_read_only: bool, channels: array<int, array{key: string, name: string, waiting: int, active: int, closed: int}>}>
     */
    private function mapInboxes(Collection $shops): array
    {
        $user = request()->user();

        $shopIds = $shops->pluck('id');
        $counts  = $this->waitingAndActiveCounts($shopIds);
        $phone   = $this->phoneCallCounts($shopIds);

        return $shops->map(function ($shop) use ($user, $counts, $phone) {
            $channel = function (string $key, string $name) use ($shop, $counts): array {
                $tally = function (string $kind) use ($shop, $counts, $key): array {
                    $row = $counts["{$shop->id}.{$key}.{$kind}"] ?? [];

                    return [
                        'waiting' => $row[ChatSessionStatusEnum::WAITING->value] ?? 0,
                        'active'  => $row[ChatSessionStatusEnum::ACTIVE->value] ?? 0,
                        'closed'  => $row[ChatSessionStatusEnum::CLOSED->value] ?? 0,
                        'mine'              => $row['mine'] ?? 0,
                        'colleagues'        => $row['colleagues'] ?? 0,
                        'closed_mine'       => $row['closed_mine'] ?? 0,
                        'closed_colleagues' => $row['closed_colleagues'] ?? 0,
                    ];
                };

                return [
                    'key'      => $key,
                    'name'     => $name,
                    'customer' => $tally('customer'),
                    'guest'    => $tally('guest'),
                ];
            };

            // Every shop shows the same three columns so the rail reads down as one table;
            // a channel nothing arrives on is held open and empty rather than dropped, which
            // used to shift the icons a row out of line from one shop to the next.
            $hasEmail    = isset($counts["{$shop->id}.email.customer"]) || isset($counts["{$shop->id}.email.guest"]);
            $hasWhatsapp = (bool) Arr::get($shop->settings, 'whatsapp.enabled', false);

            $channels = [
                $channel('website', __('Website')) + ['available' => true],
                $channel('email', __('Email')) + ['available' => $hasEmail],
                $channel('whatsapp', __('WhatsApp')) + ['available' => $hasWhatsapp],
            ];

            return [
                'id'       => $shop->id,
                'name'     => $shop->name,
                'slug'     => $shop->slug,
                'type'     => $shop->type?->value,
                'channels' => $channels,
                // The telephone is not a channel: nothing arrives on it, nothing waits on it and
                // no conversation is held in it. It rides beside the channels as its own column
                // so the rail says how much of the day went on the phone next to how much went
                // on chat, which is the comparison anybody looking at this rail is making.
                'phone'    => $phone[$shop->id] ?? ['in_progress' => 0, 'customer' => 0, 'guest' => 0],
                // Writing is decided shop by shop, never once for the page: the same person is
                // an agent on one shop and only oversees another.
                'is_read_only' => !$this->userCanActOnChatOnShop($user, $shop),
                // A shop with no mailbox connected can send nothing, so the inbox does not offer
                // to write from it. The customer still has to have an address of their own.
                'can_start_email' => filled(Arr::get($shop->settings, 'gmail.email')),
            ];
        })->values()->all();
    }

    /**
     * Telephone time per shop: what is happening now, and what has been finished today.
     *
     * A call in progress is deliberately not split between customer and guest. Who was on the
     * other end is chosen when the call is filed, so until then it belongs to neither row, and
     * putting it in one would make that row wrong for as long as the call lasts.
     *
     * @param  Collection<int, int>  $shopIds
     * @return array<int, array{in_progress: int, customer: int, guest: int}>
     */
    private function phoneCallCounts(Collection $shopIds): array
    {
        $counts = [];

        $running = ChatPhoneCall::whereIn('shop_id', $shopIds)
            ->inProgress()
            ->groupBy('shop_id')
            ->selectRaw('shop_id, count(*) as total')
            ->pluck('total', 'shop_id');

        $finished = ChatPhoneCall::whereIn('shop_id', $shopIds)
            ->where('status', ChatPhoneCallStatusEnum::COMPLETED)
            ->whereDate('ended_at', today())
            ->groupBy('shop_id', 'contact_type')
            ->get(['shop_id', 'contact_type', DB::raw('count(*) as total')]);

        foreach ($shopIds as $shopId) {
            $counts[$shopId] = [
                'in_progress' => (int) ($running[$shopId] ?? 0),
                'customer'    => 0,
                'guest'       => 0,
            ];
        }

        foreach ($finished as $row) {
            $kind = $row->contact_type instanceof ChatPhoneCallContactTypeEnum
                ? $row->contact_type->value
                : (string) $row->contact_type;

            if (isset($counts[$row->shop_id][$kind])) {
                $counts[$row->shop_id][$kind] += (int) $row->total;
            }
        }

        return $counts;
    }

    /**
     * How many conversations sit in each state, per shop and channel, so the rail says where
     * the work is rather than only what exists.
     *
     * @return array<string, array<string, int>> keyed "{shopId}.{channel}"
     */
    private function waitingAndActiveCounts(Collection $shopIds): array
    {
        $open = [
            ChatSessionStatusEnum::WAITING->value,
            ChatSessionStatusEnum::ACTIVE->value,
        ];

        $counts  = [];
        $agentId = (int) ChatAgent::where('user_id', request()->user()?->id)->value('id');

        $add = function (string $key, string $state, int $total) use (&$counts): void {
            $counts[$key][$state] = ($counts[$key][$state] ?? 0) + $total;
        };

        // A closed conversation passed between two people is in both their lists, so it counts
        // for both, as the lists themselves do.
        $addByHolder = function (string $key, object $row, bool $isClosed) use ($add): void {
            $prefix = $isClosed ? 'closed_' : '';

            if ($row->by_me) {
                $add($key, $prefix.'mine', (int) $row->total);
            }

            if ($row->by_colleague) {
                $add($key, $prefix.'colleagues', (int) $row->total);
            }
        };

        // The same conditions the list itself applies, or the capsule promises work that is not
        // there: most website sessions are a widget opened and abandoned without a word, and
        // counting those said 1073 waiting where the agent could see one.
        //
        // Split by who is on the other end as well: a customer waiting is not the same job as
        // a stranger, or a bounce daemon, and the two were adding up into one number.
        $rows = ChatSession::whereIn('shop_id', $shopIds)
            ->where(fn ($q) => $q->whereIn('status', $open)
                ->orWhere(fn ($c) => GetChatSessions::scopeClosedToday($c->where('status', ChatSessionStatusEnum::CLOSED->value))))
            ->whereHas('messages')
            ->where('is_spam', false)
            // Put aside keeps its status, so it has to be left out by name: the one active
            // email the rail promised was an ignored one the list rightly would not show.
            ->where('is_rubbish', false)
            ->groupBy('shop_id', 'channel', 'status', DB::raw('web_user_id is not null'), 'by_me', 'by_colleague')
            ->get([
                'shop_id',
                'channel',
                'status',
                DB::raw('web_user_id is not null as is_customer'),
                ...$this->holderColumns('chat_assignments', 'chat_session_id', 'chat_sessions', $agentId),
                DB::raw('count(*) as total'),
            ]);

        foreach ($rows as $row) {
            $channel = $row->channel instanceof ChatChannelEnum ? $row->channel->value : (string) $row->channel;
            $kind    = $row->is_customer ? 'customer' : 'guest';
            $key     = "{$row->shop_id}.{$channel}.{$kind}";

            $add($key, $row->status->value, (int) $row->total);

            if ($row->status !== ChatSessionStatusEnum::WAITING) {
                $addByHolder($key, $row, $row->status === ChatSessionStatusEnum::CLOSED);
            }
        }

        // WhatsApp has no empty-session problem: a conversation only exists once somebody wrote.
        // It carries the customer on the session itself rather than through a web user.
        $metaRows = MetaChatSession::whereIn('shop_id', $shopIds)
            ->where(fn ($q) => $q->whereIn('status', $open)
                ->orWhere(fn ($c) => GetChatSessions::scopeClosedToday($c->where('status', ChatSessionStatusEnum::CLOSED->value))))
            ->where('is_spam', false)
            ->groupBy('shop_id', 'status', DB::raw('customer_id is not null'), 'by_me', 'by_colleague')
            ->get([
                'shop_id',
                'status',
                DB::raw('customer_id is not null as is_customer'),
                ...$this->holderColumns('meta_chat_assignments', 'meta_chat_session_id', 'meta_chat_sessions', $agentId),
                DB::raw('count(*) as total'),
            ]);

        foreach ($metaRows as $row) {
            $kind   = $row->is_customer ? 'customer' : 'guest';
            $key    = "{$row->shop_id}.whatsapp.{$kind}";
            $isOpen = $row->status !== ChatSessionStatusEnum::CLOSED;

            // A WhatsApp conversation is never stored as waiting: it waits while nobody holds it,
            // which is how its list reads it too.
            $isHeld = $row->by_me || $row->by_colleague;
            $state  = $isOpen && !$isHeld ? ChatSessionStatusEnum::WAITING->value : $row->status->value;

            $add($key, $state, (int) $row->total);
            $addByHolder($key, $row, !$isOpen);
        }

        return $counts;
    }

    /**
     * Whether the person asking, and whether a colleague, holds a conversation: the active
     * assignment while it is open, the resolved one once it is closed.
     *
     * @return array<int, \Illuminate\Contracts\Database\Query\Expression>
     */
    private function holderColumns(string $assignments, string $foreignKey, string $sessions, int $agentId): array
    {
        $assigned = "select 1 from {$assignments} a where a.{$foreignKey} = {$sessions}.id and a.deleted_at is null"
            ." and a.status = case when {$sessions}.status = '".ChatSessionStatusEnum::CLOSED->value."'"
            ." then '".ChatAssignmentStatusEnum::RESOLVED->value."' else '".ChatAssignmentStatusEnum::ACTIVE->value."' end";

        return [
            DB::raw("exists ({$assigned} and a.chat_agent_id = {$agentId}) as by_me"),
            DB::raw("exists ({$assigned} and a.chat_agent_id is not null and a.chat_agent_id <> {$agentId}) as by_colleague"),
        ];
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => $this->supervising ? 'fal fa-user-headset' : 'fal fa-inbox',
                        'route' => [
                            'name'       => $this->supervising ? 'grp.org.chat.supervision' : 'grp.org.chat.inbox',
                            'parameters' => ['organisation' => $routeParameters['organisation'] ?? null],
                        ],
                        'label' => $this->supervising ? __('Supervision') : __('Customer Inbox'),
                    ],
                ],
            ]
        );
    }
}
