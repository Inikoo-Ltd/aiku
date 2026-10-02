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
        $tasks = fn () => StaffTask::query()->where('group_id', $user->group_id);
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
            $rows['department'] = $this->row(__('My department, nobody on it'), $tasks()->open()->whereNull('assignee_id')->whereIn('department', $departments), ['department' => implode(',', $departments), 'unassigned' => 1], 'todo,in_progress');
        }

        return [
            'mine'   => $rows,
            'today'  => [
                'done' => $mine()->where('status', StaffTaskStatusEnum::DONE)->where('closed_at', '>=', now()->startOfDay())->count(),
                'open' => $mine()->open()->count(),
            ],
            'recent' => $this->recentUpdates($user),
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
