<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 00:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\UI;

use App\Actions\Chat\ChatSession\GetChatAutoSendGate;
use App\Actions\Chat\ChatSession\GetChatClaimDetails;
use App\Actions\Chat\ChatSession\SendChatAiAnswer;
use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Enums\CRM\Livechat\ChatAiDraftStatusEnum;
use App\Enums\CRM\Livechat\ChatAutomationKindEnum;
use App\Enums\CRM\Livechat\ChatNoiseVerdictEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatTopicEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Chat\ChatAiDraft;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Everything the chat did on its own, in every channel, in three tabs. A dashboard of the last
 * 30 days. What reached customers: each fixed message it sent and each reply the AI drafted,
 * with what became of it. And the noise checks that decided whether a stranger reached the
 * queue, with the verdict and why. The lists are kept apart because the checks outnumber what
 * was sent a hundred to one, and the few messages that matter were lost among them. Nothing
 * automatic ever happens out of sight.
 */
class ShowGroupChatAutomation extends OrgAction
{
    use WithInertia;

    public const string DASHBOARD = 'dashboard';
    public const string SENT = 'sent';
    public const string NOISE_CHECKS = 'noise_checks';

    private string $bucket = self::DASHBOARD;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasGroupAccess();
    }

    public function asController(ActionRequest $request): ?LengthAwarePaginator
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->bucket === self::DASHBOARD ? null : $this->handle($this->group);
    }

    public function inSent(ActionRequest $request): ?LengthAwarePaginator
    {
        $this->bucket = self::SENT;

        return $this->asController($request);
    }

    public function inNoiseChecks(ActionRequest $request): ?LengthAwarePaginator
    {
        $this->bucket = self::NOISE_CHECKS;

        return $this->asController($request);
    }

    public function handle(Group $group, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($where) use ($value) {
                $where->where('automation.text', 'ilike', '%'.$value.'%')
                    ->orWhere('automation.contact', 'ilike', '%'.$value.'%');
            });
        });

        $query = QueryBuilder::for(ChatMessage::withoutGlobalScopes()->fromSub($this->activity($group, $this->bucket), 'automation'));

        foreach ($this->getElementGroups($group) as $key => $elementGroup) {
            $query->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $query
            ->defaultSort('-at')
            ->allowedSorts(['at', 'kind', 'shop_name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * One row per thing done, whatever table it lives in. Website chat and email share the chat
     * tables; WhatsApp has its own, and its older messages are known by the key they were sent
     * under rather than a common marker.
     */
    private function activity(Group $group, string $bucket): \Illuminate\Database\Query\Builder
    {
        $shops = fn ($query, string $table) => $query
            ->join('shops', 'shops.id', '=', $table.'.shop_id')
            ->join('organisations', 'organisations.id', '=', 'shops.organisation_id')
            ->where('shops.group_id', $group->id);

        $common = fn (string $contact) => [
            'shops.name as shop_name',
            'organisations.slug as organisation_slug',
            DB::raw($contact.' as contact'),
        ];

        $sent = DB::table('chat_messages')
            ->join('chat_sessions', 'chat_sessions.id', '=', 'chat_messages.chat_session_id')
            ->where('chat_messages.sender_type', 'system')
            ->whereNull('chat_messages.deleted_at')
            ->whereRaw("chat_messages.metadata->>'automated' is not null")
            ->whereRaw("chat_messages.metadata->>'automated' <> ?", [SendChatAiAnswer::MESSAGE_MARKER])
            ->tap(fn ($query) => $shops($query, 'chat_sessions'))
            ->select([
                DB::raw("chat_messages.metadata->>'automated' as kind"),
                'chat_messages.created_at as at',
                DB::raw('null::integer as draft_id'),
                'chat_sessions.id as session_id',
                'chat_sessions.channel as channel',
                'chat_sessions.ulid as session_ulid',
                'chat_messages.message_text as text',
                DB::raw('null::varchar as verdict'),
                DB::raw('null::smallint as confidence'),
                DB::raw('null::varchar as source'),
                DB::raw('false as put_aside'),
                DB::raw('false as reversed'),
                ...$common("coalesce(chat_sessions.metadata->>'name', chat_sessions.metadata->>'email_from')"),
            ]);

        $sentOnWhatsapp = DB::table('meta_chat_messages')
            ->join('meta_chat_sessions', 'meta_chat_sessions.id', '=', 'meta_chat_messages.meta_chat_session_id')
            ->where('meta_chat_messages.sender_type', 'system')
            ->whereNull('meta_chat_messages.deleted_at')
            ->whereRaw("coalesce(meta_chat_messages.metadata->>'claim_details_asked_at', meta_chat_messages.metadata->>'out_of_hours_replied_at', meta_chat_messages.metadata->>'asked_if_customer', meta_chat_messages.metadata->>'greeted_at', meta_chat_messages.metadata->>'greeting') is not null")
            ->tap(fn ($query) => $shops($query, 'meta_chat_sessions'))
            ->select([
                DB::raw("case
                    when meta_chat_messages.metadata->>'claim_details_asked_at' is not null then 'claim_details'
                    when meta_chat_messages.metadata->>'out_of_hours_replied_at' is not null then 'out_of_hours'
                    when meta_chat_messages.metadata->>'asked_if_customer' is not null then 'asked_if_customer'
                    else 'greeting' end as kind"),
                'meta_chat_messages.created_at as at',
                DB::raw('null::integer as draft_id'),
                'meta_chat_sessions.id as session_id',
                DB::raw("'whatsapp' as channel"),
                'meta_chat_sessions.ulid as session_ulid',
                'meta_chat_messages.message_text as text',
                DB::raw('null::varchar as verdict'),
                DB::raw('null::smallint as confidence'),
                DB::raw('null::varchar as source'),
                DB::raw('false as put_aside'),
                DB::raw('false as reversed'),
                ...$common('meta_chat_sessions.phone_number'),
            ]);

        $checked = DB::table('chat_sessions')
            ->whereNotNull('chat_sessions.noise_verdict')
            ->tap(fn ($query) => $shops($query, 'chat_sessions'))
            ->select([
                DB::raw("'noise_check' as kind"),
                DB::raw('coalesce(chat_sessions.noise_checked_at, chat_sessions.updated_at) as at'),
                DB::raw('null::integer as draft_id'),
                'chat_sessions.id as session_id',
                'chat_sessions.channel as channel',
                'chat_sessions.ulid as session_ulid',
                'chat_sessions.noise_note as text',
                'chat_sessions.noise_verdict as verdict',
                'chat_sessions.noise_confidence as confidence',
                'chat_sessions.noise_source as source',
                DB::raw('((chat_sessions.is_spam and chat_sessions.spammed_by_agent_id is null) or (coalesce(chat_sessions.is_rubbish, false) and chat_sessions.rubbished_by_agent_id is null)) as put_aside'),
                DB::raw('chat_sessions.noise_reversed_at is not null as reversed'),
                ...$common("coalesce(chat_sessions.metadata->>'name', chat_sessions.metadata->>'email_from')"),
            ]);

        $checkedOnWhatsapp = DB::table('meta_chat_sessions')
            ->whereNotNull('meta_chat_sessions.noise_verdict')
            ->tap(fn ($query) => $shops($query, 'meta_chat_sessions'))
            ->select([
                DB::raw("'noise_check' as kind"),
                DB::raw('coalesce(meta_chat_sessions.noise_checked_at, meta_chat_sessions.updated_at) as at'),
                DB::raw('null::integer as draft_id'),
                'meta_chat_sessions.id as session_id',
                DB::raw("'whatsapp' as channel"),
                'meta_chat_sessions.ulid as session_ulid',
                'meta_chat_sessions.noise_note as text',
                'meta_chat_sessions.noise_verdict as verdict',
                'meta_chat_sessions.noise_confidence as confidence',
                'meta_chat_sessions.noise_source as source',
                DB::raw('(meta_chat_sessions.is_spam and meta_chat_sessions.spammed_by_agent_id is null) as put_aside'),
                DB::raw('meta_chat_sessions.noise_reversed_at is not null as reversed'),
                ...$common('meta_chat_sessions.phone_number'),
            ]);

        $drafted = DB::table('chat_ai_drafts')
            ->leftJoin('chat_sessions', 'chat_sessions.id', '=', 'chat_ai_drafts.chat_session_id')
            ->leftJoin('meta_chat_sessions', 'meta_chat_sessions.id', '=', 'chat_ai_drafts.meta_chat_session_id')
            ->tap(fn ($query) => $shops($query, 'chat_ai_drafts'))
            ->select([
                DB::raw("'ai_draft' as kind"),
                'chat_ai_drafts.created_at as at',
                'chat_ai_drafts.id as draft_id',
                DB::raw('coalesce(chat_ai_drafts.chat_session_id, chat_ai_drafts.meta_chat_session_id) as session_id'),
                DB::raw("case when chat_ai_drafts.meta_chat_session_id is not null then 'whatsapp' else chat_sessions.channel end as channel"),
                DB::raw('coalesce(chat_sessions.ulid, meta_chat_sessions.ulid) as session_ulid'),
                'chat_ai_drafts.text as text',
                'chat_ai_drafts.status as verdict',
                DB::raw('null::smallint as confidence'),
                'chat_ai_drafts.topic as source',
                DB::raw('false as put_aside'),
                DB::raw('chat_ai_drafts.flagged_wrong_at is not null as reversed'),
                ...$common("coalesce(chat_sessions.metadata->>'name', chat_sessions.metadata->>'email_from', meta_chat_sessions.phone_number)"),
            ]);

        return $bucket === self::NOISE_CHECKS
            ? $checked->unionAll($checkedOnWhatsapp)
            : $sent->unionAll($sentOnWhatsapp)->unionAll($drafted);
    }

    private function getElementGroups(Group $group): array
    {
        if ($this->bucket === self::NOISE_CHECKS) {
            $counts = DB::query()->fromSub($this->activity($group, self::NOISE_CHECKS), 'automation')
                ->selectRaw('verdict, count(*) as total')
                ->groupBy('verdict')
                ->pluck('total', 'verdict');

            $elements = [];
            foreach (ChatNoiseVerdictEnum::cases() as $case) {
                $elements[$case->value] = [$case->label(), (int) ($counts[$case->value] ?? 0)];
            }

            return [
                'verdict' => [
                    'label'    => __('Verdict'),
                    'elements' => $elements,
                    'engine'   => fn ($query, $elements) => $query->whereIn('automation.verdict', $elements),
                ],
            ];
        }

        $counts = DB::query()->fromSub($this->activity($group, self::SENT), 'automation')
            ->selectRaw('kind, count(*) as total')
            ->groupBy('kind')
            ->pluck('total', 'kind');

        $elements = [];
        foreach (ChatAutomationKindEnum::cases() as $case) {
            if ($case !== ChatAutomationKindEnum::NOISE_CHECK) {
                $elements[$case->value] = [$case->label(), (int) ($counts[$case->value] ?? 0)];
            }
        }

        return [
            'kind' => [
                'label'    => __('What'),
                'elements' => $elements,
                'engine'   => fn ($query, $elements) => $query->whereIn('automation.kind', $elements),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function subNavigation(Group $group): array
    {
        $tab = fn (string $label, string $routeName, string $bucket) => [
            'label'  => $label,
            'root'   => $routeName,
            'route'  => ['name' => $routeName, 'parameters' => []],
            'number' => DB::query()->fromSub($this->activity($group, $bucket), 'automation')->count(),
        ];

        return [
            [
                'label' => __('Dashboard'),
                'root'  => 'grp.chat.ai.dashboard',
                'route' => ['name' => 'grp.chat.ai.dashboard', 'parameters' => []],
            ],
            $tab(__('Sent to customers'), 'grp.chat.ai.sent', self::SENT),
            $tab(__('Noise checks'), 'grp.chat.ai.noise_checks', self::NOISE_CHECKS),
        ];
    }

    public function tableStructure(Group $group, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($group, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($group) as $key => $elementGroup) {
                $table->elementGroup(key: $key, label: $elementGroup['label'], elements: $elementGroup['elements']);
            }

            $table
                ->withGlobalSearch()
                ->column(key: 'at', label: __('When'), canBeHidden: false, sortable: true)
                ->column(key: 'kind', label: __('What'), canBeHidden: false, sortable: true)
                ->column(key: 'shop_name', label: __('Shop'), sortable: true)
                ->column(key: 'contact', label: __('Customer'))
                ->column(key: 'text', label: $this->bucket === self::NOISE_CHECKS ? __('Verdict and why') : __('What was sent or drafted'), canBeHidden: false)
                ->defaultSort('-at');
        };
    }

    public function htmlResponse(?LengthAwarePaginator $activity, ActionRequest $request): Response
    {
        if (!$activity) {
            return Inertia::render('Chat/ChatAutomationDashboard', [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('AI assist'),
                'pageHead'    => [
                    'title'         => __('AI dashboard'),
                    'icon'          => ['title' => __('AI assist'), 'icon' => ['fal', 'fa-robot']],
                    'subNavigation' => $this->subNavigation($this->group),
                ],
                'dashboard'  => $this->dashboard($this->group),
                'draftStats' => $this->draftStats($this->group),
                'autoSend'   => $this->autoSend($this->group),
            ]);
        }

        $activity->getCollection()->transform(function ($row) {
            $kind    = ChatAutomationKindEnum::tryFrom((string) $row->kind);
            $verdict = ChatNoiseVerdictEnum::tryFrom((string) $row->verdict);
            $claim   = $kind === ChatAutomationKindEnum::CLAIM_DETAILS ? $this->claimSoFar($row) : null;
            $draft   = $kind === ChatAutomationKindEnum::AI_DRAFT ? ChatAiDraftStatusEnum::tryFrom((string) $row->verdict) : null;

            return [
                'at'            => $row->at,
                'kind'          => $row->kind,
                'kind_label'    => $kind?->label() ?? $row->kind,
                'sends_message' => (bool) $kind?->sendsMessage(),
                'channel'       => $row->channel,
                'shop_name'     => $row->shop_name,
                'contact'       => $row->contact,
                'text'          => $row->text,
                'verdict'       => $row->verdict,
                'verdict_label' => $draft ? $draft->label() : $verdict?->label(),
                'draft_status'  => $draft?->value,
                'topic_label'   => $draft ? ChatTopicEnum::tryFrom((string) $row->source)?->label() : null,
                'is_noise'      => (bool) $verdict?->isNoise(),
                'confidence'    => $row->confidence,
                'source'        => $row->source,
                'put_aside'     => (bool) $row->put_aside,
                'reversed'      => (bool) $row->reversed,
                'claim'         => $claim,
                'draft_id'      => $row->draft_id,
                'url'           => match (true) {
                    !$row->session_ulid         => route('grp.org.chat.inbox', [$row->organisation_slug]),
                    $row->channel === 'whatsapp' => route('grp.org.chat.inbox', [$row->organisation_slug, 'channel' => 'whatsapp', 'session' => trim($row->session_ulid)]),
                    default                     => route('grp.org.chat.inbox.conversation', [$row->organisation_slug, trim($row->session_ulid)]),
                },
            ];
        });

        return Inertia::render(
            'Chat/ChatAutomation',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('AI assist'),
                'pageHead'    => [
                    'title' => $this->bucket === self::NOISE_CHECKS ? __('Noise checks') : __('Sent to customers'),
                    'icon'  => [
                        'title' => __('AI assist'),
                        'icon'  => ['fal', 'fa-robot'],
                    ],
                    'subNavigation' => $this->subNavigation($this->group),
                ],
                'data' => JsonResource::collection($activity),
            ]
        )->table($this->tableStructure($this->group));
    }

    /**
     * What the customer has sent in this wait, before and after being asked, looked up when the
     * page is read, so the row says what is still missing when the agent gets to it.
     *
     * @return array{order_reference: ?string, photos: int}|null
     */
    private function claimSoFar(object $row): ?array
    {
        $session = $row->channel === 'whatsapp' ? MetaChatSession::find($row->session_id) : ChatSession::find($row->session_id);

        if (!$session) {
            return null;
        }

        $waitStarted = $session->messages()
            ->where('sender_type', ChatSenderTypeEnum::AGENT)
            ->where('created_at', '<', $row->at)
            ->max('created_at');

        $details = GetChatClaimDetails::run($session, $waitStarted ? Carbon::parse($waitStarted) : null);

        return ['order_reference' => $details['order_reference'], 'photos' => $details['photos']];
    }

    /**
     * How the drafts decided in the last 30 days were used: the number that says whether they
     * can ever be trusted to go out on their own.
     *
     * @return array{decided: int, used: int, edited: int, discarded: int, superseded: int, pending: int, auto_sent: int}
     */
    private function draftStats(Group $group): array
    {
        $counts = ChatAiDraft::where('group_id', $group->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $count = fn (ChatAiDraftStatusEnum $status) => (int) ($counts[$status->value] ?? 0);

        return [
            'decided'    => $count(ChatAiDraftStatusEnum::USED) + $count(ChatAiDraftStatusEnum::EDITED) + $count(ChatAiDraftStatusEnum::DISCARDED) + $count(ChatAiDraftStatusEnum::SUPERSEDED),
            'used'       => $count(ChatAiDraftStatusEnum::USED),
            'edited'     => $count(ChatAiDraftStatusEnum::EDITED),
            'discarded'  => $count(ChatAiDraftStatusEnum::DISCARDED),
            'superseded' => $count(ChatAiDraftStatusEnum::SUPERSEDED),
            'pending'    => $count(ChatAiDraftStatusEnum::PENDING),
            'auto_sent'  => $count(ChatAiDraftStatusEnum::AUTO_SENT),
        ];
    }

    /**
     * Where drafts may go out without a person, and how far each shop and topic is from it.
     *
     * @return array{enabled: bool, min_decided: int, min_used_share: float, gates: array<int, array<string, mixed>>}
     */
    private function autoSend(Group $group): array
    {
        $pairs = ChatAiDraft::where('group_id', $group->id)
            ->where('created_at', '>=', now()->subDays((int) config('chat.ai_auto_send.window_days')))
            ->select('shop_id', 'topic')
            ->distinct()
            ->with('shop:id,name')
            ->get();

        return [
            'enabled'        => (bool) config('chat.ai_auto_send.enabled'),
            'min_decided'    => (int) config('chat.ai_auto_send.min_decided'),
            'min_used_share' => (float) config('chat.ai_auto_send.min_used_share'),
            'gates'          => $pairs->map(fn (ChatAiDraft $pair) => [
                'shop'  => $pair->shop?->name,
                'topic' => $pair->topic->label(),
            ] + GetChatAutoSendGate::run($pair->shop, $pair->topic))->values()->all(),
        ];
    }

    /**
     * The last 30 days at a glance: what was sent, drafted and checked each day, and how the
     * noise checks came out.
     *
     * @return array<string, mixed>
     */
    private function dashboard(Group $group): array
    {
        $since = now()->subDays(29)->startOfDay();

        $perDay = fn (string $bucket, string $column) => DB::query()
            ->fromSub($this->activity($group, $bucket), 'automation')
            ->where('at', '>=', $since)
            ->selectRaw("to_char(at, 'YYYY-MM-DD') as day, $column as series, count(*) as total")
            ->groupByRaw("1, 2")
            ->get();

        $sent   = $perDay(self::SENT, 'kind');
        $checks = $perDay(self::NOISE_CHECKS, "case when verdict = 'genuine' then 'genuine' else 'noise' end");

        $days = collect(range(0, 29))->map(fn (int $offset) => $since->copy()->addDays($offset)->format('Y-m-d'));
        $sum  = fn ($rows, callable $match, string $day) => (int) $rows->where('day', $day)->filter($match)->sum('total');

        $daily = $days->map(fn (string $day) => [
            'date'          => $day,
            'sent'          => $sum($sent, fn ($row) => $row->series !== ChatAutomationKindEnum::AI_DRAFT->value, $day),
            'drafts'        => $sum($sent, fn ($row) => $row->series === ChatAutomationKindEnum::AI_DRAFT->value, $day),
            'noise'         => $sum($checks, fn ($row) => $row->series === 'noise', $day),
            'genuine'       => $sum($checks, fn ($row) => $row->series === 'genuine', $day),
        ])->values()->all();

        $byKind = $sent->groupBy('series')->map(fn ($rows) => (int) $rows->sum('total'));

        $verdicts = DB::query()
            ->fromSub($this->activity($group, self::NOISE_CHECKS), 'automation')
            ->where('at', '>=', $since)
            ->selectRaw('verdict, count(*) as total, count(*) filter (where put_aside) as put_aside, count(*) filter (where reversed) as overruled')
            ->groupBy('verdict')
            ->get();

        return [
            'daily'   => $daily,
            'by_kind' => collect(ChatAutomationKindEnum::cases())
                ->reject(fn (ChatAutomationKindEnum $kind) => $kind === ChatAutomationKindEnum::NOISE_CHECK)
                ->map(fn (ChatAutomationKindEnum $kind) => ['kind' => $kind->value, 'label' => $kind->label(), 'total' => (int) ($byKind[$kind->value] ?? 0)])
                ->values()->all(),
            'verdicts' => $verdicts->map(fn ($row) => [
                'verdict'  => $row->verdict,
                'label'    => ChatNoiseVerdictEnum::tryFrom((string) $row->verdict)?->label() ?? $row->verdict,
                'total'    => (int) $row->total,
            ])->sortByDesc('total')->values()->all(),
            'checks'    => (int) $verdicts->sum('total'),
            'put_aside' => (int) $verdicts->sum('put_aside'),
            'overruled' => (int) $verdicts->sum('overruled'),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-robot',
                        'route' => ['name' => 'grp.chat.ai.dashboard'],
                        'label' => __('AI assist'),
                    ],
                ],
            ]
        );
    }
}
