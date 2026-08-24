<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\UI;

use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowAikuPublicAnalytics extends OrgAction
{
    use WithInertia;

    public function asController(ActionRequest $request): Group
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->group;
    }

    public function htmlResponse(Group $group, ActionRequest $request): Response
    {
        $title = __('aiku.io analytics');

        return Inertia::render(
            'Devops/AikuPublicAnalytics',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-chart-line'],
                        'title' => $title,
                    ],
                ],
                'stats'       => $this->handle(),
            ]
        );
    }

    /** @return array{daily: array<int, object>, pages: array<int, object>, searches: array<int, object>, referrers: array<int, object>, page_referrers: array<int, object>, countries: array<int, object>} */
    public function handle(int $days = 30): array
    {
        $visits = fn () => DB::table('aiku_public_visits')->where('created_at', '>', now()->subDays($days));

        return [
            'daily' => $visits()
                ->selectRaw('created_at::date as day, count(*) as views, count(distinct visitor_hash) as visitors')
                ->groupBy('day')->orderBy('day')->get()->all(),
            'pages' => $visits()->where('path', 'not like', '/~search/%')
                ->selectRaw('path, count(*) as views, count(distinct visitor_hash) as visitors, max(created_at) as last_visited_at')
                ->groupBy('path')->orderByDesc(DB::raw('count(*)'))->limit(25)->get()->all(),
            'searches' => $visits()->where('path', 'like', '/~search/%')
                ->selectRaw("replace(substr(path, 10), '%20', ' ') as query, count(*) as views, count(distinct visitor_hash) as visitors, max(created_at) as last_visited_at")
                ->groupBy('path')->orderByDesc(DB::raw('count(*)'))->limit(25)->get()->all(),
            'referrers' => $visits()->whereNotNull('referrer')
                ->selectRaw('referrer, count(*) as views, count(distinct visitor_hash) as visitors, max(created_at) as last_visited_at')
                ->groupBy('referrer')->orderByDesc(DB::raw('count(distinct visitor_hash)'))->limit(25)->get()->all(),
            'page_referrers' => $visits()->whereNotNull('referrer')
                ->selectRaw('path, referrer, count(*) as views, count(distinct visitor_hash) as visitors, max(created_at) as last_visited_at')
                ->groupBy('path', 'referrer')->orderByDesc(DB::raw('count(distinct visitor_hash)'))->limit(50)->get()->all(),
            'countries' => $visits()->whereNotNull('country')
                ->selectRaw('country, count(*) as views, count(distinct visitor_hash) as visitors, max(created_at) as last_visited_at')
                ->groupBy('country')->orderByDesc(DB::raw('count(distinct visitor_hash)'))->limit(25)->get()->all(),
        ];
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowDevopsDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.devops.aiku-public-analytics',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('aiku.io analytics'),
                    ],
                ],
            ]
        );
    }
}
