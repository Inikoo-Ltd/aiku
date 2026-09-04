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
use App\Enums\UI\DevOps\AikuPublicAnalyticsTabsEnum;
use App\InertiaTable\InertiaTable;
use App\Models\SysAdmin\Group;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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
        $this->initialisationFromGroup(app('group'), $request)->withTab(AikuPublicAnalyticsTabsEnum::values());

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
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => AikuPublicAnalyticsTabsEnum::navigation(),
                ],

                AikuPublicAnalyticsTabsEnum::OVERVIEW->value => $this->tab == AikuPublicAnalyticsTabsEnum::OVERVIEW->value ?
                    fn () => $this->handle()
                    : Inertia::optional(fn () => $this->handle()),

                AikuPublicAnalyticsTabsEnum::ARTICLES->value => $this->tab == AikuPublicAnalyticsTabsEnum::ARTICLES->value ?
                    fn () => $this->getArticlesPaginator()
                    : Inertia::optional(fn () => $this->getArticlesPaginator()),

                AikuPublicAnalyticsTabsEnum::HASHTAGS->value => $this->tab == AikuPublicAnalyticsTabsEnum::HASHTAGS->value ?
                    fn () => $this->getHashtagsPaginator()
                    : Inertia::optional(fn () => $this->getHashtagsPaginator()),
            ]
        )->table($this->articlesTableStructure())
            ->table($this->hashtagsTableStructure());
    }

    private function getArticlesPaginator(): LengthAwarePaginator
    {
        $rows = $this->sortRows(collect($this->getArticleStats()), AikuPublicAnalyticsTabsEnum::ARTICLES->value, '-committed_at', ['title', 'committed_at', 'visitors', 'views', 'last_visited_at']);

        return new LengthAwarePaginator($rows, $rows->count(), max($rows->count(), 1), 1, ['path' => request()->url()]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $sortableKeys
     * @return Collection<int, array<string, mixed>>
     */
    private function sortRows(Collection $rows, string $tableName, string $defaultSort, array $sortableKeys): Collection
    {
        $sort = (string) request()->query($tableName.'_sort', $defaultSort);
        $descending = str_starts_with($sort, '-');
        $key = ltrim($sort, '-');

        if (!in_array($key, $sortableKeys)) {
            return $rows;
        }

        return $rows->sortBy($key, SORT_NATURAL | SORT_FLAG_CASE, $descending)->values();
    }

    private function getHashtagsPaginator(): LengthAwarePaginator
    {
        $rows = $this->sortRows($this->getHashtagStats(), AikuPublicAnalyticsTabsEnum::HASHTAGS->value, '-views', ['hashtag', 'articles', 'visitors', 'views', 'last_visited_at']);

        return new LengthAwarePaginator($rows, $rows->count(), max($rows->count(), 1), 1, ['path' => request()->url()]);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function getHashtagStats(): Collection
    {
        $articles = collect($this->getArticleStats());
        $visitorHashes = $this->getArticleVisitorHashes();

        return BlogPosts::all()
            ->flatMap(fn (array $post) => array_map(fn (string $tag) => ['tag' => $tag, 'slug' => $post['slug']], array_filter($post['tags'])))
            ->groupBy('tag')
            ->map(function (Collection $group, string $tag) use ($articles, $visitorHashes) {
                $rows = $articles->whereIn('slug', $group->pluck('slug'));

                return [
                    'hashtag'         => $tag,
                    'articles'        => $rows->count(),
                    'visitors'        => $rows->pluck('slug')->flatMap(fn (string $slug) => $visitorHashes[$slug] ?? [])->unique()->count(),
                    'views'           => (int) $rows->sum('views'),
                    'last_visited_at' => $rows->pluck('last_visited_at')->filter()->max(),
                ];
            })
            ->values();
    }

    /** @return array<string, array<int, string>> */
    private function getArticleVisitorHashes(): array
    {
        return DB::table('aiku_public_visits')->where('is_bot', false)->where('path', 'like', '/blog/%')
            ->selectRaw('substr(path, 7) as slug, visitor_hash')
            ->distinct()->get()
            ->groupBy('slug')
            ->map(fn (Collection $rows) => $rows->pluck('visitor_hash')->all())
            ->all();
    }

    public function hashtagsTableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->name(AikuPublicAnalyticsTabsEnum::HASHTAGS->value)
                ->pageName(AikuPublicAnalyticsTabsEnum::HASHTAGS->value.'Page')
                ->withLabelRecord([__('hashtag'), __('hashtags')])
                ->defaultSort('-views')
                ->column(key: 'hashtag', label: __('Hashtag'), canBeHidden: false, sortable: true)
                ->column(key: 'articles', label: __('Articles'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'visitors', label: __('Visitors'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'views', label: __('Views'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'last_visited_at', label: __('Last visit'), canBeHidden: false, sortable: true, align: 'right');
        };
    }

    public function articlesTableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->name(AikuPublicAnalyticsTabsEnum::ARTICLES->value)
                ->pageName(AikuPublicAnalyticsTabsEnum::ARTICLES->value.'Page')
                ->withLabelRecord([__('article'), __('articles')])
                ->defaultSort('-committed_at')
                ->column(key: 'title', label: __('Article'), canBeHidden: false, sortable: true)
                ->column(key: 'committed_at', label: __('Published'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'visitors', label: __('Visitors'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'views', label: __('Views'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'last_visited_at', label: __('Last visit'), canBeHidden: false, sortable: true, align: 'right');
        };
    }

    /** Suspect visitors: no referrer and a single view in the window, the signature of headless scraper farms; excluded from every stat, counted per day.
     *
     * @return array{daily: array<int, object>, pages: array<int, object>, searches: array<int, object>, referrers: array<int, object>, page_referrers: array<int, object>, countries: array<int, object>, bots: array<int, object>, articles: array<int, array<string, mixed>>} */
    public function handle(int $days = 30): array
    {
        $since = now()->subDays($days);
        $suspects = DB::table('aiku_public_visits')->where('is_bot', false)->where('created_at', '>', $since)
            ->select('visitor_hash')->groupBy('visitor_hash')->havingRaw('count(*) = 1 and bool_and(referrer is null)');
        $visits = fn () => DB::table('aiku_public_visits')->where('is_bot', false)->where('created_at', '>', $since)->whereNotIn('visitor_hash', $suspects);

        return [
            'bots' => DB::table('aiku_public_visits')->where('is_bot', true)->where('created_at', '>', now()->subDays($days))
                ->selectRaw('user_agent, count(*) as views, count(distinct visitor_hash) as visitors, max(created_at) as last_visited_at')
                ->groupBy('user_agent')->orderByDesc(DB::raw('count(*)'))->limit(25)->get()->all(),
            'daily' => DB::table('aiku_public_visits')->where('is_bot', false)->where('created_at', '>', $since)
                ->selectRaw('created_at::date as day, count(*) filter (where visitor_hash not in ('.$suspects->toSql().')) as views, count(distinct visitor_hash) filter (where visitor_hash not in ('.$suspects->toSql().')) as visitors, count(*) filter (where visitor_hash in ('.$suspects->toSql().')) as suspect', array_merge($suspects->getBindings(), $suspects->getBindings(), $suspects->getBindings()))
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
