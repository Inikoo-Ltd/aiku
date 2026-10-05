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
