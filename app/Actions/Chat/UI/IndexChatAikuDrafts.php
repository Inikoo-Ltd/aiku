<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Oct 2026 10:15:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\UI;

use App\Actions\Chat\ChatSession\DraftChatReply;
use App\Actions\Chat\Reports\GetChatAiAcceptance;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatAiDraftChangeEnum;
use App\Enums\CRM\Livechat\ChatAiDraftStatusEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Enums\CRM\Livechat\ChatIntentEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatMessage;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Everything Aiku left for staff on a chat, newest first: the reply suggestions it wrote, with the
 * customer message each answers, what staff then sent and how much of the draft they kept, and the
 * internal notes it left where it could not confirm an answer, each linked to its chat (WhatsApp
 * included). Only the shops whose chats the user may see are listed; who decided a draft, and the
 * filter by person, only on the shops the user supervises.
 */
class IndexChatAikuDrafts extends OrgAction
{
    use WithChatAgentAuthorisation;

    /** @var Collection<int, int> */
    private Collection $shopIds;

    /** @var Collection<int, int> */
    private Collection $supervisedShopIds;

    public const string PENDING = 'pending';

    public function authorize(ActionRequest $request): bool
    {
        $this->shopIds = $this->shopIdsFor($request->user());

        return $this->shopIds->isNotEmpty();
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(app('group'), $request);
        $this->supervisedShopIds = IndexChatAiAcceptance::make()->supervisedShopIds($request->user())->intersect($this->shopIds)->values();

        return $this->handle($this->shopIds, supervisedShopIds: $this->supervisedShopIds);
    }

    /**
     * @return Collection<int, int>
     */
    public function shopIdsFor(User $user): Collection
    {
        setPermissionsTeamId($user->group_id);

        return Shop::with('fulfilment')->where('group_id', $user->group_id)->get()
            ->filter(fn (Shop $shop) => $this->userCanViewChatOnShop($user, $shop))
            ->pluck('id')
            ->values();
    }

    /**
     * @param  Collection<int, int>  $shopIds
     */
    public function handle(Collection $shopIds, ?string $prefix = null, ?Collection $supervisedShopIds = null): LengthAwarePaginator
    {
        $supervisedShopIds ??= collect();
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($where) use ($value) {
                $where->where('aiku.text', 'ilike', '%'.$value.'%')
                    ->orWhere('aiku.contact', 'ilike', '%'.$value.'%')
                    ->orWhere('aiku.shop_name', 'ilike', '%'.$value.'%');
            });
        });

        return QueryBuilder::for(ChatMessage::withoutGlobalScopes()->fromSub($this->items($shopIds), 'aiku'))
            ->defaultSort('-at')
            ->allowedSorts(['at', 'kind', 'shop_name'])
            ->allowedFilters([
                $globalSearch,
                AllowedFilter::callback('shop', fn ($query, $value) => $query->where('aiku.shop_slug', $value)),
                AllowedFilter::callback('outcome', fn ($query, $value) => $value === self::PENDING
                    ? $query->where('aiku.status', ChatAiDraftStatusEnum::PENDING->value)
                    : $query->where('aiku.change_class', $value)),
                AllowedFilter::callback('intent', fn ($query, $value) => $query->where('aiku.intent', $value)),
                AllowedFilter::callback('channel', fn ($query, $value) => $query->where('aiku.channel', $value)),
                AllowedFilter::callback('kind', fn ($query, $value) => $query->where('aiku.kind', $value)),
                AllowedFilter::callback('week', function ($query, $value) {
                    $start = Carbon::parse($value)->startOfWeek();
                    $query->where('aiku.at', '>=', $start)->where('aiku.at', '<', $start->copy()->addWeek());
                }),
                AllowedFilter::callback('band', fn ($query, $value) => $query->whereIn('aiku.shop_id', GetChatAiAcceptance::make()->shopIdsInBand($this->group, (string) $value))),
                AllowedFilter::callback('flag', fn ($query, $value) => $value === 'time_lost' ? $query->whereIn('aiku.shop_id', $supervisedShopIds)->where('aiku.time_lost', true) : $query->whereRaw('false')),
                AllowedFilter::callback('staff', fn ($query, $value) => $query->whereIn('aiku.shop_id', $supervisedShopIds)->where('aiku.decided_by', (int) $value)),
            ])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * @param  Collection<int, int>  $shopIds
     */
    private function items(Collection $shopIds): \Illuminate\Database\Query\Builder
    {
        $common = [
            'shops.id as shop_id',
            'shops.slug as shop_slug',
            'shops.name as shop_name',
            'organisations.slug as organisation_slug',
        ];

        $drafts = DB::table('chat_ai_drafts')
            ->leftJoin('chat_sessions', 'chat_sessions.id', '=', 'chat_ai_drafts.chat_session_id')
            ->leftJoin('meta_chat_sessions', 'meta_chat_sessions.id', '=', 'chat_ai_drafts.meta_chat_session_id')
            ->leftJoin('chat_messages as question', fn ($join) => $join->on('question.id', '=', 'chat_ai_drafts.trigger_message_id')->whereNotNull('chat_ai_drafts.chat_session_id'))
            ->leftJoin('chat_messages as reply', fn ($join) => $join->on('reply.id', '=', 'chat_ai_drafts.reply_message_id')->whereNotNull('chat_ai_drafts.chat_session_id'))
            ->leftJoin('meta_chat_messages as wa_question', fn ($join) => $join->on('wa_question.id', '=', 'chat_ai_drafts.trigger_message_id')->whereNotNull('chat_ai_drafts.meta_chat_session_id'))
            ->leftJoin('meta_chat_messages as wa_reply', fn ($join) => $join->on('wa_reply.id', '=', 'chat_ai_drafts.reply_message_id')->whereNotNull('chat_ai_drafts.meta_chat_session_id'))
            ->leftJoin('chat_agents as reply_agent', fn ($join) => $join->on('reply_agent.id', '=', 'reply.sender_id')->where('reply.sender_type', ChatSenderTypeEnum::AGENT->value))
            ->join('shops', 'shops.id', '=', 'chat_ai_drafts.shop_id')
            ->join('organisations', 'organisations.id', '=', 'shops.organisation_id')
            ->whereIn('shops.id', $shopIds)
            ->whereRaw("chat_ai_drafts.facts->>'mode' = ?", [DraftChatReply::ASSISTANT])
            ->whereRaw('coalesce(chat_sessions.deleted_at, meta_chat_sessions.deleted_at) is null')
            ->whereRaw('(chat_sessions.id is not null or meta_chat_sessions.id is not null)')
            ->select([
                DB::raw("'draft' as kind"),
                'chat_ai_drafts.created_at as at',
                'chat_ai_drafts.id as item_id',
                'chat_ai_drafts.text as text',
                'chat_ai_drafts.status as status',
                'chat_ai_drafts.facts as facts',
                DB::raw("(chat_ai_drafts.facts->>'user_id')::integer as asked_by"),
                DB::raw('coalesce(chat_sessions.ulid, meta_chat_sessions.ulid) as session_ulid'),
                DB::raw("case when chat_ai_drafts.meta_chat_session_id is not null then 'whatsapp' else chat_sessions.channel end as channel"),
                DB::raw("coalesce(chat_sessions.metadata->>'name', chat_sessions.metadata->>'email_from', chat_sessions.metadata->>'email', meta_chat_sessions.phone_number) as contact"),
                'chat_ai_drafts.intent as intent',
                'chat_ai_drafts.change_class as change_class',
                DB::raw('coalesce(chat_ai_drafts.decided_by_user_id, reply_agent.user_id) as decided_by'),
                'chat_ai_drafts.time_lost as time_lost',
                'chat_ai_drafts.time_lost_seconds as time_lost_seconds',
                'chat_ai_drafts.chat_session_id as chat_session_id',
                'chat_ai_drafts.trigger_message_id as trigger_message_id',
                DB::raw('coalesce(question.original_text, question.message_text, wa_question.original_text, wa_question.message_text) as question'),
                DB::raw('coalesce(reply.original_text, reply.message_text, wa_reply.original_text, wa_reply.message_text) as reply'),
                ...$common,
            ]);

        $notes = DB::table('chat_events')
            ->join('chat_sessions', 'chat_sessions.id', '=', 'chat_events.chat_session_id')
            ->join('shops', 'shops.id', '=', 'chat_sessions.shop_id')
            ->join('organisations', 'organisations.id', '=', 'shops.organisation_id')
            ->whereIn('shops.id', $shopIds)
            ->whereNull('chat_sessions.deleted_at')
            ->where('chat_events.event_type', ChatEventTypeEnum::INTERNAL_NOTE->value)
            ->select([
                DB::raw("'note' as kind"),
                'chat_events.created_at as at',
                'chat_events.id as item_id',
                DB::raw("chat_events.payload->>'text' as text"),
                DB::raw('null::varchar as status'),
                DB::raw('null::jsonb as facts'),
                DB::raw("(chat_events.payload->>'requested_by_user_id')::integer as asked_by"),
                'chat_sessions.ulid as session_ulid',
                'chat_sessions.channel as channel',
                DB::raw("coalesce(chat_sessions.metadata->>'name', chat_sessions.metadata->>'email_from', chat_sessions.metadata->>'email') as contact"),
                DB::raw('null::varchar as intent'),
                DB::raw('null::varchar as change_class'),
                DB::raw('null::smallint as decided_by'),
                DB::raw('null::boolean as time_lost'),
                DB::raw('null::integer as time_lost_seconds'),
                DB::raw('null::integer as chat_session_id'),
                DB::raw('null::integer as trigger_message_id'),
                DB::raw('null::text as question'),
                DB::raw('null::text as reply'),
                ...$common,
            ]);

        return $drafts->unionAll($notes);
    }

    /**
     * @param  Collection<int, int>  $shopIds
     * @param  Collection<int, int>  $supervisedShopIds
     * @return array<string, array<string, string>>
     */
    public function filterOptions(Collection $shopIds, Collection $supervisedShopIds): array
    {
        $staff = $supervisedShopIds->isEmpty() ? [] : DB::table('chat_ai_drafts')
            ->join('users', 'users.id', 'chat_ai_drafts.decided_by_user_id')
            ->whereIn('chat_ai_drafts.shop_id', $supervisedShopIds)
            ->where('chat_ai_drafts.created_at', '>=', GetChatAiAcceptance::weekStarts()[GetChatAiAcceptance::WEEKS - 1])
            ->distinct()
            ->orderBy('users.contact_name')
            ->pluck('users.contact_name', 'users.id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])
            ->all();

        $weeks = [];
        foreach (GetChatAiAcceptance::weekStarts() as $start) {
            $weeks[$start->toDateString()] = __('Week of :date', ['date' => $start->isoFormat('D MMM')]);
        }

        $outcomes = [self::PENDING => __('Waiting')];
        foreach (ChatAiDraftChangeEnum::cases() as $case) {
            $outcomes[$case->value] = $case->label();
        }

        $intents = [];
        foreach (ChatIntentEnum::cases() as $case) {
            $intents[$case->value] = $case->label();
        }

        return [
            'shop'    => Shop::whereIn('id', $shopIds)->orderBy('name')->pluck('name', 'slug')->all(),
            'outcome' => $outcomes,
            'intent'  => $intents,
            'channel' => ['email' => __('Email'), 'website' => __('Website chat'), 'whatsapp' => __('WhatsApp')],
            'kind'    => ['draft' => __('Draft'), 'note' => __('Note for staff')],
            'band'    => ['high' => __('High'), 'medium' => __('Medium'), 'low' => __('Low'), 'few' => __('Too few')],
            'week'    => $weeks,
            'staff'   => $staff,
            'flag'    => $supervisedShopIds->isEmpty() ? [] : ['time_lost' => __('Time lost')],
        ];
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $labels = [
                'shop'    => __('Shop'),
                'outcome' => __('Outcome'),
                'intent'  => __('Customer wants'),
                'channel' => __('Channel'),
                'kind'    => __('What'),
                'week'    => __('Week'),
                'band'    => __('Band'),
                'staff'   => __('Decided by'),
                'flag'    => __('Tag'),
            ];
            foreach ($this->filterOptions($this->shopIds, $this->supervisedShopIds) as $key => $options) {
                if ($options) {
                    $table->selectFilter($key, $options, $labels[$key]);
                }
            }

            $table
                ->withGlobalSearch()
                ->column(key: 'at', label: __('When'), canBeHidden: false, sortable: true)
                ->column(key: 'shop_name', label: __('Shop'), sortable: true)
                ->column(key: 'contact', label: __('Customer'))
                ->column(key: 'outcome', label: __('Outcome'), canBeHidden: false)
                ->column(key: 'question', label: __('Customer wrote'), canBeHidden: false)
                ->column(key: 'text', label: __('Draft or note'), canBeHidden: false)
                ->column(key: 'reply', label: __('Staff sent'), canBeHidden: false)
                ->defaultSort('-at');
        };
    }

    /**
     * A discarded draft has no reply of its own: show the agent's next message in the chat, so
     * what staff wrote instead can be read beside it. Only for the rows on the page.
     *
     * @param  Collection<int, object>  $rows
     * @return array<int, string>
     */
    private function nextReplies(Collection $rows): array
    {
        $replies = [];
        foreach ($rows->filter(fn ($row) => $row->kind === 'draft' && $row->reply === null && $row->chat_session_id && $row->trigger_message_id) as $row) {
            $reply = DB::table('chat_messages')
                ->where('chat_session_id', $row->chat_session_id)
                ->where('id', '>', $row->trigger_message_id)
                ->where('sender_type', ChatSenderTypeEnum::AGENT->value)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->value(DB::raw('coalesce(original_text, message_text)'));
            if ($reply !== null) {
                $replies[$row->item_id] = $reply;
            }
        }

        return $replies;
    }

    /**
     * Only the page: the raw rows carry who decided each draft and the time lost, which the page shows
     * on supervised shops only.
     */
    public function jsonResponse(): never
    {
        abort(404);
    }

    public function htmlResponse(LengthAwarePaginator $items, ActionRequest $request): Response
    {
        $askers   = User::whereIn('id', $items->getCollection()->pluck('asked_by')->filter()->unique())->pluck('contact_name', 'id');
        $deciders = User::whereIn('id', $items->getCollection()->filter(fn ($row) => $this->supervisedShopIds->contains($row->shop_id))->pluck('decided_by')->filter()->unique())->pluck('contact_name', 'id');

        $nextReplies = $this->nextReplies($items->getCollection());

        $items->getCollection()->transform(function ($row) use ($askers, $deciders, $nextReplies) {
            $change  = ChatAiDraftChangeEnum::tryFrom((string) $row->change_class);
            $intent  = ChatIntentEnum::tryFrom((string) $row->intent);
            $facts   = $row->facts ? json_decode($row->facts, true) : [];
            $status  = ChatAiDraftStatusEnum::tryFrom((string) $row->status);
            $actions = collect($facts['actions'] ?? []);

            return [
                'at'           => Carbon::parse($row->at)->toIso8601String(),
                'kind'         => $row->kind,
                'channel'      => $row->channel,
                'shop_name'    => $row->shop_name,
                'contact'      => $row->contact,
                'text'         => $row->text,
                'status'       => $status?->value,
                'status_label' => $status?->label(),
                'staff_note'   => $facts['staff_note'] ?? null,
                'checked'      => count($facts['checked'] ?? []),
                'actions'      => $actions->map(fn (array $action) => [
                    'label' => $action['done']['label'] ?? ($action['label'] ?? ($action['type'] === 'claim' ? str_replace('_', ' ', $action['resolution']).' '.$action['order'] : $action['subject'] ?? '')),
                    'done'  => isset($action['done']),
                ])->values()->all(),
                'premonitions' => $facts['premonitions'] ?? [],
                'asked_by'     => $askers[$row->asked_by] ?? null,
                'question'     => $row->question,
                'reply'        => $row->reply ?? $nextReplies[$row->item_id] ?? null,
                'intent'       => $intent?->label(),
                'outcome'      => $change?->value ?? ($row->kind === 'draft' && $status === ChatAiDraftStatusEnum::PENDING ? self::PENDING : null),
                'outcome_label' => $change?->label() ?? ($row->kind === 'draft' && $status === ChatAiDraftStatusEnum::PENDING ? __('Waiting') : null),
                'taken'        => $change ? in_array($change->value, GetChatAiAcceptance::ADOPTED, true) : null,
                'decided_by'   => $deciders[$row->decided_by] ?? null,
                'time_lost'    => $this->supervisedShopIds->contains($row->shop_id) && $row->time_lost,
                'time_lost_seconds' => $this->supervisedShopIds->contains($row->shop_id) && $row->time_lost ? $row->time_lost_seconds : null,
                'url'          => $row->channel === 'whatsapp'
                    ? route('grp.org.chat.inbox', [$row->organisation_slug, 'channel' => 'whatsapp', 'session' => trim((string) $row->session_ulid)])
                    : route('grp.org.chat.inbox.conversation', [$row->organisation_slug, trim((string) $row->session_ulid)]),
            ];
        });

        return Inertia::render('Chat/ChatAikuDrafts', [
            'breadcrumbs' => ShowGroupChatAutomation::make()->getBreadcrumbs(),
            'title'       => __('Suggestions'),
            'pageHead'    => [
                'title'         => __('Suggestions'),
                'icon'          => ['title' => __('AI assist'), 'icon' => ['fal', 'fa-robot']],
                'subNavigation' => ShowGroupChatAutomation::make()->subNavigation($this->group),
            ],
            'data' => JsonResource::collection($items),
        ])->table($this->tableStructure());
    }
}
