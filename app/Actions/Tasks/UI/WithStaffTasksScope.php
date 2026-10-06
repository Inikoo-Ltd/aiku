<?php

/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Tasks\UI;

use App\Actions\Traits\WithGroupModuleScope;
use App\Models\Tasks\StaffTask;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

trait WithStaffTasksScope
{
    use WithGroupModuleScope;

    protected function initialisationFromTasksScope(ActionRequest $request, ?Organisation $organisation = null, ?Shop $shop = null): static
    {
        return $this->initialisationFromModuleScope($request, $organisation, $shop);
    }

    protected function tasksParent(): Group|Organisation
    {
        return $this->organisation ?? $this->group;
    }

    /**
     * The task lists show the whole group; filter[organisation]=<slug> narrows them to the tasks
     * involving the staff of one organisation the viewer has access to.
     */
    protected function tasksListParent(): Group|Organisation
    {
        $organisationSlug = Arr::get(request()->input('filter', []), 'organisation');
        $group            = $this->group ?? $this->organisation?->group;

        if (!$organisationSlug || !request()->user()) {
            return $group;
        }

        return request()->user()->authorisedOrganisations()
            ->where('organisations.group_id', $group->id)
            ->where('organisations.slug', $organisationSlug)
            ->first() ?? $group;
    }

    /**
     * @return array<string, string>
     */
    protected function organisationFilterOptions(User $viewer): array
    {
        return $viewer->authorisedOrganisations()
            ->orderBy('organisations.name')
            ->get(['organisations.slug', 'organisations.name'])
            ->mapWithKeys(fn (Organisation $organisation) => [$organisation->slug => $organisation->name])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function assigneeFilterOptions(Group|Organisation $parent, User $viewer): array
    {
        $assigneeIds = StaffTask::query()->within($parent)->visibleTo($viewer)
            ->whereNotNull('staff_tasks.assignee_id')
            ->where('staff_tasks.assignee_id', '!=', $viewer->id)
            ->distinct()
            ->pluck('staff_tasks.assignee_id');

        $assignees = User::whereIn('id', $assigneeIds)
            ->orderBy('contact_name')
            ->get(['id', 'contact_name', 'username'])
            ->mapWithKeys(fn (User $user) => [(string) $user->id => $user->contact_name ?: $user->username])
            ->all();

        return ['me' => __('Me'), 'unassigned' => __('Unassigned')] + $assignees;
    }

    public const array DRILL_DOWN_FILTERS = ['assignee', 'involved', 'collaborator', 'requester', 'unassigned', 'has_assignee'];

    /**
     * Lists open on the viewer's own tasks unless a filter already says whose tasks to show.
     */
    protected function appliedAssigneeFilter(): string
    {
        $filters = (array) request()->input('filter', []);

        if (array_key_exists('assignee', $filters)) {
            return (string) $filters['assignee'] === '' ? 'all' : (string) $filters['assignee'];
        }

        return Arr::hasAny($filters, self::DRILL_DOWN_FILTERS) ? 'all' : 'me';
    }

    /**
     * @return array{organisation: array<int, array{value: string, label: string}>, assignee: array<int, array{value: string, label: string}>, department: array<int, array{value: string, label: string}>}
     */
    protected function taskFilterOptions(Group|Organisation $parent, User $viewer): array
    {
        $asList = fn (array $options) => collect($options)->map(fn (string $label, string|int $value) => ['value' => (string) $value, 'label' => $label])->values()->all();

        return [
            'organisation' => $asList($this->organisationFilterOptions($viewer)),
            'assignee'     => $asList(['all' => __('All')] + $this->assigneeFilterOptions($parent, $viewer)),
            'department'   => $asList($this->departmentFilterOptions($parent)),
        ];
    }

    /**
     * @return array{organisation: string|null, assignee: string, department: string|null}
     */
    protected function appliedTaskFilters(): array
    {
        $filters = (array) request()->input('filter', []);

        return [
            'organisation' => Arr::get($filters, 'organisation'),
            'assignee'     => $this->appliedAssigneeFilter(),
            'department'   => Arr::get($filters, 'department'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function departmentFilterOptions(Group|Organisation $parent): array
    {
        $groupId = $parent instanceof Group ? $parent->id : $parent->group_id;

        return ['none' => __('No department')]
            + collect(StaffTask::departments($groupId))->mapWithKeys(fn (array $department) => [$department['value'] => $department['label']])->all();
    }

    protected function applyAssigneeFilter(Builder $query, User $viewer, mixed $value): Builder
    {
        return match ((string) $value) {
            '', 'all'    => $query,
            'me'         => $query->where('staff_tasks.assignee_id', $viewer->id),
            'unassigned' => $query->whereNull('staff_tasks.assignee_id'),
            default      => $query->where('staff_tasks.assignee_id', (int) $value),
        };
    }

    protected function applyDepartmentFilter(Builder $query, mixed $value): Builder
    {
        if ($value === null || $value === '') {
            return $query;
        }

        return $value === 'none' ? $query->whereNull('staff_tasks.department') : $query->whereIn('staff_tasks.department', (array) $value);
    }

    /**
     * @param  array<int, string>  $extraParameters
     *
     * @return array{name: string, parameters: array<int, string>}
     */
    protected function tasksRoute(string $suffix, array $extraParameters = []): array
    {
        return $this->moduleScopeRoute('tasks', $suffix, $extraParameters);
    }

    /**
     * @return array{statuses: \Illuminate\Support\Collection, priorities: \Illuminate\Support\Collection}
     */
    protected function staffTaskEditOptions(): array
    {
        return StaffTask::editOptions();
    }

    protected function tasksBreadcrumbs(): array
    {
        return array_merge(
            $this->moduleScopeParentBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-tasks',
                        'route' => $this->tasksRoute('index'),
                        'label' => __('Tasks'),
                    ],
                ],
            ]
        );
    }
}
