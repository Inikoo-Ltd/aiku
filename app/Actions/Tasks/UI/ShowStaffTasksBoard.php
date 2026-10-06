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
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowStaffTasksBoard extends OrgAction
{
    use WithStaffTasksScope;

    public function handle(Group|Organisation $parent, User $viewer, string $interval, array $filters = []): array
    {
        $query = StaffTask::query()->within($parent)->visibleTo($viewer);
        $this->applyAssigneeFilter($query, $viewer, Arr::get($filters, 'assignee'));
        $this->applyDepartmentFilter($query, Arr::get($filters, 'department'));

        $tasks = IndexTickets::make()->whereCreatedIn($query, $interval, 'staff_tasks.created_at')
            ->with(['requester.image', 'assignee.image', 'collaborators.image', 'conversation.participants', 'model', 'media'])
            ->orderByRaw('due_at asc nulls last, id desc')
            ->get()
            ->groupBy(fn (StaffTask $task) => $task->status->value);

        return collect(StaffTaskStatusEnum::cases())->map(fn (StaffTaskStatusEnum $status) => [
            'status' => $status->value,
            'label'  => StaffTaskStatusEnum::labels()[$status->value],
            'color'  => StaffTaskStatusEnum::stateIcon()[$status->value]['color'],
            'icon'   => StaffTaskStatusEnum::stateIcon()[$status->value],
            'tasks'  => StaffTaskResource::collection($tasks->get($status->value, collect()))->resolve(),
        ])->values()->all();
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromTasksScope($request);

        return $this->handle($this->tasksListParent(), $request->user(), $this->createdInterval(), $this->boardFilters());
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisationFromTasksScope($request, $organisation);

        return $this->handle($this->tasksListParent(), $request->user(), $this->createdInterval(), $this->boardFilters());
    }

    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromTasksScope($request, $organisation, $shop);

        return $this->handle($this->tasksListParent(), $request->user(), $this->createdInterval(), $this->boardFilters());
    }

    /**
     * @return array{organisation: string|null, assignee: string|null, department: string|null}
     */
    private function boardFilters(): array
    {
        $filters = (array) request()->input('filter', []);

        return [
            'organisation' => Arr::get($filters, 'organisation'),
            'assignee'     => Arr::get($filters, 'assignee'),
            'department'   => Arr::get($filters, 'department'),
        ];
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
                $this->tasksBreadcrumbs(),
                [['type' => 'simple', 'simple' => ['route' => $this->tasksRoute('board'), 'label' => __('Board')]]]
            ),
            'title'       => $title,
            'pageHead'    => ['title' => $title, 'icon' => ['icon' => ['fal', 'fa-columns'], 'title' => $title]],
            'columns'     => $columns,
            'showRoute'   => $this->tasksRoute('show'),
            'createdIntervals' => IndexTickets::make()->createdIntervalOptions(),
            'createdInterval'  => $this->createdInterval(),
            'taskFilterOptions'  => [
                'organisation' => $this->organisationFilterOptions($request->user()),
                'assignee'     => $this->assigneeFilterOptions($this->tasksListParent(), $request->user()),
                'department'   => $this->departmentFilterOptions($this->tasksListParent()),
            ],
            'appliedTaskFilters' => $this->boardFilters(),
            'can_manage'  => StaffTask::isSupervisor($request->user()),
            'me'          => $request->user()->id,
        ]);
    }
}
