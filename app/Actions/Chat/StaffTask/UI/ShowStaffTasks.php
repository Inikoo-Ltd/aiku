<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\StaffTask\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowStaffTasks extends OrgAction
{
    use AsAction;
    use WithInertia;

    public function asController(ActionRequest $request): Group
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->group;
    }

    public function htmlResponse(Group $group, ActionRequest $request): Response
    {
        $title = __('Tasks');

        return Inertia::render('Chat/StaffTasks', [
            'breadcrumbs'   => $this->getBreadcrumbs(),
            'title'         => $title,
            'pageHead'      => [
                'title' => $title,
                'icon'  => ['icon' => ['fal', 'fa-tasks'], 'title' => $title],
            ],
            'selected_task' => $request->query('task'),
        ]);
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [[
                'type'   => 'simple',
                'simple' => [
                    'icon'  => 'fal fa-tasks',
                    'route' => ['name' => 'grp.chat.staff.tasks.index'],
                    'label' => __('Tasks'),
                ],
            ]]
        );
    }
}
