<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 06 Jun 2026 09:22:41 Indochina Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowDevopsDashboard extends OrgAction
{
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
                'publicSiteVisits' => $this->getPublicSiteVisits(),

            ]
        );
    }

    /** @return array{daily: array<int, object>, visitors: int, views: int, top_referrer: string|null} */
    public function getPublicSiteVisits(): array
    {
        $visits = fn (int $days) => DB::table('aiku_public_visits')
            ->where('created_at', '>', now()->subDays($days))
            ->where('path', 'not like', '/~search/%');

        $lastWeek = $visits(7)->selectRaw('count(*) as views, count(distinct visitor_hash) as visitors')->first();

        return [
            'daily' => $visits(14)
                ->selectRaw('created_at::date as day, count(*) as views, count(distinct visitor_hash) as visitors')
                ->groupBy('day')->orderBy('day')->get()->all(),
            'visitors'     => (int) $lastWeek->visitors,
            'views'        => (int) $lastWeek->views,
            'top_referrer' => $visits(7)->whereNotNull('referrer')
                ->selectRaw('referrer, count(distinct visitor_hash) as visitors')
                ->groupBy('referrer')->orderByDesc(DB::raw('count(distinct visitor_hash)'))->value('referrer'),
        ];
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
