<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Tasks\UI;

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
    public function handle(Group $group): array
    {
        $tasks = StaffTask::query()
            ->where('group_id', $group->id)
            ->where(fn ($query) => $query->open()->orWhere('closed_at', '>=', now()->subDays(7)))
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

        return $this->handle($this->group);
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
            'can_manage'  => StaffTask::isSupervisor($request->user()),
            'me'          => $request->user()->id,
        ]);
    }
}
