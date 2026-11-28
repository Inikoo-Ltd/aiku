<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\UI;

use App\Actions\DevOps\UI\ShowDevopsDashboard;
use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackEmoji;
use App\Models\Chat\SlackFile;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackSyncRun;
use App\Models\Chat\SlackUser;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * One screen for engineers that says whether Slack messages are still arriving. The counts over all messages and
 * reactions are cached for a few minutes; everything a warning depends on is read live.
 */
class ShowSlackSyncHealth extends OrgAction
{
    public const string STATS_CACHE_KEY = 'slack-sync-health-stats';

    public const int STATS_CACHE_SECONDS = 600;

    public const int WORKDAY_START_HOUR_UTC = 7;

    public const int WORKDAY_END_HOUR_UTC = 16;

    public const int QUIET_WORKING_MINUTES = 180;

    public const int LATE_AFTER_HOURS = 26;

    public const int STUCK_AFTER_HOURS = 6;

    public const array NIGHTLY_COMMANDS = [
        'slack:sync-public-channels'    => '02:40',
        'slack:sync-users'              => '03:40',
        'slack:sync-channel-members'    => '03:45',
        'slack:sync-pins-and-bookmarks' => '03:50',
        'slack:sync-emojis'             => '03:55',
        'slack:sync-private-channels'   => '04:00',
        'slack:bring-direct-history'    => '04:20',
    ];

    private const string STATUS_SQL = "case when slack_channels.is_ignored then 'ignored' when slack_channels.deleted_in_slack_at is not null then 'deleted' when not slack_channels.is_public then 'private' when slack_channels.is_archived then 'archived' when not slack_channels.is_member then 'not_joined' else 'kept' end";

    public function authorize(ActionRequest $request): bool
    {
        return Ticket::canBeManagedBy($request->user()) && $request->user()->hasGroupAccess();
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle();
    }

    public function handle(?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $stats = DB::query()->fromRaw(
            'jsonb_to_recordset(?::jsonb) as stats(slack_channel_id int, messages int, oldest timestamptz, newest timestamptz)',
            [json_encode($this->messageStats()['channels'])]
        );

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where('slack_channels.is_public', true)->where('slack_channels.name', 'ilike', '%'.$value.'%');
        });

        return QueryBuilder::for(
            SlackChannel::query()
                ->where('slack_channels.group_id', $this->group->id)
                ->leftJoinSub($stats, 'stats', 'stats.slack_channel_id', '=', 'slack_channels.id')
                ->select([
                    'slack_channels.id', DB::raw('case when slack_channels.is_public then slack_channels.name end as name'), 'slack_channels.history_synced_at',
                    DB::raw('coalesce(stats.messages, 0) as messages'), 'stats.oldest', 'stats.newest',
                    DB::raw(self::STATUS_SQL.' as status'),
                ])
        )
            ->defaultSort('-messages')
            ->allowedSorts([
                'name', 'messages', 'status',
                ...array_map(fn (string $column) => AllowedSort::callback($column, fn ($query, bool $descending) => $query->orderByRaw($column.' '.($descending ? 'desc' : 'asc').' nulls last')), ['oldest', 'newest', 'history_synced_at']),
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * @return array{channels: array<int, array{slack_channel_id: int, messages: int, oldest: string|null, newest: string|null}>, reactions: int}
     */
    public function messageStats(): array
    {
        return Cache::remember(self::STATS_CACHE_KEY, self::STATS_CACHE_SECONDS, fn () => [
            'channels'  => SlackMessage::query()
                ->selectRaw('slack_channel_id, count(*) as messages, min(sent_at) as oldest, max(sent_at) as newest')
                ->groupBy('slack_channel_id')
                ->toBase()->get()
                ->map(fn (object $row) => [
                    'slack_channel_id' => (int) $row->slack_channel_id,
                    'messages'         => (int) $row->messages,
                    'oldest'           => $row->oldest,
                    'newest'           => $row->newest,
                ])->all(),
            'reactions' => DB::table('slack_message_reactions')->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Group $group): array
    {
        $stats    = $this->messageStats();
        $channels = SlackChannel::where('group_id', $group->id)->toBase()->selectRaw(
            'count(*) filter (where is_public and not is_ignored and deleted_in_slack_at is null and not is_archived and is_member) as kept,
             count(*) filter (where is_public and not is_ignored and deleted_in_slack_at is null and not is_archived and not is_member) as not_joined,
             count(*) filter (where is_archived and not is_ignored) as archived,
             count(*) filter (where is_ignored) as ignored'
        )->first();
        $people = SlackUser::toBase()->selectRaw(
            'count(*) filter (where not is_bot and not is_deleted) as active,
             count(*) filter (where not is_bot and not is_deleted and user_id is not null) as linked'
        )->first();
        $files = SlackFile::toBase()->selectRaw('state, count(*) as files, coalesce(sum(size), 0) as size, count(*) filter (where disk is not null) as in_bucket')
            ->groupBy('state')->get();

        return [
            'channels_kept'     => (int) $channels->kept,
            'channels_not_joined' => (int) $channels->not_joined,
            'channels_archived' => (int) $channels->archived,
            'channels_ignored'  => (int) $channels->ignored,
            'messages'          => array_sum(array_column($stats['channels'], 'messages')),
            'messages_last_day' => SlackMessage::where('sent_at', '>=', now()->subDay())->count(),
            'newest_message_at' => $this->newestMessageAt()?->toIso8601String(),
            'people_active'     => (int) $people->active,
            'people_linked'     => (int) $people->linked,
            'files'             => $files->sortByDesc('files')->map(fn (object $row) => ['state' => $row->state, 'files' => (int) $row->files])->values()->all(),
            'files_total'       => (int) $files->sum('files'),
            'files_size'        => (int) $files->sum('size'),
            'files_in_bucket'   => (int) $files->sum('in_bucket'),
            'files_failed'      => (int) $files->firstWhere('state', 'failed')?->files,
            'emojis'            => SlackEmoji::whereNull('removed_in_slack_at')->count(),
            'reactions'         => $stats['reactions'],
            'cached_seconds'    => self::STATS_CACHE_SECONDS,
        ];
    }

    private function newestMessageAt(): ?Carbon
    {
        $newest = SlackMessage::max('sent_at');

        return $newest ? Carbon::parse($newest) : null;
    }

    /**
     * @return array<int, array{command: string, scheduled_at: string, status: string, started_at: string|null, finished_at: string|null, error: string|null, totals: array<string, mixed>|null}>
     */
    public function nightlyRuns(): array
    {
        $latest = SlackSyncRun::query()
            ->fromRaw('(select distinct on (command) * from slack_sync_runs order by command, id desc) as slack_sync_runs')
            ->get()->keyBy('command');

        return collect(self::NIGHTLY_COMMANDS)->map(function (string $scheduledAt, string $command) use ($latest) {
            /** @var SlackSyncRun|null $run */
            $run    = $latest->get($command);
            $totals = $run?->totals ?? ($run && $command === 'slack:sync-public-channels' && $run->finished_at && !$run->error
                ? ['channels' => $run->channels, 'messages_read' => $run->messages_read, 'messages_added' => $run->messages_added]
                : null);

            return [
                'command'      => $command,
                'scheduled_at' => $scheduledAt,
                'status'       => $this->runStatus($run),
                'started_at'   => $run?->started_at?->toIso8601String(),
                'finished_at'  => $run?->finished_at?->toIso8601String(),
                'error'        => $run?->error,
                'totals'       => $totals,
            ];
        })->values()->all();
    }

    private function runStatus(?SlackSyncRun $run): string
    {
        return match (true) {
            $run === null                                                                 => 'not_recorded',
            $run->error !== null                                                          => 'failed',
            $run->finished_at === null && $run->started_at->lt(now()->subHours(self::STUCK_AFTER_HOURS)) => 'stuck',
            $run->finished_at === null                                                    => 'running',
            $run->started_at->lt(now()->subHours(self::LATE_AFTER_HOURS))                 => 'late',
            default                                                                       => 'ok',
        };
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<int, array<string, mixed>>  $runs
     * @return array<int, array<string, mixed>>
     */
    public function warnings(array $summary, array $runs): array
    {
        $warnings     = [];
        $newest       = $summary['newest_message_at'] ? Carbon::parse($summary['newest_message_at']) : null;
        $quietMinutes = $newest ? self::workingMinutesBetween($newest, now()) : null;

        if ($newest === null || $quietMinutes >= self::QUIET_WORKING_MINUTES) {
            $warnings[] = ['type' => 'quiet', 'since' => $newest?->toIso8601String(), 'working_hours' => $quietMinutes === null ? null : intdiv($quietMinutes, 60)];
        }

        foreach ($runs as $run) {
            if (in_array($run['status'], ['failed', 'stuck', 'late'], true)
                || ($run['status'] === 'not_recorded' && $run['command'] === 'slack:sync-public-channels')) {
                $warnings[] = ['type' => 'run_'.$run['status'], 'command' => $run['command'], 'started_at' => $run['started_at'], 'error' => $run['error']];
            }
        }

        if ($summary['files_failed'] > 0) {
            $warnings[] = ['type' => 'files_failed', 'count' => $summary['files_failed']];
        }

        return $warnings;
    }

    /**
     * Minutes between two moments that fall in working hours (Monday to Friday, UTC), where messages normally
     * arrive every few minutes; nights and weekends are quiet, so they do not count.
     */
    public static function workingMinutesBetween(Carbon $from, Carbon $to): int
    {
        $from    = $from->copy()->utc()->max($to->copy()->utc()->subDays(31));
        $to      = $to->copy()->utc();
        $minutes = 0;

        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            if ($day->isWeekend()) {
                continue;
            }
            $start = $day->copy()->setTime(self::WORKDAY_START_HOUR_UTC, 0)->max($from);
            $end   = $day->copy()->setTime(self::WORKDAY_END_HOUR_UTC, 0)->min($to);
            if ($end->gt($start)) {
                $minutes += (int) $start->diffInMinutes($end);
            }
        }

        return $minutes;
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->column(key: 'name', label: __('Channel'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'status', label: __('State'), sortable: true)
                ->column(key: 'messages', label: __('Messages'), sortable: true, type: 'number')
                ->column(key: 'oldest', label: __('Oldest message'), sortable: true)
                ->column(key: 'newest', label: __('Newest message'), sortable: true)
                ->column(key: 'history_synced_at', label: __('Last catch-up'), sortable: true)
                ->column(key: 'history_complete', label: __('History'))
                ->defaultSort('-messages');
        };
    }

    public function htmlResponse(LengthAwarePaginator $channels, ActionRequest $request): Response
    {
        $channels->getCollection()->transform(fn (SlackChannel $channel) => [
            'id'                => $channel->id,
            'name'              => $channel->name,
            'status'            => $channel->getAttribute('status'),
            'messages'          => (int) $channel->getAttribute('messages'),
            'oldest'            => $channel->getAttribute('oldest') ? Carbon::parse($channel->getAttribute('oldest'))->toIso8601String() : null,
            'newest'            => $channel->getAttribute('newest') ? Carbon::parse($channel->getAttribute('newest'))->toIso8601String() : null,
            'history_synced_at' => $channel->history_synced_at?->toIso8601String(),
            'history_complete'  => $channel->history_synced_at !== null,
        ]);

        $summary = $this->summary($this->group);
        $runs    = $this->nightlyRuns();
        $title   = __('Slack sync health');

        return Inertia::render('Chat/SlackSyncHealth', [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'title'       => $title,
            'pageHead'    => [
                'title' => $title,
                'icon'  => ['icon' => ['fal', 'fa-comments'], 'title' => $title],
            ],
            'summary'     => $summary,
            'runs'        => $runs,
            'warnings'    => $this->warnings($summary, $runs),
            'data'        => JsonResource::collection($channels),
            'writing'     => GetSlackWritingFromAiku::make()->handle($this->group),
            'writing_channels' => JsonResource::collection(
                GetSlackWritingFromAiku::make()->channels($this->group)->through(fn (SlackChannel $channel) => [
                    'id'        => $channel->id,
                    'name'      => $channel->name,
                    'messages'  => (int) $channel->getAttribute('messages'),
                    'from_aiku' => (int) $channel->getAttribute('from_aiku'),
                    'share'     => (float) $channel->getAttribute('share'),
                ])
            ),
        ])->table($this->tableStructure())->table(GetSlackWritingFromAiku::make()->tableStructure());
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowDevopsDashboard::make()->getBreadcrumbs([]),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => ['name' => 'grp.devops.slack_sync'],
                        'label' => __('Slack sync'),
                    ],
                ],
            ]
        );
    }
}
