<?php

/*
 * Author: stewicca
 * Created: Mon, 14 Apr 2025
 * Copyright (c) 2025, Inikoo Ltd
 */

namespace App\Actions\CRM\Agent\UI;

use App\Actions\GrpAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowGroupAgents extends GrpAction
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
        $indexAgentAction = IndexAgent::make();
        $agents = $indexAgentAction->handle($group, 'agents');

        return Inertia::render(
            'Agent/Agents',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('Agents'),
                'pageHeading' => [
                    'title' => __('Chat Agents'),
                    'icon'  => [
                        'title' => __('Chat Agents'),
                        'icon'  => ['fal', 'fa-headset'],
                    ],
                    'actions' => [],
                ],
                'data'             => $agents,
                'organisationSlug' => $group->organisations->first()->slug,
            ],
        )->table(
            $indexAgentAction->tableStructure(prefix: 'agents')
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
                        'icon'  => 'fal fa-headset',
                        'route' => [
                            'name'       => 'grp.chat.agents.show',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Agents'),
                    ],
                ],
            ]
        );
    }
}
