<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Docs\UI;

use App\Actions\OrgAction;
use App\Actions\UI\AikuPublic\BlogPosts;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowDocsDashboard extends OrgAction
{
    use WithInertia;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasGroupAccess();
    }

    public function asController(ActionRequest $request): Group
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->group;
    }

    public function htmlResponse(Group $group, ActionRequest $request): Response
    {
        $title = __('Docs');

        return Inertia::render(
            'Docs/Dashboard',
            [
                'breadcrumbs'      => $this->getBreadcrumbs(),
                'title'            => $title,
                'pageHead'         => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-file-alt'],
                        'title' => $title,
                    ],
                ],
                'publicSiteVisits' => $this->getPublicSiteVisits(),
                'modules'          => $this->getModules(),
            ]
        );
    }

    /** @return array<int, array{category: string, docs: array<int, array{title: string, summary: string|null, url: string}>}> */
    public function getModules(): array
    {
        return BlogPosts::all('docs')->sortBy('title')->groupBy('category')->sortKeys()
            ->map(fn ($docs, string $category) => [
                'category' => $category,
                'docs'     => $docs->map(fn (array $doc) => [
                    'title'   => $doc['title'],
                    'summary' => $doc['summary'],
                    'url'     => route('aiku-public.docs.show', $doc['slug']),
                ])->values()->all(),
            ])->values()->all();
    }

    /** @return array{daily: array<int, object>, visitors: int, views: int, top_referrer: string|null} */
    public function getPublicSiteVisits(): array
    {
        $visits = fn (int $days) => DB::table('aiku_public_visits')->where('is_bot', false)
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


    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'icon'  => 'fal fa-file-alt',
                        'route' => ['name' => 'grp.docs'],
                        'label' => __('Docs'),
                    ],
                ],
            ]
        );
    }
}
