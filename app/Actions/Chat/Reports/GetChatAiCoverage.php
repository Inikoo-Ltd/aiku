<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 23 Oct 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Reports;

use App\Enums\CRM\Livechat\ChatAiOutcomeEnum;
use App\Models\Chat\ChatAiRun;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What the AI customer service sweep did with every chat a customer wrote to in a window, by
 * channel. A chat is counted once, by the customer's latest message in the window: the outcome
 * recorded for that message, else a staff reply after it, else the bin it was put in, else not
 * worked yet. A newer message is new work, so an outcome for an earlier one no longer covers it.
 */
class GetChatAiCoverage
{
    use AsAction;

    public const array WINDOWS = ['last_hour', 'today', 'overnight', 'team_hours', 'last_24h', 'last_7_days', 'custom'];

    public const array CHANNELS = ['email', 'website', 'whatsapp'];

    public const string NOT_WORKED = 'not_worked';

    public const array WORKED = [
        ChatAiOutcomeEnum::DRAFT->value,
        ChatAiOutcomeEnum::NOTE->value,
        ChatAiOutcomeEnum::REVIEW_FAILED_NOTE->value,
        ChatAiOutcomeEnum::SPAM->value,
        ChatAiOutcomeEnum::HELD->value,
        ChatAiOutcomeEnum::OPENED->value,
        ChatAiOutcomeEnum::WHATSAPP_WINDOW_CLOSED->value,
        ChatAiOutcomeEnum::THANKS->value,
        ChatAiOutcomeEnum::NOTICE->value,
        ChatAiOutcomeEnum::ANSWERED_ELSEWHERE->value,
    ];

    /**
     * @return array{window: array<string, mixed>, matrix: array<string, array<string, int>>, shops: array<int, array<string, mixed>>, trend: array{unit: string, points: array<int, array<string, mixed>>}, accounts: array<int, array<string, mixed>>}
     */
    public function handle(Group $group, string $window, ?string $from = null, ?string $to = null): array
    {
        $segments = $this->segments($group, $window, $from, $to);
        $chats    = DB::query()->fromSub($this->chats($group, $segments), 'coverage');

        $counts = (clone $chats)->selectRaw('bucket, channel, shop_slug, organisation_slug, count(*) as total')
            ->groupBy('bucket', 'channel', 'shop_slug', 'organisation_slug')
            ->get();

        return [
            'window' => [
                'key'      => $window,
                'segments' => array_map(fn (array $segment) => [
                    'label' => $segment['label'],
                    'from'  => $segment['from']->toIso8601String(),
                    'to'    => $segment['to']->toIso8601String(),
                ], $segments),
            ],
            'matrix' => $this->matrix($counts),
            'shops'  => $counts->groupBy('shop_slug')->map(fn ($rows, $shop) => [
                'shop'         => $shop,
                'organisation' => $rows->first()->organisation_slug,
                'buckets'      => $rows->groupBy('bucket')->map(fn ($bucketRows) => (int) $bucketRows->sum('total'))->all(),
                'received'     => (int) $rows->sum('total'),
            ])->sortBy([['organisation', 'asc'], ['shop', 'asc']])->values()->all(),
            'trend'    => $this->trend($chats, $segments),
            'accounts' => $this->accounts($group, $segments),
        ];
    }

    /**
     * The runs of each claude.ai account (start_run account): how many ran in the window and the
     * chats they settled, which are working now, which stopped without finishing (usually the
     * account ran out of usage), and when the account last started one, looking back at least a
     * week so an account that stopped running altogether still shows.
     *
     * @param array<int, array{label: string, organisation_ids: ?array<int, int>, from: Carbon, to: Carbon}> $segments
     * @return array<int, array{account: ?string, runs: int, chats: int, working: int, stopped: int, last_started_at: string}>
     */
    private function accounts(Group $group, array $segments): array
    {
        $from  = collect($segments)->min(fn (array $segment) => $segment['from']);
        $to    = collect($segments)->max(fn (array $segment) => $segment['to']);
        $quiet = now()->subMinutes(ChatAiRun::IDLE_MINUTES);

        $inWindow = 'started_at between ? and ?';
        $settled  = '(select count(*) from chat_ai_outcomes where chat_ai_outcomes.run_id = chat_ai_runs.id)';

        return DB::table('chat_ai_runs')
            ->where('group_id', $group->id)
            ->where('started_at', '>=', $from->copy()->min(now()->subDays(7)))
            ->selectRaw('account')
            ->selectRaw("count(*) filter (where $inWindow) as runs", [$from, $to])
            ->selectRaw("coalesce(sum($settled) filter (where $inWindow), 0) as chats", [$from, $to])
            ->selectRaw('count(*) filter (where finished_at is null and coalesce(last_activity_at, started_at) >= ?) as working', [$quiet])
            ->selectRaw("count(*) filter (where $inWindow and finished_at is null and coalesce(last_activity_at, started_at) < ?) as stopped", [$from, $to, $quiet])
            ->selectRaw('max(started_at) as last_started_at')
            ->groupBy('account')
            ->orderBy('account')
            ->get()
            ->map(fn ($row) => [
                'account'         => $row->account,
                'runs'            => (int) $row->runs,
                'chats'           => (int) $row->chats,
                'working'         => (int) $row->working,
                'stopped'         => (int) $row->stopped,
                'last_started_at' => Carbon::parse($row->last_started_at)->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @param array<int, array{label: string, organisation_ids: ?array<int, int>, from: Carbon, to: Carbon}> $segments
     */
    public function chats(Group $group, array $segments): Builder
    {
        return $this->chatSessionChats($group, $segments)->unionAll($this->whatsappChats($group, $segments));
    }

    /**
     * @param array<int, array{label: string, organisation_ids: ?array<int, int>, from: Carbon, to: Carbon}> $segments
     */
    private function chatSessionChats(Group $group, array $segments): Builder
    {
        $latest = DB::table('chat_messages')
            ->join('chat_sessions', 'chat_sessions.id', '=', 'chat_messages.chat_session_id')
            ->join('shops', 'shops.id', '=', 'chat_sessions.shop_id')
            ->where('shops.group_id', $group->id)
            ->whereIn('chat_messages.sender_type', ['guest', 'user'])
            ->whereNull('chat_messages.deleted_at')
            ->whereNull('chat_sessions.deleted_at')
            ->where(fn ($query) => $this->inSegments($query, $segments, 'chat_messages.created_at'))
            ->selectRaw('distinct on (chat_messages.chat_session_id) chat_messages.chat_session_id as session_id, chat_messages.id as message_id, chat_messages.created_at as at')
            ->orderBy('chat_messages.chat_session_id')
            ->orderByDesc('chat_messages.id');

        return DB::query()->fromSub($latest, 'latest')
            ->join('chat_sessions', 'chat_sessions.id', '=', 'latest.session_id')
            ->join('shops', 'shops.id', '=', 'chat_sessions.shop_id')
            ->join('organisations', 'organisations.id', '=', 'shops.organisation_id')
            ->leftJoin('chat_ai_outcomes', fn ($join) => $join->on('chat_ai_outcomes.chat_session_id', '=', 'latest.session_id')
                ->on('chat_ai_outcomes.trigger_message_id', '=', 'latest.message_id'))
            ->select([
                'chat_sessions.channel as channel',
                'latest.session_id',
                'chat_sessions.ulid as session_ulid',
                'latest.message_id',
                'latest.at',
                'shops.slug as shop_slug',
                'shops.name as shop_name',
                'organisations.slug as organisation_slug',
                DB::raw("coalesce(chat_sessions.metadata->>'name', chat_sessions.metadata->>'email_from') as contact"),
                DB::raw("case
                    when chat_ai_outcomes.outcome is not null then chat_ai_outcomes.outcome
                    when exists (select 1 from chat_messages reply where reply.chat_session_id = latest.session_id and reply.sender_type = 'agent' and reply.deleted_at is null and reply.id > latest.message_id) then '".ChatAiOutcomeEnum::ANSWERED_BY_STAFF->value."'
                    when chat_sessions.is_spam then '".ChatAiOutcomeEnum::SPAM->value."'
                    when coalesce(chat_sessions.is_rubbish, false) then '".ChatAiOutcomeEnum::NOTICE->value."'
                    else '".self::NOT_WORKED."' end as bucket"),
                DB::raw("case when chat_ai_outcomes.outcome is not null then 'recorded' else 'chat' end as source"),
                'chat_ai_outcomes.reason',
            ]);
    }

    /**
     * @param array<int, array{label: string, organisation_ids: ?array<int, int>, from: Carbon, to: Carbon}> $segments
     */
    private function whatsappChats(Group $group, array $segments): Builder
    {
        $latest = DB::table('meta_chat_messages')
            ->join('meta_chat_sessions', 'meta_chat_sessions.id', '=', 'meta_chat_messages.meta_chat_session_id')
            ->join('shops', 'shops.id', '=', 'meta_chat_sessions.shop_id')
            ->where('shops.group_id', $group->id)
            ->whereIn('meta_chat_messages.sender_type', ['guest', 'user'])
            ->whereNull('meta_chat_messages.deleted_at')
            ->whereNull('meta_chat_sessions.deleted_at')
            ->where(fn ($query) => $this->inSegments($query, $segments, 'meta_chat_messages.created_at'))
            ->selectRaw('distinct on (meta_chat_messages.meta_chat_session_id) meta_chat_messages.meta_chat_session_id as session_id, meta_chat_messages.id as message_id, meta_chat_messages.created_at as at')
            ->orderBy('meta_chat_messages.meta_chat_session_id')
            ->orderByDesc('meta_chat_messages.id');

        return DB::query()->fromSub($latest, 'latest')
            ->join('meta_chat_sessions', 'meta_chat_sessions.id', '=', 'latest.session_id')
            ->join('shops', 'shops.id', '=', 'meta_chat_sessions.shop_id')
            ->join('organisations', 'organisations.id', '=', 'shops.organisation_id')
            ->leftJoin('chat_ai_outcomes', fn ($join) => $join->on('chat_ai_outcomes.meta_chat_session_id', '=', 'latest.session_id')
                ->on('chat_ai_outcomes.trigger_message_id', '=', 'latest.message_id'))
            ->select([
                DB::raw("'whatsapp' as channel"),
                'latest.session_id',
                'meta_chat_sessions.ulid as session_ulid',
                'latest.message_id',
                'latest.at',
                'shops.slug as shop_slug',
                'shops.name as shop_name',
                'organisations.slug as organisation_slug',
                'meta_chat_sessions.phone_number as contact',
                DB::raw("case
                    when chat_ai_outcomes.outcome is not null then chat_ai_outcomes.outcome
                    when exists (select 1 from meta_chat_messages reply where reply.meta_chat_session_id = latest.session_id and reply.sender_type = 'agent' and reply.deleted_at is null and reply.id > latest.message_id) then '".ChatAiOutcomeEnum::ANSWERED_BY_STAFF->value."'
                    when meta_chat_sessions.is_spam then '".ChatAiOutcomeEnum::SPAM->value."'
                    else '".self::NOT_WORKED."' end as bucket"),
                DB::raw("case when chat_ai_outcomes.outcome is not null then 'recorded' else 'chat' end as source"),
                'chat_ai_outcomes.reason',
            ]);
    }

    /**
     * @param array<int, array{label: string, organisation_ids: ?array<int, int>, from: Carbon, to: Carbon}> $segments
     */
    private function inSegments($query, array $segments, string $column): void
    {
        foreach ($segments as $segment) {
            $query->orWhere(function ($inSegment) use ($segment, $column) {
                $inSegment->where($column, '>=', $segment['from'])->where($column, '<', $segment['to']);
                if ($segment['organisation_ids'] !== null) {
                    $inSegment->whereIn('shops.organisation_id', $segment['organisation_ids']);
                }
            });
        }
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function matrix($counts): array
    {
        $count = fn (array $buckets, ?string $channel) => (int) $counts
            ->filter(fn ($row) => in_array($row->bucket, $buckets) && ($channel === null || $row->channel === $channel))
            ->sum('total');

        $row = fn (array $buckets) => collect(self::CHANNELS)
            ->mapWithKeys(fn (string $channel) => [$channel => $count($buckets, $channel)])
            ->put('total', $count($buckets, null))
            ->all();

        $all = [...self::WORKED, ChatAiOutcomeEnum::ANSWERED_BY_STAFF->value, ChatAiOutcomeEnum::SUGGESTION_ALREADY_WAITING->value, self::NOT_WORKED];

        return [
            'received'                                          => $row($all),
            ChatAiOutcomeEnum::ANSWERED_BY_STAFF->value          => $row([ChatAiOutcomeEnum::ANSWERED_BY_STAFF->value]),
            ChatAiOutcomeEnum::SUGGESTION_ALREADY_WAITING->value => $row([ChatAiOutcomeEnum::SUGGESTION_ALREADY_WAITING->value]),
            'worked'                                            => $row(self::WORKED),
            ...collect(self::WORKED)->mapWithKeys(fn (string $bucket) => [$bucket => $row([$bucket])])->all(),
            self::NOT_WORKED                                    => $row([self::NOT_WORKED]),
        ];
    }

    /**
     * @param array<int, array{label: string, organisation_ids: ?array<int, int>, from: Carbon, to: Carbon}> $segments
     *
     * @return array{unit: string, points: array<int, array{at: string, received: int, covered: int}>}
     */
    private function trend(Builder $chats, array $segments): array
    {
        $from = collect($segments)->min(fn (array $segment) => $segment['from']);
        $to   = collect($segments)->max(fn (array $segment) => $segment['to']);
        $unit = $from->diffInHours($to) <= 48 ? 'hour' : 'day';

        $rows = (clone $chats)
            ->selectRaw("date_trunc('$unit', at) as bucket_at, count(*) as received, count(*) filter (where bucket <> ?) as covered", [self::NOT_WORKED])
            ->groupByRaw('1')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->bucket_at)->toIso8601String());

        $points = [];
        for ($at = $from->copy()->utc()->startOf($unit); $at->lt($to); $at->add(1, $unit)) {
            $row      = $rows->get($at->toIso8601String());
            $points[] = ['at' => $at->toIso8601String(), 'received' => (int) ($row->received ?? 0), 'covered' => (int) ($row->covered ?? 0)];
        }

        return ['unit' => $unit, 'points' => $points];
    }

    /**
     * Team windows are cut per team in the team's own clock: today from midnight, team hours the
     * current or last weekday shift, overnight from the last shift's end to the next start or now.
     * The rest are one span for every shop.
     *
     * @return array<int, array{label: string, organisation_ids: ?array<int, int>, from: Carbon, to: Carbon}>
     */
    public function segments(Group $group, string $window, ?string $from = null, ?string $to = null, ?Carbon $now = null): array
    {
        $now ??= now()->addSecond()->startOfSecond();

        $span = fn (Carbon $start, Carbon $end) => [['label' => __('All shops'), 'organisation_ids' => null, 'from' => $start, 'to' => $end]];

        return match ($window) {
            'last_hour'   => $span($now->copy()->subHour(), $now),
            'last_24h'    => $span($now->copy()->subDay(), $now),
            'last_7_days' => $span($now->copy()->subDays(7), $now),
            'custom'      => $span(
                Carbon::parse($from ?? $now->toDateString())->startOfDay(),
                Carbon::parse($to ?? $from ?? $now->toDateString())->addDay()->startOfDay()
            ),
            default       => $this->teamSegments($group, $window, $now),
        };
    }

    /**
     * @return array<int, array{label: string, organisation_ids: ?array<int, int>, from: Carbon, to: Carbon}>
     */
    private function teamSegments(Group $group, string $window, Carbon $now): array
    {
        $segments = [];

        foreach (config('chat.ai_teams') as $team) {
            $local = $now->copy()->setTimezone($team['timezone']);
            $shift = fn (Carbon $day) => [$day->copy()->setTimeFromTimeString($team['start']), $day->copy()->setTimeFromTimeString($team['end'])];

            $lastShift = null;
            for ($day = $local->copy()->startOfDay(); !$lastShift; $day->subDay()) {
                if ($day->isWeekday() && $shift($day)[0]->lte($local)) {
                    $lastShift = $shift($day);
                }
            }

            [$start, $end] = match ($window) {
                'today'      => [$local->copy()->startOfDay(), $local],
                'team_hours' => [$lastShift[0], $lastShift[1]->min($local)],
                default      => $this->overnight($lastShift, $local, $shift),
            };

            $segments[] = [
                'label'            => $team['label'],
                'organisation_ids' => Organisation::where('group_id', $group->id)->whereIn('slug', Arr::wrap($team['organisations']))->pluck('id')->all(),
                'from'             => $start->utc(),
                'to'               => $end->utc(),
            ];
        }

        return $segments;
    }

    /**
     * @param array{0: Carbon, 1: Carbon} $lastShift
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function overnight(array $lastShift, Carbon $local, callable $shift): array
    {
        if ($lastShift[1]->gt($local)) {
            $previous = $lastShift[0]->copy()->subDay();
            while (!$previous->isWeekday()) {
                $previous->subDay();
            }

            return [$shift($previous->startOfDay())[1], $lastShift[0]];
        }

        $nextStart = $lastShift[0]->copy()->addDay();
        while (!$nextStart->isWeekday()) {
            $nextStart->addDay();
        }

        return [$lastShift[1], $nextStart->min($local)];
    }
}
