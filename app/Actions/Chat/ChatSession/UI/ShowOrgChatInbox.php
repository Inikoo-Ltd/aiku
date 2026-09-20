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
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Http\Resources\CRM\Livechat\ChatSessionListResource;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatAgent;
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
     * @return array<int, array{id: int, name: string|null, presence: string, open: int, max: int}>
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
            ->pluck(DB::raw('count(*)'), 'chat_assignments.chat_agent_id');

        return ChatAgent::with('user')->get()
            ->filter(fn (ChatAgent $agent) => $agent->user?->status
                && array_intersect($shopIds, $this->workableShopIdsFor($agent->user)) !== [])
            ->map(fn (ChatAgent $agent) => [
                'id'       => $agent->id,
                'name'     => $agent->user->contact_name,
                'presence' => $agent->presenceStatus()->value,
                'open'     => (int) ($open[$agent->id] ?? 0),
                'max'      => $agent->max_concurrent_chats,
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

        return $shops->map(function ($shop) use ($user, $counts) {
            $channel = function (string $key, string $name) use ($shop, $counts): array {
                $tally = function (string $kind) use ($shop, $counts, $key): array {
                    $row = $counts["{$shop->id}.{$key}.{$kind}"] ?? [];

                    return [
                        'waiting' => $row[ChatSessionStatusEnum::WAITING->value] ?? 0,
                        'active'  => $row[ChatSessionStatusEnum::ACTIVE->value] ?? 0,
                        'closed'  => $row[ChatSessionStatusEnum::CLOSED->value] ?? 0,
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
                // Writing is decided shop by shop, never once for the page: the same person is
                // an agent on one shop and only oversees another.
                'is_read_only' => !$this->userCanActOnChatOnShop($user, $shop),
            ];
        })->values()->all();
    }

    /**
     * How many conversations sit in each state, per shop and channel, so the rail says where
     * the work is rather than only what exists.
     *
     * @return array<string, array<string, int>> keyed "{shopId}.{channel}"
     */
    private function waitingAndActiveCounts(Collection $shopIds): array
    {
        $wanted = [
            ChatSessionStatusEnum::WAITING->value,
            ChatSessionStatusEnum::ACTIVE->value,
            ChatSessionStatusEnum::CLOSED->value,
        ];

        $counts = [];

        // The same conditions the list itself applies, or the capsule promises work that is not
        // there: most website sessions are a widget opened and abandoned without a word, and
        // counting those said 1073 waiting where the agent could see one.
        //
        // Split by who is on the other end as well: a customer waiting is not the same job as
        // a stranger, or a bounce daemon, and the two were adding up into one number.
        $rows = ChatSession::whereIn('shop_id', $shopIds)
            ->whereIn('status', $wanted)
            ->whereHas('messages')
            ->where('is_spam', false)
            ->groupBy('shop_id', 'channel', 'status', DB::raw('web_user_id is not null'))
            ->get([
                'shop_id',
                'channel',
                'status',
                DB::raw('web_user_id is not null as is_customer'),
                DB::raw('count(*) as total'),
            ]);

        foreach ($rows as $row) {
            $channel = $row->channel instanceof ChatChannelEnum ? $row->channel->value : (string) $row->channel;
            $kind    = $row->is_customer ? 'customer' : 'guest';
            $counts["{$row->shop_id}.{$channel}.{$kind}"][$row->status->value] = (int) $row->total;
        }

        // WhatsApp has no empty-session problem: a conversation only exists once somebody wrote.
        // It carries the customer on the session itself rather than through a web user.
        $metaRows = MetaChatSession::whereIn('shop_id', $shopIds)
            ->whereIn('status', $wanted)
            ->where('is_spam', false)
            ->groupBy('shop_id', 'status', DB::raw('customer_id is not null'))
            ->get([
                'shop_id',
                'status',
                DB::raw('customer_id is not null as is_customer'),
                DB::raw('count(*) as total'),
            ]);

        foreach ($metaRows as $row) {
            $kind = $row->is_customer ? 'customer' : 'guest';
            $counts["{$row->shop_id}.whatsapp.{$kind}"][$row->status->value] = (int) $row->total;
        }

        return $counts;
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
