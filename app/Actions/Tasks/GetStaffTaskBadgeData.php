<?php

/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Tasks;

use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetStaffTaskBadgeData
{
    use AsObject;

    /**
     * @return array{mine: array<string, array{label: string, count: int, filter: array<string, mixed>, status: string}>, today: array{done: int, open: int}, recent: array<int, array<string, mixed>>}
     */
    public function handle(User $user): array
    {
        $tasks = fn () => StaffTask::query()->where('group_id', $user->group_id)->inSection(null);
        $mine  = fn () => $tasks()->where(fn (Builder $query) => $query
            ->where('assignee_id', $user->id)
            ->orWhereIn('id', DB::table('staff_task_collaborators')->where('user_id', $user->id)->select('staff_task_id')));

        $involved    = ['involved' => $user->id];
        $departments = StaffTask::departmentsOf($user);

        $rows = [
            'todo'        => $this->row(__('To do'), $mine()->where('status', StaffTaskStatusEnum::TODO), $involved, 'todo'),
            'in_progress' => $this->row(__('Working on it'), $mine()->where('status', StaffTaskStatusEnum::IN_PROGRESS), $involved, 'in_progress'),
            'overdue'     => $this->row(__('Overdue'), $mine()->open()->where('due_at', '<', today()), [...$involved, 'overdue' => 1], 'todo,in_progress'),
            'requested'   => $this->row(__('I asked for, still open'), $tasks()->open()->where('requester_id', $user->id), ['requester' => $user->id], 'todo,in_progress'),
        ];

        if ($departments) {
            $rows['department'] = $this->row(__('My department, nobody on it'), $tasks()->open()->whereNull('assignee_id')->sentToDepartmentsOf($user), ['department' => implode(',', $departments), 'unassigned' => 1], 'todo,in_progress');
        }

        return [
            'mine'    => $rows,
            'today'   => [
                'done' => $mine()->where('status', StaffTaskStatusEnum::DONE)->where('closed_at', '>=', now()->startOfDay())->count(),
                'open' => $mine()->open()->count(),
            ],
            'created' => $this->createdTasks($user),
            'review'  => $this->reviewTasks($user),
            'recent'  => $this->recentUpdates($user),
        ];
    }

    /**
     * The open tasks a person raised, in three groups: a new ETA waiting for their answer, a call for help, and the rest, newest first.
     *
     * @return array{open: int, needs_answer: int, tasks: array<int, array<string, mixed>>, sections: array{eta_change: array<int, array<string, mixed>>, help_request: array<int, array<string, mixed>>, recent: array<int, array<string, mixed>>}}
     */
    private function createdTasks(User $user): array
    {
        $created     = fn () => StaffTask::query()->where('group_id', $user->group_id)->open()->where('requester_id', $user->id);
        $needsAnswer = fn (Builder $query) => $query->where(fn (Builder $waiting) => $waiting->whereNotNull('data->eta_proposal')->orWhereNotNull('data->help_requested'));

        $rows = fn (Builder $query, int $limit) => $query
            ->with('assignee')
            ->limit($limit)
            ->get()
            ->map(fn (StaffTask $task) => $this->createdTaskRow($task))
            ->values()
            ->all();

        return [
            'open'         => $created()->count(),
            'needs_answer' => $needsAnswer($created())->count(),
            'tasks'        => $rows($created()->orderByRaw("(data->'eta_proposal') is null, (data->'help_requested') is null, due_at asc nulls last, id desc"), 6),
            'sections'     => [
                'eta_change'   => $rows($created()->whereNotNull('data->eta_proposal')->orderByDesc('id'), 5),
                'help_request' => $rows($created()->whereNotNull('data->help_requested')->orderByDesc('id'), 5),
                'recent'       => $rows($created()->whereNull('data->eta_proposal')->whereNull('data->help_requested')->orderByDesc('id'), 5),
            ],
        ];
    }

    /**
     * The open "To review & publish" tasks the user works on, or that wait for their department,
     * with how many of their lines are still to do.
     *
     * @return array{open: int, tasks: array<int, array{id: int, reference: string, subject: string, pending: int, total: int, route: string}>}
     */
    private function reviewTasks(User $user): array
    {
        $tasks = StaffTask::query()
            ->where('group_id', $user->group_id)
            ->inSection(StaffTask::SECTION_REVIEW)
            ->open()
            ->where(fn (Builder $query) => $query
                ->where('assignee_id', $user->id)
                ->orWhereIn('id', DB::table('staff_task_collaborators')->where('user_id', $user->id)->select('staff_task_id'))
                ->orWhere(fn (Builder $department) => $department->whereNull('assignee_id')->sentToDepartmentsOf($user)))
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'reference', 'subject', 'data'])
            ->map(function (StaffTask $task) {
                $subtasks = collect($task->data['subtasks'] ?? []);

                return [
                    'id'        => $task->id,
                    'reference' => $task->reference,
                    'subject'   => $task->subject,
                    'pending'   => $subtasks->where('status', '!=', 'done')->count(),
                    'total'     => $subtasks->count(),
                    'route'     => route('grp.tasks.show', $task->reference),
                ];
            });

        return [
            'open'  => $tasks->sum('pending'),
            'tasks' => $tasks->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createdTaskRow(StaffTask $task): array
    {
        return [
            'id'               => $task->id,
            'reference'        => $task->reference,
            'subject'          => $task->subject,
            'status'           => $task->status->value,
            'status_icon'      => StaffTaskStatusEnum::stateIcon()[$task->status->value],
            'due_at'           => $task->due_at?->toDateString(),
            'assignee'         => $task->assignee?->chatName() ?? ($task->department ? StaffTask::departmentLabel($task->department) : null),
            'has_eta_proposal' => isset($task->data['eta_proposal']),
            'asked_for_help'   => isset($task->data['help_requested']),
            'route'            => route('grp.tasks.show', $task->reference),
        ];
    }

    /**
     * @param array<string, mixed> $filter
     *
     * @return array{label: string, count: int, filter: array<string, mixed>, status: string}
     */
    private function row(string $label, Builder $query, array $filter, string $status): array
    {
        return ['label' => $label, 'count' => $query->count(), 'filter' => $filter, 'status' => $status];
    }

    /**
     * @return array<int, array{id: string, title: string, body: string, route: string, read: bool, created_at: mixed}>
     */
    private function recentUpdates(User $user): array
    {
        return $user->notifications()
            ->whereRaw("(data::jsonb)->>'type' = 'staff_task'")
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn ($notification) => [
                'id'         => (string) $notification->id,
                'title'      => (string) data_get($notification->data, 'title', ''),
                'body'       => (string) data_get($notification->data, 'body', ''),
                'route'      => (string) data_get($notification->data, 'route', ''),
                'read'       => $notification->read_at !== null,
                'created_at' => $notification->created_at,
            ])
            ->values()
            ->all();
    }
}
