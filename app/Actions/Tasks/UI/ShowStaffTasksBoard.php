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
use App\Http\Resources\Tasks\StaffTaskResource;
use App\Models\SysAdmin\Group;
use App\Models\Tasks\StaffTask;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowStaffTasksBoard extends OrgAction
{
    public function handle(Group $group, string $interval): array
    {
        $tasks = IndexTickets::make()->whereCreatedIn(StaffTask::query()->where('group_id', $group->id), $interval, 'staff_tasks.created_at')
            ->with(['requester.image', 'assignee.image', 'conversation', 'model'])
            ->orderByRaw('due_at asc nulls last, id desc')
            ->get()
            ->groupBy(fn (StaffTask $task) => $task->status->value);

        return collect(StaffTaskStatusEnum::cases())->map(fn (StaffTaskStatusEnum $status) => [
            'status' => $status->value,
            'label'  => StaffTaskStatusEnum::labels()[$status->value],
            'color'  => StaffTaskStatusEnum::stateIcon()[$status->value]['color'],
            'tasks'  => StaffTaskResource::collection($tasks->get($status->value, collect()))->resolve(),
        ])->values()->all();
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group, $this->createdInterval());
    }

    private function createdInterval(): string
    {
        return request()->has('created') ? IndexTickets::make()->createdInterval() : '1w';
    }

    public function htmlResponse(array $columns, ActionRequest $request): Response
    {
        $title = __('Tasks board');

        return Inertia::render('Tasks/StaffTasksBoard', [
            'breadcrumbs' => array_merge(
                ShowStaffTasks::make()->getBreadcrumbs(),
                [['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.tasks.board'], 'label' => __('Board')]]]
            ),
            'title'       => $title,
            'pageHead'    => ['title' => $title, 'icon' => ['icon' => ['fal', 'fa-columns'], 'title' => $title]],
            'columns'     => $columns,
            'createdIntervals' => IndexTickets::make()->createdIntervalOptions(),
            'createdInterval'  => $this->createdInterval(),
            'can_manage'  => StaffTask::isSupervisor($request->user()),
            'me'          => $request->user()->id,
        ]);
    }
}
