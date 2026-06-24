<?php

/*
 * Author: stewicca
 * Created: Mon, 14 Apr 2025
 * Copyright (c) 2025, Inikoo Ltd
 */

namespace App\Actions\CRM\ChatSession\UI;

use App\Actions\CRM\ChatSession\GetChatDashboardData;
use App\Actions\CRM\ChatSession\GetGroupChatDashboardData;
use App\Actions\GrpAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\Auth;
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

        $agentDashboards = $this->getAgentDashboards();

        return Inertia::render(
            'Chat/Dashboard',
            [
                'breadcrumbs'     => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'           => $title,
                'pageHead'        => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-comment-alt'],
                        'title' => $title,
                    ],
                ],
                'stats'           => $dashboardData['stats'],
                'table'           => $dashboardData['table'],
                'agentDashboards' => $agentDashboards,
            ]
        );
    }

    private function getAgentDashboards(): array
    {
        $user = Auth::user();

        if (!$user) {
            return [];
        }

        $chatAgent = $user->chatAgent()->with('organisations')->first();

        if (!$chatAgent) {
            return [];
        }

        return $chatAgent->organisations->unique('id')->map(function (Organisation $organisation) use ($chatAgent): array {
            /** @var \App\Models\CRM\Livechat\ChatAgent $chatAgent */
            $data = GetChatDashboardData::run($organisation);

            $agentShopIds = $chatAgent->shops()
                ->wherePivot('organisation_id', $organisation->id)
                ->pluck('shops.id')
                ->toArray();

            $shopQuery = \count($agentShopIds)
                ? '?' . implode('&', array_map(fn ($id) => "shop_ids[]={$id}", $agentShopIds))
                : '';

            return [
                'organisation'           => ['slug' => $organisation->slug, 'name' => $organisation->name],
                'stats'                  => $data['stats'],
                'chatEnabledShops'       => $data['chatEnabledShops'],
                'table'                  => $data['table'],
                'dashboardVisitorsRoute' => route('grp.org.chat.dashboard-visitors', $organisation->slug) . $shopQuery,
                'activeSessionsRoute'    => route('grp.org.chat.active-sessions', $organisation->slug),
                'visitorsByCountryRoute' => route('grp.org.chat.visitors-by-country', $organisation->slug),
            ];
        })->values()->all();
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
