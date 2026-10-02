<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Tasks\UI;

use App\Actions\Helpers\Ticket\UI\IndexTickets;
use App\Actions\OrgAction;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The numbers behind the eight week keep-or-park decision: tasks raised, done, how long they take, how many rot.
 * Same interval picker, layout and drill-down links as the ticket reports so people already know how to read it.
 */
class ShowStaffTasksReports extends OrgAction
{
    use WithStaffTasksScope;

    private const string METRICS_SQL = "
        count(*) as created,
        count(*) filter (where staff_tasks.status = 'todo') as todo,
        count(*) filter (where staff_tasks.status = 'in_progress') as in_progress,
        count(*) filter (where staff_tasks.status in ('todo', 'in_progress')) as open,
        count(*) filter (where staff_tasks.status = 'done') as done,
        count(*) filter (where staff_tasks.status = 'cancelled') as cancelled,
        count(*) filter (where staff_tasks.status in ('todo', 'in_progress') and staff_tasks.created_at < now() - interval '7 days') as stale,
        percentile_cont(0.5) within group (order by extract(epoch from staff_tasks.closed_at - staff_tasks.created_at) / 3600)
            filter (where staff_tasks.status = 'done') as median_hours,
        max(extract(epoch from now() - staff_tasks.created_at) / 86400) filter (where staff_tasks.status in ('todo', 'in_progress')) as longest_wait_days
    ";

    public function handle(Group|Organisation $parent, User $viewer, string $interval, ?int $personId = null): array
    {
        $base = StaffTask::query()->within($parent)->visibleTo($viewer)
            ->when($personId, fn (Builder $query) => $this->whereInvolved($query, $personId));

        [$from, $to] = $this->range($interval, (clone $base)->min('created_at'));
        $days        = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        $bucket      = $days <= 62 ? 'day' : ($days <= 400 ? 'week' : 'month');

        $inRange    = (clone $base)->whereBetween('staff_tasks.created_at', [$from, $to]);
        $oldestOpen = (clone $base)->open()->orderBy('created_at')->first();

        return [
            'interval'     => $interval,
            'person'       => $personId,
            'bucket'       => $bucket,
            'from'         => $from->toDateString(),
            'stale_before' => now()->subDays(7)->toDateTimeString(),
            'open_now'     => (clone $base)->open()->count(),
            'stale_now'    => (clone $base)->open()->where('created_at', '<', now()->subDays(7))->count(),
            'oldest_open'  => $oldestOpen ? ['reference' => $oldestOpen->reference, 'age_days' => (int) Carbon::parse($oldestOpen->created_at)->diffInDays()] : null,
            'totals'       => $this->metrics((clone $inRange)->selectRaw(self::METRICS_SQL)->first()),
            'series'       => $this->series($base, $from, $to, $bucket),
            'by_status'    => collect(StaffTaskStatusEnum::cases())->map(fn (StaffTaskStatusEnum $status) => [
                'status' => $status->value,
                'label'  => StaffTaskStatusEnum::labels()[$status->value],
                'total'  => (int) (clone $inRange)->where('status', $status)->count(),
            ])->values()->all(),
            'people'       => [
                'assignee'     => $this->people($inRange, 'assignee'),
                'collaborator' => $this->people($inRange, 'collaborator'),
                'involved'     => $this->people($inRange, 'involved'),
            ],
            'people_total' => $this->metrics((clone $inRange)->whereNotNull('staff_tasks.assignee_id')->selectRaw(self::METRICS_SQL)->first()),
            'requesters'   => $this->requesters($inRange),
            'departments'  => $this->departments($inRange),
            'cleared'      => $interval === 'all' ? null : $this->cleared($base, $from, $to),
        ];
    }

    private function whereInvolved(Builder $query, int $userId): Builder
    {
        return $query->where(fn (Builder $involved) => $involved
            ->where('staff_tasks.assignee_id', $userId)
            ->orWhereIn('staff_tasks.id', DB::table('staff_task_collaborators')->where('user_id', $userId)->select('staff_task_id')));
    }

    private function series(Builder $base, Carbon $from, Carbon $to, string $bucket): array
    {
        $createdBy = (clone $base)->whereBetween('created_at', [$from, $to])
            ->selectRaw("to_char(date_trunc('$bucket', created_at), 'YYYY-MM-DD') as day, count(*) as total")->groupBy('day')->pluck('total', 'day');
        $doneBy = (clone $base)->where('status', StaffTaskStatusEnum::DONE)->whereBetween('closed_at', [$from, $to])
            ->selectRaw("to_char(date_trunc('$bucket', closed_at), 'YYYY-MM-DD') as day, count(*) as total")->groupBy('day')->pluck('total', 'day');
        $closedBy = (clone $base)->whereBetween('closed_at', [$from, $to])
            ->selectRaw("to_char(date_trunc('$bucket', closed_at), 'YYYY-MM-DD') as day, count(*) as total")->groupBy('day')->pluck('total', 'day');

        $open   = (clone $base)->where('created_at', '<', $from)->count() - (clone $base)->where('closed_at', '<', $from)->count();
        $series = [];
        $cursor = $from->copy()->startOf($bucket);
        while ($cursor->lte($to)) {
            $day      = $cursor->toDateString();
            $open    += (int) ($createdBy[$day] ?? 0) - (int) ($closedBy[$day] ?? 0);
            $series[] = ['date' => $day, 'created' => (int) ($createdBy[$day] ?? 0), 'done' => (int) ($doneBy[$day] ?? 0), 'open' => $open];
            $cursor->add(1, $bucket);
        }

        return $series;
    }

    /**
     * @param 'assignee'|'collaborator'|'involved' $role
     */
    private function people(Builder $inRange, string $role): array
    {
        $workers = match ($role) {
            'assignee'     => DB::table('staff_tasks')->whereNotNull('assignee_id')->select('id as staff_task_id', 'assignee_id as user_id'),
            'collaborator' => DB::table('staff_task_collaborators')->select('staff_task_id', 'user_id'),
            'involved'     => DB::table('staff_tasks')->whereNotNull('assignee_id')->select('id as staff_task_id', 'assignee_id as user_id')
                ->union(DB::table('staff_task_collaborators')->select('staff_task_id', 'user_id')),
        };

        $rows = (clone $inRange)
            ->joinSub($workers, 'workers', 'workers.staff_task_id', '=', 'staff_tasks.id')
            ->join('users', 'users.id', '=', 'workers.user_id')
            ->selectRaw('users.id as user_id, '.self::METRICS_SQL)
            ->groupBy('users.id')
            ->orderByDesc('created')
            ->get();

        return $this->withPeople($rows, fn ($row) => $this->metrics($row));
    }

    private function requesters(Builder $inRange): array
    {
        $rows = (clone $inRange)
            ->join('users', 'users.id', '=', 'staff_tasks.requester_id')
            ->selectRaw('users.id as user_id, '.self::METRICS_SQL)
            ->groupBy('users.id')
            ->orderByDesc('created')
            ->get();

        return $this->withPeople($rows, fn ($row) => $this->metrics($row));
    }

    private function departments(Builder $inRange): array
    {
        return (clone $inRange)
            ->selectRaw("coalesce(staff_tasks.department, 'none') as department, ".self::METRICS_SQL)
            ->groupBy('staff_tasks.department')
            ->orderByDesc('created')
            ->get()
            ->map(fn ($row) => ['department' => $row->department, 'name' => $row->department === 'none' ? __('To a person') : StaffTask::departmentLabel($row->department), ...$this->metrics($row)])
            ->all();
    }

    /**
     * Older tasks, raised before the period and done in it: the backlog that got cleared.
     *
     * @return array{rows: array, total: array{done: int, median_hours: float|null}}
     */
    private function cleared(Builder $base, Carbon $from, Carbon $to): array
    {
        $cleared = (clone $base)
            ->where('staff_tasks.created_at', '<', $from)
            ->where('staff_tasks.status', StaffTaskStatusEnum::DONE)
            ->whereBetween('staff_tasks.closed_at', [$from, $to]);

        $metricsSql = "count(*) as done, percentile_cont(0.5) within group (order by extract(epoch from staff_tasks.closed_at - staff_tasks.created_at) / 3600) as median_hours";

        $rows = (clone $cleared)->whereNotNull('staff_tasks.assignee_id')
            ->selectRaw("staff_tasks.assignee_id as user_id, $metricsSql")
            ->groupBy('staff_tasks.assignee_id')
            ->orderByDesc('done')
            ->get();

        $total = (clone $cleared)->selectRaw($metricsSql)->first();

        return [
            'rows'  => $this->withPeople($rows, fn ($row) => ['done' => (int) $row->done, 'median_hours' => isset($row->median_hours) ? round((float) $row->median_hours, 1) : null]),
            'total' => ['done' => (int) ($total->done ?? 0), 'median_hours' => isset($total->median_hours) ? round((float) $total->median_hours, 1) : null],
        ];
    }

    private function withPeople(Collection $rows, callable $values): array
    {
        $users = User::with('image')->whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');

        return $rows->map(function ($row) use ($users, $values) {
            $user = $users->get($row->user_id);

            return [
                'id'         => (int) $row->user_id,
                'name'       => $user?->chatName() ?? '?',
                'short_name' => $user ? strtok($user->chatName(), ' ') : '?',
                'avatar'     => $user?->image_id ? $user->imageSources(48, 48) : null,
                ...$values($row),
            ];
        })->values()->all();
    }

    private function metrics(?object $row): array
    {
        $count = fn (string $key) => (int) ($row->$key ?? 0);

        return [
            'created'           => $count('created'),
            'todo'              => $count('todo'),
            'in_progress'       => $count('in_progress'),
            'open'              => $count('open'),
            'done'              => $count('done'),
            'cancelled'         => $count('cancelled'),
            'stale'             => $count('stale'),
            'median_hours'      => isset($row->median_hours) ? round((float) $row->median_hours, 1) : null,
            'longest_wait_days' => isset($row->longest_wait_days) ? (int) $row->longest_wait_days : null,
        ];
    }

    private function range(string $interval, ?string $oldestCreatedAt): array
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
     * @return array<int, array{label: string, value: int}>
     */
    private function personOptions(Group|Organisation $parent, User $viewer): array
    {
        $taskIds = StaffTask::query()->within($parent)->visibleTo($viewer)->select('staff_tasks.id');

        $userIds = DB::table('staff_tasks')->whereIn('id', $taskIds)->whereNotNull('assignee_id')->pluck('assignee_id')
            ->merge(DB::table('staff_task_collaborators')->whereIn('staff_task_id', $taskIds)->pluck('user_id'))
            ->unique();

        return User::whereIn('id', $userIds)->get()
            ->map(fn (User $user) => ['label' => $user->chatName(), 'value' => $user->id])
            ->sortBy('label')
            ->values()
            ->all();
    }

    private function person(ActionRequest $request): ?int
    {
        $person = $request->query('person');

        return ctype_digit((string) $person) ? (int) $person : null;
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromTasksScope($request);

        return $this->handle($this->tasksParent(), $request->user(), IndexTickets::make()->createdInterval(), $this->person($request));
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisationFromTasksScope($request, $organisation);

        return $this->handle($this->tasksParent(), $request->user(), IndexTickets::make()->createdInterval(), $this->person($request));
    }

    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromTasksScope($request, $organisation, $shop);

        return $this->handle($this->tasksParent(), $request->user(), IndexTickets::make()->createdInterval(), $this->person($request));
    }

    public function htmlResponse(array $stats, ActionRequest $request): Response
    {
        $title = __('Tasks reports');

        return Inertia::render('Tasks/StaffTasksReports', [
            'breadcrumbs'      => array_merge(
                $this->tasksBreadcrumbs(),
                [['type' => 'simple', 'simple' => ['route' => $this->tasksRoute('reports'), 'label' => __('Reports')]]]
            ),
            'title'            => $title,
            'pageHead'         => ['title' => $title, 'icon' => ['icon' => ['fal', 'fa-chart-line'], 'title' => $title]],
            'stats'            => $stats,
            'createdIntervals' => IndexTickets::make()->createdIntervalOptions(),
            'personOptions'    => $this->personOptions($this->tasksParent(), $request->user()),
        ]);
    }
}
