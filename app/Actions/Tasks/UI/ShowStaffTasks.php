<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Tasks\UI;

use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowStaffTasks extends OrgAction
{
    use AsAction;
    use WithInertia;
    use WithStaffTasksScope;

    public function asController(ActionRequest $request): Group
    {
        $this->initialisationFromTasksScope($request);

        return $this->group;
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): Group
    {
        $this->initialisationFromTasksScope($request, $organisation);

        return $this->group;
    }

    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): Group
    {
        $this->initialisationFromTasksScope($request, $organisation, $shop);

        return $this->group;
    }

    public function htmlResponse(Group $group, ActionRequest $request): Response
    {
        $title = __('Tasks');

        return Inertia::render('Tasks/StaffTasks', [
            'breadcrumbs'   => $this->tasksBreadcrumbs(),
            'title'         => $title,
            'pageHead'      => [
                'title' => $title,
                'icon'  => ['icon' => ['fal', 'fa-tasks'], 'title' => $title],
            ],
            'selected_task' => $request->query('task'),
        ]);
    }
}
