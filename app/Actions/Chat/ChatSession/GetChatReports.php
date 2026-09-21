<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Enums\CRM\Livechat\ChatTopicEnum;
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Conversation figures for the chat reports page, over the three channels a conversation can
 * arrive on: the website widget, email and WhatsApp. WhatsApp lives in its own pair of tables,
 * so every cut is computed per source and merged here.
 *
 * A row in chat_sessions is not a conversation: about three quarters of them are a widget that
 * was opened and never typed into. Everything below counts only sessions with a message from
 * the visitor, and first reply is the first agent message minus the first visitor message,
 * never last_agent_message_at.
 */
class GetChatReports
{
    use AsAction;

    private const array SOURCES = [
        'chat' => [
            'sessions' => 'chat_sessions',
            'messages' => 'chat_messages',
            'foreign'  => 'chat_session_id',
            'channel'  => 's.channel',
            'where'    => ' and s.is_rubbish = false',
        ],
        'meta' => [
            'sessions' => 'meta_chat_sessions',
            'messages' => 'meta_chat_messages',
            'foreign'  => 'meta_chat_session_id',
            'channel'  => "'whatsapp'",
            'where'    => '',
        ],
    ];

    private const array CHANNEL_LABELS = [
        'website'  => 'Website',
        'email'    => 'Email',
        'whatsapp' => 'WhatsApp',
    ];

    private const array OPEN_STATUSES = ['active', 'waiting', 'transferred'];

    /**
     * @param  Collection<int, int>  $shopIds
     */
    public function handle(Collection $shopIds, string $interval, array $shopNames = []): array
    {
        [$from, $to] = $this->range($interval, $this->oldestConversationAt($shopIds));

        $days   = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        $bucket = $days <= 62 ? 'day' : ($days <= 400 ? 'week' : 'month');

        $sessions = $this->sessions($shopIds, $from, $to);
        $openAtStart = $this->openBefore($shopIds, $from);
        $agents = $this->agents($shopIds, $from, $to);

        $answered = $sessions->filter(fn (object $row) => $row->first_agent_at !== null);

        return [
            'interval'      => $interval,
            'from'          => $from->toDateString(),
            'to'            => $to->toDateString(),
            'days'          => $days,
            'bucket'        => $bucket,
            'conversations' => $sessions->count(),
            'answered'      => $answered->count(),
            'unanswered'    => $sessions->count() - $answered->count(),
            'open'          => $sessions->filter(fn (object $row) => in_array($row->status, self::OPEN_STATUSES, true))->count(),
            'median_reply_minutes' => $this->median($answered->map(fn (object $row) => $this->replyMinutes($row))),
            'slowest_reply_minutes' => $answered->map(fn (object $row) => $this->replyMinutes($row))->max(),
            'csat'          => $this->rounded($sessions->whereNotNull('rating')->avg('rating')),
            'csat_ratings'  => $sessions->whereNotNull('rating')->count(),
            'csat_by_month' => $this->csatByMonth($shopIds),
            'daily'         => $this->daily($sessions, $from, $to, $bucket, $openAtStart),
            'by_status'     => $this->byStatus($sessions),
            'by_channel'    => $this->byChannel($sessions),
            'by_shop'       => $this->byShop($sessions, $shopNames),
            'by_topic'      => $this->byTopic($sessions),
            'unclassified'  => $sessions->whereNull('topic')->count(),
            'noise'         => $this->noise($shopIds, $from, $to),
            'agents'        => $agents,
            'agents_total'  => [
                'name'          => __('Total'),
                'conversations' => $agents->sum('conversations'),
                'messages'      => $agents->sum('messages'),
                'website'       => $agents->sum('website'),
                'email'         => $agents->sum('email'),
                'whatsapp'      => $agents->sum('whatsapp'),
                'median_reply_minutes' => $this->median($answered->map(fn (object $row) => $this->replyMinutes($row))),
            ],
        ];
    }

    /**
     * @param  Collection<int, int>  $shopIds
     * @return Collection<int, object>
     */
    private function sessions(Collection $shopIds, Carbon $from, Carbon $to): Collection
    {
        return $this->fromEverySource(
            fn (array $source) => "
                select
                    s.shop_id,
                    {$source['channel']} as channel,
                    s.status,
                    s.topic,
                    s.rating,
                    s.created_at,
                    s.closed_at,
                    m.first_visitor_at,
                    m.first_agent_at
                from {$source['sessions']} s
                join (
                    select {$source['foreign']} as session_id,
                        min(created_at) filter (where sender_type in ('user', 'guest')) as first_visitor_at,
                        min(created_at) filter (where sender_type in ('agent', 'ai')) as first_agent_at
                    from {$source['messages']}
                    where deleted_at is null
                    group by 1
                ) m on m.session_id = s.id
                where s.deleted_at is null
                    and s.is_spam = false
                    and s.shop_id in (:shops)
                    and s.created_at between :from and :to
                    and m.first_visitor_at is not null
                    {$source['where']}
            ",
            $shopIds,
            ['from' => $from, 'to' => $to]
        );
    }

    /**
     * How the noise check did, by who gave the verdict: a rule or the model. Reversed is what a
     * person undid or overruled, in either direction, and is the number the confidence
     * threshold gets tuned on. Counted apart from everything else here because what was put
     * aside is, rightly, missing from every other cut.
     *
     * @param  Collection<int, int>  $shopIds
     * @return array<int, array{source: string, noise: int, genuine: int, reversed: int}>
     */
    private function noise(Collection $shopIds, Carbon $from, Carbon $to): array
    {
        return $this->fromEverySource(
            fn (array $source) => "
                select s.noise_source as source,
                    count(*) filter (where s.noise_verdict <> 'genuine') as noise,
                    count(*) filter (where s.noise_verdict = 'genuine') as genuine,
                    count(*) filter (where s.noise_reversed_at is not null) as reversed
                from {$source['sessions']} s
                where s.deleted_at is null
                    and s.noise_verdict is not null
                    and s.shop_id in (:shops)
                    and s.created_at between :from and :to
                group by 1
            ",
            $shopIds,
            ['from' => $from, 'to' => $to]
        )
            ->groupBy('source')
            ->map(fn (Collection $rows, string $source) => [
                'source'   => $source,
                'noise'    => (int) $rows->sum('noise'),
                'genuine'  => (int) $rows->sum('genuine'),
                'reversed' => (int) $rows->sum('reversed'),
            ])
            ->values()
            ->all();
    }

    /**
     * Conversations already open when the window starts, so the open line does not restart at zero.
     *
     * @param  Collection<int, int>  $shopIds
     */
    private function openBefore(Collection $shopIds, Carbon $from): int
    {
        $statuses = "'".implode("', '", self::OPEN_STATUSES)."'";

        return $this->fromEverySource(
            fn (array $source) => "
                select count(*) as total
                from {$source['sessions']} s
                where s.deleted_at is null
                    and s.is_spam = false
                    and s.shop_id in (:shops)
                    and s.created_at < :from
                    and s.status in ({$statuses})
                    {$source['where']}
            ",
            $shopIds,
            ['from' => $from]
        )->sum('total');
    }

    /**
     * @param  Collection<int, int>  $shopIds
     * @return Collection<int, array<string, mixed>>
     */
    private function agents(Collection $shopIds, Carbon $from, Carbon $to): Collection
    {
        $rows = $this->fromEverySource(
            fn (array $source) => "
                select
                    msg.sender_id as agent_id,
                    {$source['channel']} as channel,
                    count(*) as messages,
                    count(distinct s.id) as conversations,
                    percentile_cont(0.5) within group (
                        order by extract(epoch from msg.created_at - m.first_visitor_at) / 60
                    ) filter (where msg.created_at = m.first_agent_at) as median_reply_minutes
                from {$source['messages']} msg
                join {$source['sessions']} s on s.id = msg.{$source['foreign']}
                join (
                    select {$source['foreign']} as session_id,
                        min(created_at) filter (where sender_type in ('user', 'guest')) as first_visitor_at,
                        min(created_at) filter (where sender_type in ('agent', 'ai')) as first_agent_at
                    from {$source['messages']}
                    where deleted_at is null
                    group by 1
                ) m on m.session_id = s.id
                where msg.deleted_at is null
                    and msg.sender_type = 'agent'
                    and msg.sender_id is not null
                    and msg.created_at between :from and :to
                    and s.deleted_at is null
                    and s.is_spam = false
                    and s.shop_id in (:shops)
                    and m.first_visitor_at is not null
                    {$source['where']}
                group by 1, 2
            ",
            $shopIds,
            ['from' => $from, 'to' => $to]
        );

        $users = User::query()
            ->join('chat_agents', 'chat_agents.user_id', '=', 'users.id')
            ->whereIn('chat_agents.id', $rows->pluck('agent_id')->unique())
            ->select(['users.*', 'chat_agents.id as chat_agent_id'])
            ->get()
            ->keyBy('chat_agent_id');

        return $rows
            ->groupBy('agent_id')
            ->map(function (Collection $agentRows, int|string $agentId) use ($users) {
                $user = $users->get($agentId);
                $name = $user?->contact_name ?: $user?->username ?: __('Unknown');

                return [
                    'name'          => $name,
                    'short_name'    => strtok((string) $name, ' '),
                    'username'      => $user?->username,
                    'avatar'        => $user?->imageSources(48, 48),
                    'conversations' => (int) $agentRows->sum('conversations'),
                    'messages'      => (int) $agentRows->sum('messages'),
                    'website'       => (int) $agentRows->where('channel', 'website')->sum('conversations'),
                    'email'         => (int) $agentRows->where('channel', 'email')->sum('conversations'),
                    'whatsapp'      => (int) $agentRows->where('channel', 'whatsapp')->sum('conversations'),
                    'median_reply_minutes' => $this->median($agentRows->pluck('median_reply_minutes')->filter(fn ($value) => $value !== null)),
                ];
            })
            ->sortByDesc('conversations')
            ->values();
    }

    /**
     * Ratings are attributed to the month the conversation started: a session carries a rating
     * but no rating date.
     *
     * @param  Collection<int, int>  $shopIds
     */
    private function csatByMonth(Collection $shopIds): array
    {
        $rows = $this->fromEverySource(
            fn (array $source) => "
                select to_char(s.created_at, 'YYYY-MM') as month, s.rating
                from {$source['sessions']} s
                where s.deleted_at is null
                    and s.is_spam = false
                    and s.rating is not null
                    and s.shop_id in (:shops)
                    and s.created_at >= :from
                    {$source['where']}
            ",
            $shopIds,
            ['from' => now()->subMonths(11)->startOfMonth()]
        )->groupBy('month');

        return collect(range(11, 0))->map(function (int $back) use ($rows) {
            $month   = now()->subMonths($back)->format('Y-m');
            $ratings = $rows->get($month, collect());

            return [
                'month'   => $month,
                'average' => $ratings->isEmpty() ? null : $this->rounded($ratings->avg('rating')),
                'total'   => $ratings->count(),
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, object>  $sessions
     */
    private function daily(Collection $sessions, Carbon $from, Carbon $to, string $bucket, int $openAtStart): array
    {
        $started  = $this->countPerBucket($sessions, $bucket, fn (object $row) => $row->created_at);
        $answered = $this->countPerBucket($sessions, $bucket, fn (object $row) => $row->first_agent_at);
        $closed   = $this->countPerBucket($sessions, $bucket, fn (object $row) => $row->closed_at);

        $series = collect();
        $open   = $openAtStart;
        $cursor = $from->copy()->startOf($bucket);

        while ($cursor->lte($to)) {
            $day  = $cursor->toDateString();
            $open += ($started[$day] ?? 0) - ($closed[$day] ?? 0);

            $series->push([
                'date'     => $day,
                'started'  => $started[$day] ?? 0,
                'answered' => $answered[$day] ?? 0,
                'open'     => max(0, $open),
            ]);

            $cursor->add(1, $bucket);
        }

        return $series->all();
    }

    /**
     * @param  Collection<int, object>  $sessions
     * @return array<string, int>
     */
    private function countPerBucket(Collection $sessions, string $bucket, callable $date): array
    {
        return $sessions
            ->map(fn (object $row) => $date($row))
            ->filter()
            ->countBy(fn ($value) => Carbon::parse($value)->startOf($bucket)->toDateString())
            ->all();
    }

    /**
     * @param  Collection<int, object>  $sessions
     */
    private function byStatus(Collection $sessions): array
    {
        $totals = $sessions->countBy('status');

        return collect(ChatSessionStatusEnum::cases())
            ->map(fn (ChatSessionStatusEnum $status) => [
                'status' => $status->value,
                'label'  => ChatSessionStatusEnum::labels()[$status->value],
                'color'  => ChatSessionStatusEnum::stateIcon()[$status->value]['color'],
                'total'  => (int) ($totals[$status->value] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, object>  $sessions
     */
    private function byChannel(Collection $sessions): array
    {
        return collect(self::CHANNEL_LABELS)
            ->map(function (string $label, string $channel) use ($sessions) {
                $rows     = $sessions->where('channel', $channel);
                $answered = $rows->filter(fn (object $row) => $row->first_agent_at !== null);

                return [
                    'channel'       => $channel,
                    'label'         => __($label),
                    'conversations' => $rows->count(),
                    'answered'      => $answered->count(),
                    'unanswered'    => $rows->count() - $answered->count(),
                    'open'          => $rows->filter(fn (object $row) => in_array($row->status, self::OPEN_STATUSES, true))->count(),
                    'median_reply_minutes' => $this->median($answered->map(fn (object $row) => $this->replyMinutes($row))),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * What customers wrote in about, from the topic the summariser gave each conversation.
     * Greetings and tests are counted as their own line rather than hidden, so the share
     * column adds up to the conversations that have been classified.
     *
     * @param  Collection<int, object>  $sessions
     */
    private function byTopic(Collection $sessions): array
    {
        $classified = $sessions->whereNotNull('topic');
        $labels     = ChatTopicEnum::labels();

        return $classified
            ->groupBy('topic')
            ->map(function (Collection $rows, string $topic) use ($classified, $labels) {
                $answered = $rows->filter(fn (object $row) => $row->first_agent_at !== null);

                return [
                    'topic'         => $topic,
                    'label'         => $labels[$topic] ?? $topic,
                    'conversations' => $rows->count(),
                    'share'         => round($rows->count() / $classified->count() * 100, 1),
                    'unanswered'    => $rows->count() - $answered->count(),
                    'website'       => $rows->where('channel', 'website')->count(),
                    'email'         => $rows->where('channel', 'email')->count(),
                    'whatsapp'      => $rows->where('channel', 'whatsapp')->count(),
                    'median_reply_minutes' => $this->median($answered->map(fn (object $row) => $this->replyMinutes($row))),
                ];
            })
            ->sortByDesc('conversations')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, object>  $sessions
     */
    private function byShop(Collection $sessions, array $shopNames): array
    {
        return $sessions
            ->groupBy('shop_id')
            ->map(function (Collection $rows, int $shopId) use ($shopNames) {
                $answered = $rows->filter(fn (object $row) => $row->first_agent_at !== null);

                return [
                    'shop'          => $shopNames[$shopId]['name'] ?? __('Unknown'),
                    'slug'          => $shopNames[$shopId]['slug'] ?? null,
                    'conversations' => $rows->count(),
                    'answered'      => $answered->count(),
                    'unanswered'    => $rows->count() - $answered->count(),
                    'website'       => $rows->where('channel', 'website')->count(),
                    'email'         => $rows->where('channel', 'email')->count(),
                    'whatsapp'      => $rows->where('channel', 'whatsapp')->count(),
                    'median_reply_minutes' => $this->median($answered->map(fn (object $row) => $this->replyMinutes($row))),
                ];
            })
            ->sortByDesc('conversations')
            ->values()
            ->all();
    }

    private function replyMinutes(object $row): float
    {
        return Carbon::parse($row->first_visitor_at)->diffInSeconds(Carbon::parse($row->first_agent_at)) / 60;
    }

    /**
     * @param  Collection<int, int>  $shopIds
     */
    private function oldestConversationAt(Collection $shopIds): ?string
    {
        return $this->fromEverySource(
            fn (array $source) => "
                select min(s.created_at) as oldest
                from {$source['sessions']} s
                where s.deleted_at is null and s.shop_id in (:shops) {$source['where']}
            ",
            $shopIds
        )->pluck('oldest')->filter()->min();
    }

    /**
     * Runs the same shape of query against the website/email tables and the WhatsApp ones.
     *
     * @param  Collection<int, int>  $shopIds
     * @return Collection<int, object>
     */
    private function fromEverySource(callable $sql, Collection $shopIds, array $bindings = []): Collection
    {
        if ($shopIds->isEmpty()) {
            return collect();
        }

        $shops = $shopIds->map(fn ($id) => (int) $id)->implode(', ');

        return collect(self::SOURCES)->flatMap(
            fn (array $source) => DB::select(str_replace(':shops', $shops, $sql($source)), $bindings)
        );
    }

    /**
     * @param  Collection<int, float|null>  $values
     */
    private function median(Collection $values): ?float
    {
        $sorted = $values->filter(fn ($value) => $value !== null)->sort()->values();

        if ($sorted->isEmpty()) {
            return null;
        }

        $middle = (int) floor($sorted->count() / 2);

        return $this->rounded($sorted->count() % 2
            ? $sorted[$middle]
            : ($sorted[$middle - 1] + $sorted[$middle]) / 2);
    }

    private function rounded(float|int|string|null $value): ?float
    {
        return $value === null ? null : round((float) $value, 1);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function range(string $interval, ?string $oldestCreatedAt): array
    {
        return match ($interval) {
            '1h'    => [now()->subHour(), now()],
            '3h'    => [now()->subHours(3), now()],
            '24h'   => [now()->subDay(), now()],
            'tdy'   => [now()->startOfDay(), now()->endOfDay()],
            'ld'    => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            '3d'    => [now()->subDays(3)->startOfDay(), now()->endOfDay()],
            '1w'    => [now()->subWeek()->startOfDay(), now()->endOfDay()],
            'lw'    => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            '1m'    => [now()->subMonth()->startOfDay(), now()->endOfDay()],
            'lm'    => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            '1q'    => [now()->subQuarter()->startOfDay(), now()->endOfDay()],
            '1y'    => [now()->subYear()->startOfDay(), now()->endOfDay()],
            default => [Carbon::parse($oldestCreatedAt ?? now())->startOfDay(), now()->endOfDay()],
        };
    }

    /**
     * @return array<string, string>
     */
    public function intervalOptions(): array
    {
        return [
            'all' => __('All'),
            '24h' => __('24 hours'),
            'tdy' => __('Today'),
            'ld'  => __('Yesterday'),
            '3d'  => __('3 days'),
            '1w'  => __('1 week'),
            'lw'  => __('Last week'),
            '1m'  => __('1 month'),
            'lm'  => __('Last month'),
            '1q'  => __('1 quarter'),
            '1y'  => __('1 year'),
        ];
    }

    public function intervalFromRequest(): string
    {
        $interval = (string) request()->input('created');

        return array_key_exists($interval, $this->intervalOptions()) ? $interval : '1m';
    }
}
