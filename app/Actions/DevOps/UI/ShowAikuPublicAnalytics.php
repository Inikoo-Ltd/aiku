<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\UI;

use App\Actions\OrgAction;
use App\Actions\UI\AikuPublic\BlogPosts;
use App\Actions\UI\WithInertia;
use App\InertiaTable\InertiaTable;
use App\Models\SysAdmin\Group;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
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
                'articles'    => $this->getArticlesPaginator(),
            ]
        )->table($this->articlesTableStructure());
    }

    private function getArticlesPaginator(): LengthAwarePaginator
    {
        $rows = collect($this->getArticleStats());

        return new LengthAwarePaginator($rows, $rows->count(), max($rows->count(), 1), 1, ['path' => request()->url()]);
    }

    public function articlesTableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->name('articles')
                ->pageName('articlesPage')
                ->withLabelRecord([__('article'), __('articles')])
                ->column(key: 'title', label: __('Article'), canBeHidden: false)
                ->column(key: 'committed_at', label: __('Published'), canBeHidden: false, align: 'right')
                ->column(key: 'visitors', label: __('Visitors'), canBeHidden: false, align: 'right')
                ->column(key: 'views', label: __('Views'), canBeHidden: false, align: 'right')
                ->column(key: 'last_visited_at', label: __('Last visit'), canBeHidden: false, align: 'right');
        };
    }

    /** @return array{daily: array<int, object>, pages: array<int, object>, searches: array<int, object>, referrers: array<int, object>, page_referrers: array<int, object>, countries: array<int, object>, articles: array<int, array<string, mixed>>} */
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

    /** @return array<int, array<string, mixed>> */
    public function getArticleStats(): array
    {
        $stats = DB::table('aiku_public_visits')->where('path', 'like', '/blog/%')
            ->selectRaw('substr(path, 7) as slug, count(*) as views, count(distinct visitor_hash) as visitors, max(created_at) as last_visited_at')
            ->groupBy('slug')->get()->keyBy('slug');

        $committedDates = $this->getArticleCommitDates();

        return BlogPosts::all()->map(fn (array $post) => [
            'slug'            => $post['slug'],
            'title'           => $post['title'],
            'url'             => 'https://aiku.io/blog/'.$post['slug'],
            'date'            => $post['date']->toDateString(),
            'committed_at'    => $committedDates[$post['slug']] ?? null,
            'visitors'        => (int) ($stats[$post['slug']]->visitors ?? 0),
            'views'           => (int) ($stats[$post['slug']]->views ?? 0),
            'last_visited_at' => $stats[$post['slug']]->last_visited_at ?? null,
        ])->all();
    }

    /** @return array<string, string> */
    private function getArticleCommitDates(): array
    {
        return cache()->remember('aiku_public_article_commit_dates', 3600, function () {
            $result = Process::path(base_path())
                ->run('git log --diff-filter=A --format="C %aI" --name-only -- resources/markdown/aiku-public/blog');
            if (! $result->successful()) {
                return [];
            }
            $dates = [];
            $current = null;
            foreach (explode("\n", $result->output()) as $line) {
                if (str_starts_with($line, 'C ')) {
                    $current = substr($line, 2);
                } elseif ($current && str_ends_with($line, '.md')) {
                    $dates[basename($line, '.md')] ??= $current;
                }
            }

            return $dates;
        });
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
