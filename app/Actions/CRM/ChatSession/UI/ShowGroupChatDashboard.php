<?php

/*
 * Author: stewicca
 * Created: Mon, 14 Apr 2025
 * Copyright (c) 2025, Inikoo Ltd
 */

namespace App\Actions\CRM\ChatSession\UI;

use App\Actions\CRM\ChatSession\GetGroupChatDashboardData;
use App\Actions\GrpAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowGroupChatDashboard extends GrpAction
{
    use AsAction;
    use WithInertia;

    public function handle(Group $group): Group
    {
        return $group;
    }

    public function asController(ActionRequest $request): Group
    {
        $this->initialisation(app('group'), $request);

        return $this->handle($this->group);
    }

    public function htmlResponse(Group $group, ActionRequest $request): Response
    {
        $title         = __('Chat Dashboard');
        $dashboardData = GetGroupChatDashboardData::run($group);

        return Inertia::render(
            'Chat/Dashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-comment-alt'],
                        'title' => $title,
                    ],
                ],
                'stats' => $dashboardData['stats'],
                'table' => $dashboardData['table'],
            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-comment-alt',
                        'route' => [
                            'name'       => 'grp.chat.dashboard',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Chat Dashboard'),
                    ],
                ],
            ]
        );
    }
}
