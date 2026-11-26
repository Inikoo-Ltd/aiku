<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 04:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\UI;

use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\InertiaTable\InertiaTable;
use App\Models\Chat\SlackChannel;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\AllowedFilter;

class ShowStaffChatReports extends OrgAction
{
    use AsAction;
    use WithInertia;

    public const array PERIODS = [7, 30, 90];

    private int $days = 30;

    private string $by = 'day';

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasGroupAccess();
    }

    public function rules(): array
    {
        return [
            'days' => ['sometimes', 'integer', 'in:'.implode(',', self::PERIODS)],
            'by'   => ['sometimes', 'string', 'in:day,week'],
        ];
    }

    public function asController(ActionRequest $request): Group
    {
        $this->initialisationFromGroup(app('group'), $request);
        $this->days = (int) $request->query('days', 30);
        $this->by   = $request->query('by', $this->days > 30 ? 'week' : 'day');

        return $this->group;
    }

    /**
     * @return array{from: Carbon, to: Carbon, previous_from: Carbon}
     */
    public function window(int $days, ?Carbon $now = null): array
    {
        $to   = ($now ?? now())->copy();
        $from = $to->copy()->subDays($days - 1)->startOfDay();

        return ['from' => $from, 'to' => $to, 'previous_from' => $from->copy()->subDays($days)];
    }

    public function handle(Group $group, int $days, string $by, ?Carbon $now = null): array
    {
        $window = $this->window($days, $now);

        return [
            'days'     => $days,
            'by'       => $by,
            'from'     => $window['from']->toIso8601String(),
            'to'       => $window['to']->toIso8601String(),
            'current'  => $this->totals($group, $window['from'], $window['to']),
            'previous' => $this->totals($group, $window['previous_from'], $window['from']),
            'series'   => $this->series($group, $window['from'], $window['to'], $by),
        ];
    }

    private function slackChannelIds(Group $group): array
    {
        return SlackChannel::query()->kept()->where('group_id', $group->id)->pluck('id')->all();
    }

    private function messageScope(): string
    {
        return 'from staff_messages m join staff_conversations c on c.id = m.staff_conversation_id
            where c.group_id = :group and m.staff_call_id is null and m.deleted_at is null
            and m.created_at >= :from and m.created_at < :to';
    }

    private function totals(Group $group, Carbon $from, Carbon $to): array
    {
        $bindings = ['group' => $group->id, 'from' => $from, 'to' => $to];

        $messages = DB::selectOne(
            "select count(*) as messages, count(distinct m.user_id) as people,
                count(distinct m.staff_conversation_id) as conversations,
                count(*) filter (where c.type = 'dm') as direct_messages
            ".$this->messageScope(),
            $bindings
        );

        $reply = DB::selectOne(
            'select percentile_cont(0.5) within group (order by gap) as median_seconds, count(*) as replies from (
                select extract(epoch from lead(created_at) over (partition by conversation_id order by created_at) - created_at) as gap
                from (
                    select m.staff_conversation_id as conversation_id, m.created_at, m.user_id,
                        lag(m.user_id) over (partition by m.staff_conversation_id order by m.created_at) as previous_user_id
                    '.$this->messageScope().'
                ) as messages
                where previous_user_id is distinct from user_id
            ) as turns where gap is not null',
            $bindings
        );

        $calls = DB::selectOne(
            "with calls as (
                select sc.*, (select count(*) from staff_call_participants p where p.staff_call_id = sc.id) as people
                from staff_calls sc where sc.group_id = :group and sc.started_at >= :from and sc.started_at < :to
            )
            select count(*) as calls,
                count(*) filter (where outcome = 'answered') as answered,
                count(*) filter (where outcome = 'missed') as missed,
                count(*) filter (where outcome = 'declined') as declined,
                count(*) filter (where outcome = 'cancelled') as cancelled,
                count(*) filter (where kind = 'voice') as voice,
                count(*) filter (where kind = 'video') as video,
                count(*) filter (where kind = 'screen') as screen,
                count(*) filter (where people >= 3) as meetings,
                avg(people) filter (where answered_at is not null) as people_per_call,
                percentile_cont(0.5) within group (order by extract(epoch from answered_at - started_at)) filter (where answered_at is not null) as answer_seconds,
                percentile_cont(0.5) within group (order by extract(epoch from ended_at - answered_at)) filter (where answered_at is not null and ended_at is not null) as median_call_seconds,
                coalesce(sum(extract(epoch from ended_at - answered_at)) filter (where answered_at is not null and ended_at is not null), 0) as talk_seconds
            from calls",
            $bindings
        );

        $personSeconds = DB::selectOne(
            'select coalesce(sum(extract(epoch from coalesce(p.left_at, p.last_seen_at) - p.joined_at)), 0) as seconds
            from staff_call_participants p join staff_calls sc on sc.id = p.staff_call_id
            where sc.group_id = :group and p.joined_at >= :from and p.joined_at < :to',
            $bindings
        );

        $slackChannelIds = $this->slackChannelIds($group);
        $slack           = $slackChannelIds
            ? DB::table('slack_messages')
                ->whereIn('slack_channel_id', $slackChannelIds)
                ->whereNull('deleted_at')
                ->where('sent_at', '>=', $from)
                ->where('sent_at', '<', $to)
                ->selectRaw('count(*) as messages, count(*) filter (where sent_from_aiku) as from_aiku, count(distinct coalesce(user_id::text, slack_user_id)) as people')
                ->first()
            : null;

        return [
            'messages'              => (int) $messages->messages,
            'people'                => (int) $messages->people,
            'conversations'         => (int) $messages->conversations,
            'direct_messages'       => (int) $messages->direct_messages,
            'group_messages'        => (int) $messages->messages - (int) $messages->direct_messages,
            'reply_median_seconds'  => $reply->median_seconds === null ? null : (int) round($reply->median_seconds),
            'calls'                 => (int) $calls->calls,
            'answered'              => (int) $calls->answered,
            'missed'                => (int) $calls->missed,
            'declined'              => (int) $calls->declined,
            'cancelled'             => (int) $calls->cancelled,
            'voice'                 => (int) $calls->voice,
            'video'                 => (int) $calls->video,
            'screen'                => (int) $calls->screen,
            'meetings'              => (int) $calls->meetings,
            'people_per_call'       => $calls->people_per_call === null ? null : round((float) $calls->people_per_call, 1),
            'answer_median_seconds' => $calls->answer_seconds === null ? null : (int) round($calls->answer_seconds),
            'call_median_seconds'   => $calls->median_call_seconds === null ? null : (int) round($calls->median_call_seconds),
            'talk_minutes'          => (int) round($calls->talk_seconds / 60),
            'person_minutes'        => (int) round($personSeconds->seconds / 60),
            'slack_messages'        => (int) ($slack->messages ?? 0),
            'slack_from_aiku'       => (int) ($slack->from_aiku ?? 0),
            'slack_people'          => (int) ($slack->people ?? 0),
        ];
    }

    private function series(Group $group, Carbon $from, Carbon $to, string $by): array
    {
        $buckets = [];
        for ($bucket = $from->copy()->startOf($by === 'week' ? 'week' : 'day'); $bucket < $to; $by === 'week' ? $bucket->addWeek() : $bucket->addDay()) {
            $buckets[] = $bucket->toDateString();
        }

        $bindings = ['group' => $group->id, 'from' => $from, 'to' => $to, 'by' => $by];

        $messages = collect(DB::select(
            'select date_trunc(:by, m.created_at)::date as bucket, count(*) as messages, count(distinct m.user_id) as people '.
            $this->messageScope().' group by 1',
            $bindings
        ))->keyBy(fn ($row) => (string) $row->bucket);

        $calls = collect(DB::select(
            "select date_trunc(:by, started_at)::date as bucket, count(*) as calls,
                count(*) filter (where outcome = 'answered') as answered,
                count(*) filter (where outcome in ('missed', 'declined')) as missed,
                coalesce(sum(extract(epoch from ended_at - answered_at)) filter (where answered_at is not null and ended_at is not null), 0) as talk_seconds
            from staff_calls where group_id = :group and started_at >= :from and started_at < :to group by 1",
            $bindings
        ))->keyBy(fn ($row) => (string) $row->bucket);

        $slackChannelIds = $this->slackChannelIds($group);
        $slack           = $slackChannelIds
            ? DB::table('slack_messages')
                ->whereIn('slack_channel_id', $slackChannelIds)
                ->whereNull('deleted_at')
                ->where('sent_at', '>=', $from)
                ->where('sent_at', '<', $to)
                ->selectRaw('date_trunc(?, sent_at)::date as bucket, count(*) as messages, count(distinct coalesce(user_id::text, slack_user_id)) as people', [$by])
                ->groupByRaw('1')
                ->get()
                ->keyBy(fn ($row) => (string) $row->bucket)
            : collect();

        return [
            'buckets'        => $buckets,
            'messages'       => array_map(fn ($bucket) => (int) ($messages[$bucket]->messages ?? 0), $buckets),
            'people'         => array_map(fn ($bucket) => (int) ($messages[$bucket]->people ?? 0), $buckets),
            'slack_messages' => array_map(fn ($bucket) => (int) ($slack[$bucket]->messages ?? 0), $buckets),
            'slack_people'   => array_map(fn ($bucket) => (int) ($slack[$bucket]->people ?? 0), $buckets),
            'calls'          => array_map(fn ($bucket) => (int) ($calls[$bucket]->calls ?? 0), $buckets),
            'answered'       => array_map(fn ($bucket) => (int) ($calls[$bucket]->answered ?? 0), $buckets),
            'missed'         => array_map(fn ($bucket) => (int) ($calls[$bucket]->missed ?? 0), $buckets),
            'talk_minutes'   => array_map(fn ($bucket) => (int) round(($calls[$bucket]->talk_seconds ?? 0) / 60), $buckets),
        ];
    }

    public function people(Group $group, Carbon $from, Carbon $to, string $prefix = 'people'): LengthAwarePaginator
    {
        InertiaTable::updateQueryBuilderParameters($prefix);

        $slackChannelIds = $this->slackChannelIds($group) ?: [0];
        $range           = [$from, $to];

        $messages = fn ($query) => $query->from('staff_messages')
            ->join('staff_conversations', 'staff_conversations.id', '=', 'staff_messages.staff_conversation_id')
            ->whereColumn('staff_messages.user_id', 'users.id')
            ->where('staff_conversations.group_id', $group->id)
            ->whereNull('staff_messages.staff_call_id')
            ->whereNull('staff_messages.deleted_at')
            ->whereBetween('staff_messages.created_at', $range);
        $participations = fn ($query) => $query->from('staff_call_participants')
            ->join('staff_calls', 'staff_calls.id', '=', 'staff_call_participants.staff_call_id')
            ->whereColumn('staff_call_participants.user_id', 'users.id')
            ->where('staff_calls.group_id', $group->id)
            ->whereBetween('staff_call_participants.joined_at', $range);
        $slackMessages = fn ($query) => $query->from('slack_messages')
            ->whereColumn('slack_messages.user_id', 'users.id')
            ->whereIn('slack_messages.slack_channel_id', $slackChannelIds)
            ->whereNull('slack_messages.deleted_at')
            ->whereBetween('slack_messages.sent_at', $range);

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($where) => $where->whereStartWith('users.contact_name', $value)->orWhereStartWith('users.username', $value));
        });

        return QueryBuilder::for(User::class)
            ->where('users.group_id', $group->id)
            ->where(fn ($query) => $query->whereExists($messages)->orWhereExists($participations)->orWhereExists($slackMessages))
            ->select(['users.id', 'users.username', 'users.contact_name'])
            ->selectSub(fn ($query) => $messages($query)->selectRaw('count(*)'), 'messages')
            ->selectSub(fn ($query) => $messages($query)->selectRaw('count(distinct staff_messages.staff_conversation_id)'), 'conversations')
            ->selectSub(fn ($query) => $query->from('staff_calls')->whereColumn('staff_calls.started_by_user_id', 'users.id')->where('staff_calls.group_id', $group->id)->whereBetween('staff_calls.started_at', $range)->selectRaw('count(*)'), 'calls_started')
            ->selectSub(fn ($query) => $participations($query)->selectRaw('count(*)'), 'calls')
            ->selectSub(fn ($query) => $participations($query)->selectRaw('coalesce(round(sum(extract(epoch from coalesce(staff_call_participants.left_at, staff_call_participants.last_seen_at) - staff_call_participants.joined_at)) / 60), 0)::int'), 'call_minutes')
            ->selectSub(fn ($query) => $slackMessages($query)->selectRaw('count(*)'), 'slack_messages')
            ->defaultSort('-messages')
            ->allowedSorts(['contact_name', 'messages', 'conversations', 'calls_started', 'calls', 'call_minutes', 'slack_messages'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure(string $prefix = 'people'): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            $table->name($prefix)->pageName($prefix.'Page')
                ->withGlobalSearch()
                ->column(key: 'contact_name', label: __('Person'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'messages', label: __('Verbs'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'conversations', label: __('Chats'), sortable: true, align: 'right')
                ->column(key: 'calls_started', label: __('Calls made'), sortable: true, align: 'right')
                ->column(key: 'calls', label: __('Calls joined'), sortable: true, align: 'right')
                ->column(key: 'call_minutes', label: __('Call minutes'), sortable: true, align: 'right')
                ->column(key: 'slack_messages', label: __('Slack'), sortable: true, align: 'right')
                ->defaultSort('-messages');
        };
    }

    public function htmlResponse(Group $group, ActionRequest $request): Response
    {
        $report = $this->handle($group, $this->days, $this->by);
        $window = $this->window($this->days);
        $title  = __('Verba stats');

        return Inertia::render(
            'Chat/StaffChatReports',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => ['icon' => ['fal', 'fa-chart-line'], 'title' => $title],
                ],
                'periods'     => self::PERIODS,
                'report'      => $report,
                'people'      => JsonResource::collection($this->people($group, $window['from'], $window['to'])),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowStaffMessaging::make()->getBreadcrumbs(null),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-chart-line',
                        'route' => ['name' => 'grp.chat.staff.reports'],
                        'label' => __('Stats'),
                    ],
                ],
            ]
        );
    }
}
