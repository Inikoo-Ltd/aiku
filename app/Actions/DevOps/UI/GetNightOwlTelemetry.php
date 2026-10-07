<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Oct 2026 16:10:00 Coordinated Universal Time
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\UI;

use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use NightOwl\Support\QueryHistogram;

class GetNightOwlTelemetry
{
    /** @var array<string, array{0: string, 1: string, 2: int}> range => [chart rollup table suffix, top-N rollup table suffix, minutes] */
    public const array RANGES = [
        '1h'  => ['rollups', 'rollups', 60],
        '24h' => ['hourly_rollups', 'hourly_rollups', 1440],
        '7d'  => ['hourly_rollups', 'daily_rollups', 10080],
    ];

    public const int TOP = 25;

    /** @var array{0: int, 1: int} seconds fresh, seconds served stale while refreshing */
    public const array CACHE_TTL = [90, 900];

    public function db(): Connection
    {
        return DB::connection('nightowl');
    }

    public static function range(?string $range): string
    {
        return array_key_exists((string) $range, self::RANGES) ? $range : '24h';
    }

    /** @return array<string, mixed> */
    public function overview(string $range): array
    {
        return Cache::flexible(self::cacheKey($range), self::CACHE_TTL, fn () => $this->build($range));
    }

    public static function cacheKey(string $range): string
    {
        return "devops-telemetry-$range";
    }

    /** @return array<string, mixed> */
    public function build(string $range): array
    {
        [$chartSuffix, $topSuffix, $minutes] = self::RANGES[$range];
        $since    = now()->subMinutes($minutes)->toDateTimeString();
        $topSince = $topSuffix === 'daily_rollups' ? now()->subMinutes($minutes)->startOfDay()->toDateTimeString() : $since;

        return [
            'range'          => $range,
            'dataSince'      => $this->db()->table('nightowl_request_hourly_rollups')->min('bucket_start'),
            'requests'       => $this->series("nightowl_request_$chartSuffix", $since, 'sum(success_count) as ok, sum(client_error_count) as client_errors, sum(server_error_count) as server_errors'),
            'jobs'           => $this->series("nightowl_job_$chartSuffix", $since, 'sum(processed_count) as processed, sum(failed_count) as failed, sum(released_count) as released'),
            'exceptions'     => $this->db()->table("nightowl_exception_$chartSuffix")->where('bucket_start', '>=', $since)
                ->groupBy('bucket_start')->orderBy('bucket_start')
                ->selectRaw('bucket_start, sum(handled_count) as handled, sum(unhandled_count) as unhandled')->get()->all(),
            'routes'         => $this->top("nightowl_request_$topSuffix", $topSince, "min(route_methods || ' ' || coalesce(route_path, ''))", 'sum(server_error_count) as server_errors, sum(client_error_count) as client_errors'),
            'jobClasses'     => $this->top("nightowl_job_$topSuffix", $topSince, 'min(job_class)', 'sum(failed_count) as failed, sum(released_count) as released, min(queue) as queue'),
            'commands'       => $this->top("nightowl_command_$topSuffix", $topSince, 'min(command)', 'sum(unsuccessful_count) as failed'),
            'scheduledTasks' => $this->top("nightowl_scheduled_task_$topSuffix", $topSince, 'min(command)', 'sum(failed_count) as failed, sum(skipped_count) as skipped, min(expression) as expression'),
            'queries'        => $this->top("nightowl_query_$topSuffix", $topSince, 'min(sql_query)', 'min(connection) as connection'),
            'outgoing'       => $this->top("nightowl_outgoing_request_$topSuffix", $topSince, 'min(host)', 'sum(client_error_count) as client_errors, sum(server_error_count) as server_errors'),
            'cache'          => $this->db()->table("nightowl_cache_$topSuffix")->where('bucket_start', '>=', $topSince)
                ->selectRaw('coalesce(sum(hits), 0) as hits, coalesce(sum(misses), 0) as misses, coalesce(sum(writes), 0) as writes, coalesce(sum(fails), 0) as fails')->first(),
            'exceptionGroups' => $this->exceptionGroups("nightowl_exception_$topSuffix", $topSince),
            'slowRequests'   => $this->slowRequests(),
            'slowJobs'       => $this->slowJobs(),
            'slowCommands'   => $this->slowCommands(),
        ];
    }

    public function histogramSums(): string
    {
        return collect(QueryHistogram::columns())->map(fn (string $column) => "coalesce(sum($column), 0) as $column")->implode(', ');
    }

    /** @return array<string, mixed> */
    public function withPercentiles(object $row): array
    {
        $bins = array_map(fn (string $column) => (int) $row->$column, QueryHistogram::columns());
        $row  = array_diff_key((array) $row, array_flip(QueryHistogram::columns()));

        return [
            ...$row,
            'calls'          => (int) $row['calls'],
            'total_duration' => (int) $row['total_duration'],
            'avg'            => $row['calls'] ? (int) ($row['total_duration'] / $row['calls']) : 0,
            'p95'            => (int) QueryHistogram::estimatePercentile($bins, 0.95, null, (int) $row['max_duration']),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function series(string $table, string $since, string $counts): array
    {
        return $this->db()->table($table)->where('bucket_start', '>=', $since)
            ->groupBy('bucket_start')->orderBy('bucket_start')
            ->selectRaw("bucket_start, sum(call_count) as calls, sum(total_duration) as total_duration, max(max_duration) as max_duration, $counts, {$this->histogramSums()}")
            ->get()->map(fn (object $row) => $this->withPercentiles($row))->all();
    }

    /** @return list<array<string, mixed>> */
    public function top(string $table, string $since, string $label, string $extra): array
    {
        return $this->db()->table($table)->where('bucket_start', '>=', $since)
            ->groupBy('group_hash')
            ->orderByRaw('sum(total_duration) desc')
            ->limit(self::TOP * 2)
            ->selectRaw("group_hash, $label as label, sum(call_count) as calls, sum(total_duration) as total_duration, max(max_duration) as max_duration, $extra, {$this->histogramSums()}")
            ->get()->map(fn (object $row) => $this->withPercentiles($row))->all();
    }

    /** @return list<object> */
    public function slowRequests(): array
    {
        return $this->db()->table('nightowl_requests_v2 as request')
            ->leftJoin('nightowl_dict_route as route', 'route.id', '=', 'request.route_id')
            ->where('request.created_at', '>=', now()->subHour()->toDateTimeString())
            ->orderByDesc('request.duration')->limit(self::TOP)
            ->get(['request.id', 'request.created_at', 'request.url', 'request.status_code', 'request.duration', 'request.queries', 'request.cache_events', 'request.outgoing_requests', 'route.name as route_name', 'route.method'])
            ->all();
    }

    /** @return list<object> */
    public function slowJobs(): array
    {
        return $this->db()->table('nightowl_jobs_v2 as job')
            ->leftJoin('nightowl_dict_string as class', 'class.id', '=', 'job.job_class_id')
            ->leftJoin('nightowl_dict_string as queue', 'queue.id', '=', 'job.queue_id')
            ->leftJoin('nightowl_dict_string as status', 'status.id', '=', 'job.status_id')
            ->where('job.created_at', '>=', now()->subHour()->toDateTimeString())
            ->orderByDesc('job.duration')->limit(self::TOP)
            ->get(['job.id', 'job.created_at', 'class.value as job_class', 'queue.value as queue', 'status.value as status', 'job.attempt', 'job.duration', 'job.queries', 'job.cache_events', 'job.outgoing_requests'])
            ->all();
    }

    /** @return list<object> */
    public function slowCommands(): array
    {
        return $this->db()->table('nightowl_commands_v2')
            ->where('created_at', '>=', now()->subDay()->toDateTimeString())
            ->orderByDesc('duration')->limit(self::TOP)
            ->get(['id', 'created_at', 'name', 'command', 'exit_code', 'duration', 'queries', 'cache_events', 'outgoing_requests'])
            ->all();
    }

    public const int SPAN_LIMIT = 500;

    /** @return array{summary: array<string, mixed>, stages: list<array{label: string, duration: int}>, spans: list<array<string, mixed>>, truncated: bool}|null */
    public function trace(string $kind, int $id, string $createdAt): ?array
    {
        $createdAt = Carbon::parse($createdAt);
        $execution = match ($kind) {
            'request' => $this->requestExecution($id, $createdAt),
            'job'     => $this->jobExecution($id, $createdAt),
            'command' => $this->commandExecution($id, $createdAt),
            default   => null,
        };

        if (! $execution) {
            return null;
        }

        [$summary, $stages, $executionId, $startUs] = $execution;
        if (! $executionId) {
            return ['summary' => $summary, 'stages' => $stages, 'spans' => [], 'truncated' => false];
        }

        $window  = [$createdAt->copy()->subMinute()->toDateTimeString(), $createdAt->copy()->addMicroseconds((int) $summary['duration'])->addMinutes(5)->toDateTimeString()];
        $inTrace = fn ($query) => $query->whereBetween('span.created_at', $window)
            ->where('span.execution_id', $executionId)
            ->orderBy('span.ts_us')->limit(self::SPAN_LIMIT);

        $groups = [
            $this->db()->query()->fromSub($inTrace($this->db()->table('nightowl_queries_v2 as span'))->select('span.ts_us', 'span.duration', 'span.sql_id'), 'span')
                ->join('nightowl_dict_sql as sql', 'sql.id', '=', 'span.sql_id')
                ->selectRaw("'query' as type, span.ts_us, span.duration, sql.sql as label, sql.file, sql.line")->get(),
            $inTrace($this->db()->table('nightowl_cache_events_v2 as span')->leftJoin('nightowl_dict_string as event', 'event.id', '=', 'span.event_type_id'))
                ->selectRaw("'cache' as type, span.ts_us, span.duration, coalesce(event.value, '') || ' ' || span.key as label, null as file, null as line")->get(),
            $inTrace($this->db()->table('nightowl_outgoing_requests_v2 as span')->leftJoin('nightowl_dict_string as method', 'method.id', '=', 'span.method_id'))
                ->selectRaw("'http' as type, span.ts_us, span.duration, coalesce(method.value, '') || ' ' || span.url || ' ' || span.status_code as label, null as file, null as line")->get(),
            $inTrace($this->db()->table('nightowl_logs_v2 as span')->leftJoin('nightowl_dict_string as level', 'level.id', '=', 'span.level_id'))
                ->selectRaw("'log' as type, span.ts_us, 0 as duration, upper(coalesce(level.value, '')) || ': ' || left(span.message, 300) as label, null as file, null as line")->get(),
            $inTrace($this->db()->table('nightowl_exceptions_v2 as span'))
                ->selectRaw("'exception' as type, span.ts_us, 0 as duration, span.class || ': ' || left(span.message, 300) as label, span.file, span.line")->get(),
        ];

        return [
            'summary'   => $summary,
            'stages'    => $stages,
            'truncated' => collect($groups)->contains(fn ($group) => $group->count() >= self::SPAN_LIMIT),
            'spans'     => collect($groups)->flatten(1)
                ->map(fn (object $span) => [...(array) $span, 'offset' => (int) $span->ts_us - $startUs, 'duration' => (int) $span->duration])
                ->sortBy('offset')->values()->all(),
        ];
    }

    /** @return array<int, array{label: string, duration: int}> */
    public function stages(object $row, array $labels): array
    {
        return collect($labels)->map(fn (string $label, string $column) => ['label' => $label, 'duration' => (int) $row->$column])->filter(fn (array $stage) => $stage['duration'] > 0)->values()->all();
    }

    /** @return array{0: array<string, mixed>, 1: list<array{label: string, duration: int}>, 2: ?string, 3: int}|null */
    public function requestExecution(int $id, Carbon $createdAt): ?array
    {
        $request = $this->db()->table('nightowl_requests_v2 as request')
            ->leftJoin('nightowl_dict_route as route', 'route.id', '=', 'request.route_id')
            ->where('request.id', $id)->where('request.created_at', $createdAt->toDateTimeString())
            ->first([
                'request.created_at', 'request.ts_us', 'request.trace_id', 'request.url', 'request.status_code', 'request.duration', 'request.user_id',
                'request.bootstrap', 'request.before_middleware', 'request.action', 'request.render', 'request.after_middleware', 'request.sending', 'request.terminating',
                'request.peak_memory_usage', 'request.exception_preview', 'route.name as route_name', 'route.method',
            ]);

        return $request ? [
            [
                'kind'              => 'request',
                'title'             => trim($request->method.' '.$request->url),
                'subtitle'          => $request->route_name,
                'status'            => (string) $request->status_code,
                'failed'            => $request->status_code >= 500,
                'duration'          => (int) $request->duration,
                'created_at'        => $request->created_at,
                'user_id'           => $request->user_id,
                'peak_memory_usage' => (int) $request->peak_memory_usage,
                'exception_preview' => $request->exception_preview,
            ],
            $this->stages($request, [
                'bootstrap' => __('Bootstrap'), 'before_middleware' => __('Middleware'), 'action' => __('Controller'), 'render' => __('Render'),
                'after_middleware' => __('After middleware'), 'sending' => __('Sending'), 'terminating' => __('Terminating'),
            ]),
            $request->trace_id,
            (int) $request->ts_us,
        ] : null;
    }

    /** @return array{0: array<string, mixed>, 1: list<array{label: string, duration: int}>, 2: ?string, 3: int}|null */
    public function jobExecution(int $id, Carbon $createdAt): ?array
    {
        $job = $this->db()->table('nightowl_jobs_v2 as job')
            ->leftJoin('nightowl_dict_string as class', 'class.id', '=', 'job.job_class_id')
            ->leftJoin('nightowl_dict_string as queue', 'queue.id', '=', 'job.queue_id')
            ->leftJoin('nightowl_dict_string as status', 'status.id', '=', 'job.status_id')
            ->where('job.id', $id)->where('job.created_at', $createdAt->toDateTimeString())
            ->first(['job.created_at', 'job.ts_us', 'job.attempt_id', 'job.attempt', 'job.duration', 'job.user_id', 'job.peak_memory_usage', 'job.exception_preview', 'class.value as job_class', 'queue.value as queue', 'status.value as status']);

        return $job ? [
            [
                'kind'              => 'job',
                'title'             => $job->job_class,
                'subtitle'          => trim(__('Queue').' '.$job->queue.' · '.__('attempt').' '.$job->attempt),
                'status'            => $job->status,
                'failed'            => $job->status === 'failed',
                'duration'          => (int) $job->duration,
                'created_at'        => $job->created_at,
                'user_id'           => $job->user_id,
                'peak_memory_usage' => (int) $job->peak_memory_usage,
                'exception_preview' => $job->exception_preview,
            ],
            [],
            $job->attempt_id,
            (int) $job->ts_us,
        ] : null;
    }

    /** @return array{0: array<string, mixed>, 1: list<array{label: string, duration: int}>, 2: ?string, 3: int}|null */
    public function commandExecution(int $id, Carbon $createdAt): ?array
    {
        $command = $this->db()->table('nightowl_commands_v2')
            ->where('id', $id)->where('created_at', $createdAt->toDateTimeString())
            ->first(['created_at', 'ts_us', 'trace_id', 'name', 'command', 'exit_code', 'duration', 'user_id', 'bootstrap', 'action', 'terminating', 'peak_memory_usage', 'exception_preview']);

        return $command ? [
            [
                'kind'              => 'command',
                'title'             => $command->command ?: $command->name,
                'subtitle'          => $command->name,
                'status'            => __('exit').' '.$command->exit_code,
                'failed'            => (int) $command->exit_code !== 0,
                'duration'          => (int) $command->duration,
                'created_at'        => $command->created_at,
                'user_id'           => $command->user_id,
                'peak_memory_usage' => (int) $command->peak_memory_usage,
                'exception_preview' => $command->exception_preview,
            ],
            $this->stages($command, ['bootstrap' => __('Bootstrap'), 'action' => __('Command'), 'terminating' => __('Terminating')]),
            $command->trace_id,
            (int) $command->ts_us,
        ] : null;
    }

    /** @return list<object> */
    public function exceptionGroups(string $table, string $since): array
    {
        return $this->db()->query()
            ->fromSub(
                $this->db()->table($table)->where('bucket_start', '>=', $since)->groupBy('fingerprint')
                    ->selectRaw('fingerprint, sum(call_count) as occurrences, sum(handled_count) as handled, sum(unhandled_count) as unhandled, sum(authenticated_count) as authenticated, max(bucket_start) as last_bucket')
                    ->orderByDesc('occurrences')->limit(self::TOP * 2),
                'exception_group'
            )
            ->leftJoin('nightowl_issues as issue', fn ($join) => $join->on('issue.group_hash', '=', 'exception_group.fingerprint')->where('issue.type', 'exception'))
            ->orderByDesc('exception_group.occurrences')
            ->get([
                'exception_group.fingerprint', 'exception_group.occurrences', 'exception_group.handled', 'exception_group.unhandled', 'exception_group.authenticated', 'exception_group.last_bucket',
                'issue.exception_class', 'issue.exception_message', 'issue.status', 'issue.priority', 'issue.first_seen_at', 'issue.last_seen_at', 'issue.occurrences_count', 'issue.users_count',
            ])->all();
    }

    /** @return array<string, mixed>|null */
    public function exception(string $fingerprint, ?int $occurrenceId = null, ?string $occurrenceAt = null): ?array
    {
        if (! preg_match('/^[0-9a-f]{32}$/', $fingerprint)) {
            return null;
        }

        $since       = now()->subDays(7)->startOfHour()->toDateTimeString();
        $occurrences = $this->db()->table('nightowl_exceptions_v2 as exception')
            ->leftJoin('nightowl_dict_string as server', 'server.id', '=', 'exception.server_id')
            ->leftJoin('nightowl_dict_string as source', 'source.id', '=', 'exception.execution_source_id')
            ->whereRaw("exception.fingerprint = decode(?, 'hex')", [$fingerprint])
            ->where('exception.created_at', '>=', $since)
            ->orderByDesc('exception.created_at')->limit(self::TOP)
            ->get(['exception.id', 'exception.created_at', 'exception.handled', 'exception.user_id', 'exception.execution_preview', 'server.value as server', 'source.value as source']);

        $occurrence = $this->db()->table('nightowl_exceptions_v2 as exception')
            ->leftJoin('nightowl_dict_string as server', 'server.id', '=', 'exception.server_id')
            ->leftJoin('nightowl_dict_string as source', 'source.id', '=', 'exception.execution_source_id')
            ->leftJoin('nightowl_dict_string as stage', 'stage.id', '=', 'exception.execution_stage_id')
            ->leftJoin('nightowl_dict_trace as stack', 'stack.id', '=', 'exception.trace_ref')
            ->whereRaw("exception.fingerprint = decode(?, 'hex')", [$fingerprint])
            ->when(
                $occurrenceId && $occurrenceAt,
                fn ($query) => $query->where('exception.id', $occurrenceId)->where('exception.created_at', Carbon::parse($occurrenceAt)->toDateTimeString()),
                fn ($query) => $query->where('exception.created_at', '>=', $since)->orderByDesc('exception.created_at')
            )
            ->first([
                'exception.id', 'exception.created_at', 'exception.execution_id', 'exception.class', 'exception.message', 'exception.code', 'exception.file', 'exception.line',
                'exception.handled', 'exception.user_id', 'exception.execution_preview', 'exception.php_version', 'exception.laravel_version',
                'server.value as server', 'source.value as source', 'stage.value as stage', 'stack.trace_z',
            ]);

        $issue = $this->db()->table('nightowl_issues')->where('group_hash', $fingerprint)->where('type', 'exception')
            ->first(['id', 'status', 'priority', 'exception_class', 'exception_message', 'first_seen_at', 'last_seen_at', 'occurrences_count', 'users_count', 'assigned_to']);

        if (! $occurrence && ! $issue) {
            return null;
        }

        return [
            'fingerprint' => $fingerprint,
            'issue'       => $issue,
            'activity'    => $issue ? $this->db()->table('nightowl_issue_activity')->where('issue_id', $issue->id)->orderByDesc('id')->limit(10)
                ->get(['action', 'old_value', 'new_value', 'user_name', 'actor_type', 'created_at'])->all() : [],
            'hourly'      => $this->db()->table('nightowl_exception_hourly_rollups')->where('fingerprint', $fingerprint)->where('bucket_start', '>=', $since)
                ->groupBy('bucket_start')->orderBy('bucket_start')
                ->selectRaw('bucket_start, sum(handled_count) as handled, sum(unhandled_count) as unhandled')->get()->all(),
            'servers'     => $this->db()->table('nightowl_exception_server_hourly_rollups')->where('fingerprint', $fingerprint)->where('bucket_start', '>=', $since)
                ->groupBy('server')->orderByRaw('sum(call_count) desc')
                ->selectRaw('server, sum(call_count) as occurrences')->get()->all(),
            'occurrences' => $occurrences->all(),
            'occurrence'  => $occurrence ? [
                ...array_diff_key((array) $occurrence, ['trace_z' => true, 'execution_id' => true]),
                'frames'    => $this->stackFrames($occurrence->trace_z),
                'execution' => $this->executionOf($occurrence),
            ] : null,
        ];
    }

    /** @return list<array{file: string, source: string, code: array<int|string, string>|null, is_vendor: bool}> */
    public function stackFrames(mixed $compressed): array
    {
        $compressed = is_resource($compressed) ? stream_get_contents($compressed) : $compressed;
        $json       = $compressed ? @gzinflate($compressed) : false;
        $frames     = $json ? json_decode($json, true) : null;

        return collect(is_array($frames) ? $frames : [])->map(fn (array $frame) => [
            'file'      => (string) ($frame['file'] ?? ''),
            'source'    => (string) ($frame['source'] ?? ''),
            'code'      => is_array($frame['code'] ?? null) ? $frame['code'] : null,
            'is_vendor' => str_starts_with((string) ($frame['file'] ?? ''), 'vendor/') || str_starts_with((string) ($frame['file'] ?? ''), '['),
        ])->all();
    }

    /** @return array{kind: string, id: int, at: string}|null */
    public function executionOf(object $occurrence): ?array
    {
        if (! $occurrence->execution_id || ! in_array($occurrence->source, ['request', 'command'], true)) {
            return null;
        }

        $createdAt = Carbon::parse($occurrence->created_at);
        $execution = $this->db()->table($occurrence->source === 'request' ? 'nightowl_requests_v2' : 'nightowl_commands_v2')
            ->where('trace_id', $occurrence->execution_id)
            ->whereBetween('created_at', [$createdAt->copy()->subHour()->toDateTimeString(), $createdAt->copy()->addMinutes(5)->toDateTimeString()])
            ->first(['id', 'created_at']);

        return $execution ? ['kind' => $occurrence->source, 'id' => (int) $execution->id, 'at' => $execution->created_at] : null;
    }

    public const array LOG_LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

    /** @return array{range: string, level: string|null, search: string|null, series: list<object>, entries: list<array<string, mixed>>} */
    public function logs(string $range, ?string $level, ?string $search): array
    {
        $minutes = self::RANGES[$range][2];
        $level   = in_array($level, self::LOG_LEVELS, true) ? $level : null;
        $search  = trim((string) $search) ?: null;
        $bucket  = $range === '1h' ? 'minute' : 'hour';

        $logs = fn () => $this->db()->table('nightowl_logs_v2 as log')
            ->leftJoin('nightowl_dict_string as level', 'level.id', '=', 'log.level_id')
            ->where('log.created_at', '>=', now()->subMinutes($minutes)->toDateTimeString())
            ->when($level, fn ($query) => $query->where('level.value', $level))
            ->when($search, fn ($query) => $query->where('log.message', 'ilike', '%'.addcslashes($search, '%_\\').'%'));

        return [
            'range'   => $range,
            'level'   => $level,
            'search'  => $search,
            'series'  => $logs()->groupByRaw("date_trunc('$bucket', log.created_at), level.value")->orderByRaw('1')
                ->selectRaw("date_trunc('$bucket', log.created_at) as bucket_start, coalesce(level.value, 'info') as level, count(*) as entries")->get()->all(),
            'entries' => $logs()
                ->leftJoin('nightowl_dict_string as channel', 'channel.id', '=', 'log.channel_id')
                ->leftJoin('nightowl_dict_string as server', 'server.id', '=', 'log.server_id')
                ->leftJoin('nightowl_dict_string as source', 'source.id', '=', 'log.execution_source_id')
                ->orderByDesc('log.created_at')->orderByDesc('log.id')->limit(100)
                ->get(['log.id', 'log.created_at', 'log.execution_id', 'log.user_id', 'log.execution_preview', 'log.message', 'log.context_z', 'level.value as level', 'channel.value as channel', 'server.value as server', 'source.value as source'])
                ->map(fn (object $entry) => [
                    ...array_diff_key((array) $entry, ['context_z' => true]),
                    'context' => $this->inflateJson($entry->context_z),
                ])->all(),
        ];
    }

    public function inflateJson(mixed $compressed): ?string
    {
        $compressed = is_resource($compressed) ? stream_get_contents($compressed) : $compressed;
        $json       = $compressed ? @gzinflate($compressed) : false;
        if (! $json || in_array($json, ['[]', '{}', 'null'], true)) {
            return null;
        }

        return json_encode(json_decode($json), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: $json;
    }

    /** @return array{summary: array<string, mixed>, stages: list<array{label: string, duration: int}>, spans: list<array<string, mixed>>, truncated: bool}|null */
    public function traceOfExecution(string $kind, string $executionId, string $at): ?array
    {
        if (! in_array($kind, ['request', 'command'], true) || ! preg_match('/^[0-9a-f-]{36}$/', $executionId)) {
            return null;
        }

        $execution = $this->executionOf((object) ['execution_id' => $executionId, 'source' => $kind, 'created_at' => $at]);

        return $execution ? $this->trace($kind, $execution['id'], $execution['at']) : null;
    }
}
