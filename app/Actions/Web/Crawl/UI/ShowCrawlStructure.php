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
        $pages = DB::table('crawl_pages')
            ->where('crawl_id', $crawl->id)
            ->where('status_code', 200)
            ->where('content_type', 'ilike', '%html%')
            ->get(['id', 'url', 'link_depth', 'is_indexable', 'is_in_sitemap']);

        $pagesWithErrors = DB::table('crawl_issues')
            ->where('crawl_id', $crawl->id)
            ->where('severity', CrawlIssueSeverityEnum::ERROR->value)
            ->distinct()
            ->pluck('crawl_page_id')
            ->flip();

        return [
            'pages'  => $pages->count(),
            'depths' => $this->depths($pages),
            'tree'   => $this->tree($pages, $pagesWithErrors),
        ];
    }

    /**
     * @return array<int, array{label: string, depth: int|null, pages: int}>
     */
    private function depths($pages): array
    {
        $counts = $pages->groupBy(fn ($page) => $page->link_depth === null ? 'none' : min((int) $page->link_depth, self::DEPTH_BUCKETS))->map->count();
        $rows   = [];

        for ($depth = 0; $depth <= min(self::DEPTH_BUCKETS, (int) $pages->max('link_depth')); $depth++) {
            $rows[] = [
                'label' => match (true) {
                    $depth === 0                  => __('Home page'),
                    $depth === self::DEPTH_BUCKETS => __(':clicks or more clicks', ['clicks' => $depth]),
                    default                       => trans_choice(':clicks click|:clicks clicks', $depth, ['clicks' => $depth]),
                },
                'depth' => $depth,
                'pages' => (int) $counts->get($depth, 0),
            ];
        }

        if ($counts->has('none')) {
            $rows[] = ['label' => __('Not reached by links'), 'depth' => null, 'pages' => (int) $counts->get('none')];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tree($pages, $pagesWithErrors): array
    {
        $root = ['children' => []];

        foreach ($pages as $page) {
            $segments = array_values(array_filter(explode('/', (string) parse_url($page->url, PHP_URL_PATH)), fn (string $segment) => $segment !== ''));
            $node     = &$root;
            $path     = '';

            foreach (array_slice($segments, 0, self::MAX_TREE_LEVELS) as $segment) {
                $path .= '/'.$segment;

                $node['children'][$segment] ??= ['path' => $path, 'name' => $segment, 'pages' => 0, 'indexable' => 0, 'errors' => 0, 'children' => []];
                $node = &$node['children'][$segment];

                $node['pages']++;
                $node['indexable'] += $page->is_indexable ? 1 : 0;
                $node['errors']    += $pagesWithErrors->has($page->id) ? 1 : 0;
            }

            unset($node);
        }

        return $this->sorted($root['children']);
    }

    /**
     * @param  array<string, array<string, mixed>>  $children
     *
     * @return array<int, array<string, mixed>>
     */
    private function sorted(array $children): array
    {
        uasort($children, fn (array $a, array $b) => [$b['pages'], $a['name']] <=> [$a['pages'], $b['name']]);

        $shown  = array_slice($children, 0, self::CHILDREN_SHOWN);
        $hidden = array_slice($children, self::CHILDREN_SHOWN);

        return [
            ...array_values(array_map(fn (array $child) => [...$child, 'children' => $this->sorted($child['children'])], $shown)),
            ...($hidden === [] ? [] : [[
                'path'      => null,
                'name'      => __(':count more folders', ['count' => count($hidden)]),
                'pages'     => array_sum(array_column($hidden, 'pages')),
                'indexable' => array_sum(array_column($hidden, 'indexable')),
                'errors'    => array_sum(array_column($hidden, 'errors')),
                'children'  => [],
            ]]),
        ];
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
