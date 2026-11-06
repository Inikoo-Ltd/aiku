<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl\UI;

use App\Actions\OrgAction;
use App\Enums\Web\Crawl\CrawlIssueSeverityEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Models\Web\Crawl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * How a crawled website is built: how many clicks each page is from the home page, and the folders
 * of its URLs with how many pages each holds, how many can be indexed and how many have errors.
 */
class ShowCrawlStructure extends OrgAction
{
    use WithSiteCrawlerAuthorisation;

    private const int MAX_TREE_LEVELS = 3;

    private const int CHILDREN_SHOWN = 50;

    private const int DEPTH_BUCKETS = 10;

    private const string URL_PATH_PATTERN = '^[A-Za-z][A-Za-z0-9+.-]*://[^/?#]*([^?#]*)';

    private Crawl $crawl;

    public function authorize(ActionRequest $request): bool
    {
        return $this->canViewCrawlsOf($request->user(), $this->crawl->website);
    }

    /**
     * @return array{depths: array<int, array{label: string, depth: int|null, pages: int}>, tree: array<int, array<string, mixed>>, pages: int}
     */
    public function handle(Crawl $crawl): array
    {
        $depthRows = DB::table('crawl_pages')
            ->where('crawl_id', $crawl->id)
            ->where('status_code', 200)
            ->where('content_type', 'ilike', '%html%')
            ->groupByRaw('1')
            ->selectRaw('case when link_depth is null then -1 else least(link_depth, ?) end as bucket, count(*) as pages, coalesce(max(link_depth), 0) as deepest', [self::DEPTH_BUCKETS])
            ->get();

        return [
            'pages'  => (int) $depthRows->sum('pages'),
            'depths' => $this->depths($depthRows->pluck('pages', 'bucket'), (int) $depthRows->max('deepest')),
            'tree'   => $this->tree($crawl),
        ];
    }

    /**
     * @param  Collection<int, int|string>  $counts
     *
     * @return array<int, array{label: string, depth: int|null, pages: int}>
     */
    private function depths(Collection $counts, int $deepest): array
    {
        $rows = [];

        for ($depth = 0; $depth <= min(self::DEPTH_BUCKETS, $deepest); $depth++) {
            $rows[] = [
                'label' => match (true) {
                    $depth === 0                   => __('Home page'),
                    $depth === self::DEPTH_BUCKETS => __(':clicks or more clicks', ['clicks' => $depth]),
                    default                        => trans_choice(':clicks click|:clicks clicks', $depth, ['clicks' => $depth]),
                },
                'depth' => $depth,
                'pages' => (int) $counts->get($depth, 0),
            ];
        }

        if ($counts->has(-1)) {
            $rows[] = ['label' => __('Not reached by links'), 'depth' => null, 'pages' => (int) $counts->get(-1)];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tree(Crawl $crawl): array
    {
        $rows = DB::select(
            <<<'SQL'
            with p as (
                select array_remove(string_to_array(coalesce(substring(cp.url from ?), ''), '/'), '') as segs,
                    cp.is_indexable as indexable,
                    exists (select 1 from crawl_issues ci where ci.crawl_page_id = cp.id and ci.severity = ?) as has_error
                from crawl_pages cp
                where cp.crawl_id = ? and cp.status_code = 200 and cp.content_type ilike '%html%'
            ), prefixes as (
                select l as lvl, segs[1:l] as prefix, indexable, has_error
                from p cross join generate_series(1, ?) as l
                where cardinality(segs) >= l
            ), folders as (
                select lvl, array_to_string(prefix[1:lvl - 1], '/') as parent, prefix[lvl] as name, array_to_string(prefix, '/') as path,
                    count(*) as pages, count(*) filter (where indexable) as indexable, count(*) filter (where has_error) as errors
                from prefixes
                group by lvl, prefix
            ), ranked as (
                select *, row_number() over (partition by lvl, parent order by pages desc, name collate "C") as rn from folders
            )
            select lvl, parent, name, path, pages, indexable, errors, 0 as hidden, rn from ranked where rn <= ?
            union all
            select lvl, parent, null, null, sum(pages), sum(indexable), sum(errors), count(*), ? from ranked where rn > ? group by lvl, parent
            order by lvl, parent, rn
            SQL,
            [self::URL_PATH_PATTERN, CrawlIssueSeverityEnum::ERROR->value, $crawl->id, self::MAX_TREE_LEVELS, self::CHILDREN_SHOWN, self::CHILDREN_SHOWN + 1, self::CHILDREN_SHOWN]
        );

        $byParent = [];
        foreach ($rows as $row) {
            $byParent[$row->parent][] = $row;
        }

        return $this->branch($byParent, '');
    }

    /**
     * @param  array<string, array<int, object>>  $byParent
     *
     * @return array<int, array<string, mixed>>
     */
    private function branch(array $byParent, string $parent): array
    {
        return array_map(fn (object $row) => [
            'path'      => $row->path === null ? null : '/'.$row->path,
            'name'      => $row->path === null ? __(':count more folders', ['count' => $row->hidden]) : $row->name,
            'pages'     => (int) $row->pages,
            'indexable' => (int) $row->indexable,
            'errors'    => (int) $row->errors,
            'children'  => $row->path === null ? [] : $this->branch($byParent, $row->path),
        ], $byParent[$parent] ?? []);
    }

    public function asController(Crawl $crawl, ActionRequest $request): Crawl
    {
        abort_unless($crawl->type === CrawlTypeEnum::AUDIT, 404);

        $this->crawl = $crawl;
        $this->initialisationFromGroup(group(), $request);

        return $crawl;
    }

    public function htmlResponse(Crawl $crawl): Response
    {
        $title = __('Site structure');

        return Inertia::render(
            'Org/Web/Crawler/CrawlStructure',
            [
                'breadcrumbs' => [
                    ...ShowCrawl::make()->getBreadcrumbs($crawl),
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => ['name' => 'grp.websites.crawler.structure', 'parameters' => ['crawl' => $crawl->id]],
                            'label' => $title,
                        ],
                    ],
                ],
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-spider'],
                        'title' => __('Site crawler'),
                    ],
                    'meta'  => [
                        [
                            'key'   => 'website',
                            'label' => $crawl->website->domain,
                        ],
                    ],
                ],
                'structure'   => $this->handle($crawl),
                'isFullAudit' => $crawl->is_full_audit,
                'routes'      => [
                    'crawl'     => ['name' => 'grp.websites.crawler.show', 'parameters' => ['crawl' => $crawl->id]],
                    'linkGraph' => ['name' => 'grp.json.crawl.link_graph', 'parameters' => ['crawl' => $crawl->id]],
                ],
            ]
        );
    }
}
