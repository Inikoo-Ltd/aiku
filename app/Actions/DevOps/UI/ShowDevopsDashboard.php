<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Jun 2026 09:22:41 Indochina Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\UI;

use App\Actions\CRM\ChatSession\GetChatDashboardData;
use App\Actions\CRM\ChatSession\GetGroupChatDashboardData;
use App\Actions\GrpAction;
use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowDevopsDashboard extends OrgAction
{
    use AsAction;
    use WithInertia;

    public function handle(Group $group): Group
    {
        return $group;
    }

    public function asController(ActionRequest $request): Group
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    public function htmlResponse(Group $group, ActionRequest $request): Response
    {
        $title         = __('Devops Dashboard');


        return Inertia::render(
            'Devops/Dashboard',
            [
                'breadcrumbs'     => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'           => $title,
                'pageHead'        => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-server'],
                        'title' => $title,
                    ],
                ],

            ]
        );
    }


    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-comment-alt',
                        'route' => [
                            'name'       => 'grp.devops.dashboard',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Devops Dashboard'),
                    ],
                ],
            ]
        );
    }
}
