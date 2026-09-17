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
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * The numbers behind the eight week keep-or-park decision: tasks raised, done, how long they take, how many rot.
 * Same interval picker as the ticket reports so people already know how to read it.
 */
class ShowStaffTasksReports extends OrgAction
{
    private const string METRICS_SQL = "
        count(*) as created,
        count(*) filter (where staff_tasks.status in ('todo', 'in_progress')) as open,
        count(*) filter (where staff_tasks.status = 'done') as done,
        count(*) filter (where staff_tasks.status = 'cancelled') as cancelled,
        count(*) filter (where staff_tasks.status in ('todo', 'in_progress') and staff_tasks.created_at < now() - interval '7 days') as stale,
        percentile_cont(0.5) within group (order by extract(epoch from staff_tasks.closed_at - staff_tasks.created_at) / 3600)
            filter (where staff_tasks.status = 'done') as median_hours,
        max(extract(epoch from now() - staff_tasks.created_at) / 86400) filter (where staff_tasks.status in ('todo', 'in_progress')) as longest_wait_days
    ";

    public function handle(Group $group, string $interval): array
    {
        $base = StaffTask::where('staff_tasks.group_id', $group->id);

        [$from, $to] = $this->range($interval, (clone $base)->min('created_at'));
        $days        = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        $bucket      = $days <= 62 ? 'day' : ($days <= 400 ? 'week' : 'month');

        $createdBy = (clone $base)->whereBetween('created_at', [$from, $to])
            ->selectRaw("to_char(date_trunc('$bucket', created_at), 'YYYY-MM-DD') as day, count(*) as total")->groupBy('day')->pluck('total', 'day');
        $doneBy = (clone $base)->where('status', StaffTaskStatusEnum::DONE)->whereBetween('closed_at', [$from, $to])
            ->selectRaw("to_char(date_trunc('$bucket', closed_at), 'YYYY-MM-DD') as day, count(*) as total")->groupBy('day')->pluck('total', 'day');
        $closedBy = (clone $base)->whereBetween('closed_at', [$from, $to])
            ->selectRaw("to_char(date_trunc('$bucket', closed_at), 'YYYY-MM-DD') as day, count(*) as total")->groupBy('day')->pluck('total', 'day');

        $open   = (clone $base)->where('created_at', '<', $from)->count() - (clone $base)->where('closed_at', '<', $from)->count();
        $series = collect();
        $cursor = $from->copy()->startOf($bucket);
        while ($cursor->lte($to)) {
            $day  = $cursor->toDateString();
            $open += (int) ($createdBy[$day] ?? 0) - (int) ($closedBy[$day] ?? 0);
            $series->push(['date' => $day, 'created' => (int) ($createdBy[$day] ?? 0), 'done' => (int) ($doneBy[$day] ?? 0), 'open' => $open]);
            $cursor->add(1, $bucket);
        }

        $inRange = (clone $base)->whereBetween('staff_tasks.created_at', [$from, $to]);

        $byDepartment = (clone $inRange)
            ->selectRaw("coalesce(staff_tasks.department, '') as department, ".self::METRICS_SQL)
            ->groupBy('staff_tasks.department')
            ->orderByDesc('created')
            ->get()
            ->map(fn ($row) => ['department' => $row->department, 'label' => $row->department ? StaffTask::departmentLabel($row->department) : __('To a person'), ...$this->metrics($row)])
            ->all();

        $workers = DB::query()->fromSub(
            DB::table('staff_tasks')->whereNotNull('assignee_id')->select('id as staff_task_id', 'assignee_id as user_id')
                ->union(DB::table('staff_task_collaborators')->select('staff_task_id', 'user_id')),
            'task_workers'
        )->selectRaw('staff_task_id, user_id, 1.0 / count(*) over (partition by staff_task_id) as share');

        $byAssignee = (clone $inRange)
            ->joinSub($workers, 'workers', 'workers.staff_task_id', '=', 'staff_tasks.id')
            ->join('users', 'users.id', '=', 'workers.user_id')
            ->selectRaw('users.id as id, coalesce(users.contact_name, users.username) as name, '.str_replace('count(*)', 'sum(workers.share)', self::METRICS_SQL))
            ->groupBy('users.id', 'users.contact_name', 'users.username')
            ->orderByDesc('created')
            ->get();
        $assigneeUsers = User::whereIn('id', $byAssignee->pluck('id'))->get()->keyBy('id');
        $byAssignee    = $byAssignee->map(fn ($row) => ['name' => $row->name, 'avatar' => $assigneeUsers->get($row->id)?->imageSources(48, 48), ...$this->metrics($row, 2)])->all();

        $byRequester = (clone $inRange)
            ->join('users', 'users.id', '=', 'staff_tasks.requester_id')
            ->selectRaw('users.id as id, coalesce(users.contact_name, users.username) as name, '.self::METRICS_SQL)
            ->groupBy('users.id', 'users.contact_name', 'users.username')
            ->orderByDesc('created')
            ->get()
            ->map(fn ($row) => ['name' => $row->name, ...$this->metrics($row)])
            ->all();

        $totals     = $this->metrics((clone $inRange)->selectRaw(self::METRICS_SQL)->first());
        $oldestOpen = (clone $base)->open()->orderBy('created_at')->first();

        return [
            'interval'      => $interval,
            'bucket'        => $bucket,
            'from'          => $from->toDateString(),
            'open_now'      => (clone $base)->open()->count(),
            'stale_now'     => (clone $base)->open()->where('created_at', '<', now()->subDays(7))->count(),
            'oldest_open'   => $oldestOpen ? ['reference' => $oldestOpen->reference, 'age_days' => (int) Carbon::parse($oldestOpen->created_at)->diffInDays()] : null,
            'totals'        => $totals,
            'series'        => $series->values()->all(),
            'by_status'     => collect(StaffTaskStatusEnum::cases())->map(fn (StaffTaskStatusEnum $status) => [
                'status' => $status->value,
                'label'  => StaffTaskStatusEnum::labels()[$status->value],
                'color'  => StaffTaskStatusEnum::stateIcon()[$status->value]['color'],
                'total'  => (int) (clone $inRange)->where('status', $status)->count(),
            ])->values()->all(),
            'by_department' => $byDepartment,
            'by_assignee'   => $byAssignee,
            'by_requester'  => $byRequester,
        ];
    }

    /**
     * Per person counts are shared between the assignee and collaborators, so they carry a decimal.
     */
    private function metrics(?object $row, int $precision = 0): array
    {
        $count = fn (string $key) => $precision ? round((float) ($row->$key ?? 0), $precision) : (int) ($row->$key ?? 0);

        return [
            'created'           => $count('created'),
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

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group, IndexTickets::make()->createdInterval());
    }

    public function htmlResponse(array $stats): Response
    {
        $title = __('Tasks reports');

        return Inertia::render('Tasks/StaffTasksReports', [
            'breadcrumbs'      => array_merge(
                ShowStaffTasks::make()->getBreadcrumbs(),
                [['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.tasks.reports'], 'label' => __('Reports')]]]
            ),
            'title'            => $title,
            'pageHead'         => ['title' => $title, 'icon' => ['icon' => ['fal', 'fa-chart-line'], 'title' => $title]],
            'stats'            => $stats,
            'createdIntervals' => IndexTickets::make()->createdIntervalOptions(),
        ]);
    }
}
