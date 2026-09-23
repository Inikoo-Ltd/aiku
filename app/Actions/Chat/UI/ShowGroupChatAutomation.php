<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 00:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Enums\CRM\Livechat\ChatAutomationKindEnum;
use App\Enums\CRM\Livechat\ChatNoiseVerdictEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Chat\ChatMessage;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Everything the chat did on its own, newest first, in every channel: each fixed message it
 * sent a customer and each noise check that decided whether a stranger reached the queue, with
 * the words sent or the verdict and why. So nothing automatic ever happens out of sight.
 */
class ShowGroupChatAutomation extends OrgAction
{
    use WithInertia;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasGroupAccess();
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
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

        $query = QueryBuilder::for(ChatMessage::withoutGlobalScopes()->fromSub($this->activity($group), 'automation'));

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
    private function activity(Group $group): \Illuminate\Database\Query\Builder
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
            ->tap(fn ($query) => $shops($query, 'chat_sessions'))
            ->select([
                DB::raw("chat_messages.metadata->>'automated' as kind"),
                'chat_messages.created_at as at',
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
            ->whereRaw("coalesce(meta_chat_messages.metadata->>'out_of_hours_replied_at', meta_chat_messages.metadata->>'asked_if_customer', meta_chat_messages.metadata->>'greeted_at', meta_chat_messages.metadata->>'greeting') is not null")
            ->tap(fn ($query) => $shops($query, 'meta_chat_sessions'))
            ->select([
                DB::raw("case
                    when meta_chat_messages.metadata->>'out_of_hours_replied_at' is not null then 'out_of_hours'
                    when meta_chat_messages.metadata->>'asked_if_customer' is not null then 'asked_if_customer'
                    else 'greeting' end as kind"),
                'meta_chat_messages.created_at as at',
                DB::raw("'whatsapp' as channel"),
                DB::raw('null::char(26) as session_ulid'),
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
                DB::raw("'whatsapp' as channel"),
                DB::raw('null::char(26) as session_ulid'),
                'meta_chat_sessions.noise_note as text',
                'meta_chat_sessions.noise_verdict as verdict',
                'meta_chat_sessions.noise_confidence as confidence',
                'meta_chat_sessions.noise_source as source',
                DB::raw('(meta_chat_sessions.is_spam and meta_chat_sessions.spammed_by_agent_id is null) as put_aside'),
                DB::raw('meta_chat_sessions.noise_reversed_at is not null as reversed'),
                ...$common('meta_chat_sessions.phone_number'),
            ]);

        return $sent->unionAll($sentOnWhatsapp)->unionAll($checked)->unionAll($checkedOnWhatsapp);
    }

    private function getElementGroups(Group $group): array
    {
        $counts = DB::query()->fromSub($this->activity($group), 'automation')
            ->selectRaw('kind, count(*) as total')
            ->groupBy('kind')
            ->pluck('total', 'kind');

        $elements = [];
        foreach (ChatAutomationKindEnum::cases() as $case) {
            $elements[$case->value] = [$case->label(), (int) ($counts[$case->value] ?? 0)];
        }

        return [
            'kind' => [
                'label'    => __('What'),
                'elements' => $elements,
                'engine'   => fn ($query, $elements) => $query->whereIn('automation.kind', $elements),
            ],
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
                ->column(key: 'text', label: __('Sent or decided'), canBeHidden: false)
                ->defaultSort('-at');
        };
    }

    public function htmlResponse(LengthAwarePaginator $activity, ActionRequest $request): Response
    {
        $activity->getCollection()->transform(function ($row) {
            $kind    = ChatAutomationKindEnum::tryFrom((string) $row->kind);
            $verdict = ChatNoiseVerdictEnum::tryFrom((string) $row->verdict);

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
                'verdict_label' => $verdict?->label(),
                'is_noise'      => (bool) $verdict?->isNoise(),
                'confidence'    => $row->confidence,
                'source'        => $row->source,
                'put_aside'     => (bool) $row->put_aside,
                'reversed'      => (bool) $row->reversed,
                'url'           => $row->session_ulid
                    ? route('grp.org.chat.inbox.conversation', [$row->organisation_slug, trim($row->session_ulid)])
                    : route('grp.org.chat.inbox', [$row->organisation_slug]),
            ];
        });

        return Inertia::render(
            'Chat/ChatAutomation',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('AI'),
                'pageHead'    => [
                    'title' => __('AI and automatic messages'),
                    'icon'  => [
                        'title' => __('AI'),
                        'icon'  => ['fal', 'fa-robot'],
                    ],
                ],
                'data' => JsonResource::collection($activity),
            ]
        )->table($this->tableStructure($this->group));
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
                        'route' => ['name' => 'grp.chat.ai'],
                        'label' => __('AI'),
                    ],
                ],
            ]
        );
    }
}
