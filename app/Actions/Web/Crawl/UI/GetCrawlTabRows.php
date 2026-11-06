<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl\UI;

use App\Actions\Web\Crawl\AuditWebsite;
use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use App\Enums\Web\Crawl\CrawlTabEnum;
use App\Models\Web\Crawl;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The rows of one tab and filter of a crawl, as the site crawler table and its export show them: the
 * crawled pages, or the external links or images those pages use. A filter is either one of the
 * tab's state filters or an issue type, which always lists pages.
 */
class GetCrawlTabRows
{
    use AsObject;

    private const array PAGE_COLUMNS = [
        'status_code'             => 'crawl_pages.status_code',
        'indexability'            => 'crawl_pages.is_indexable',
        'content_type'            => 'crawl_pages.content_type',
        'title'                   => 'crawl_pages.title',
        'title_length'            => 'char_length(crawl_pages.title)',
        'title_pixels'            => "(crawl_pages.facts->>'title_px')::int",
        'meta_description'        => 'crawl_pages.meta_description',
        'meta_description_length' => 'char_length(crawl_pages.meta_description)',
        'meta_description_pixels' => "(crawl_pages.facts->>'meta_description_px')::int",
        'reading_ease'            => "(crawl_pages.facts->>'reading_ease')::numeric",
        'h1'                      => 'crawl_pages.h1',
        'h1_length'               => 'char_length(crawl_pages.h1)',
        'h1_count'                => 'crawl_pages.h1_count',
        'h2_1'                    => "crawl_pages.facts->'h2'->>0",
        'h2_2'                    => "crawl_pages.facts->'h2'->>1",
        'h2_count'                => "coalesce((crawl_pages.facts->>'h2_count')::int, 0)",
        'word_count'              => 'crawl_pages.word_count',
        'link_depth'              => 'crawl_pages.link_depth',
        'inlinks'                 => 'crawl_pages.inlinks',
        'internal_outlinks'       => "coalesce((crawl_pages.facts->>'internal_outlinks')::int, 0)",
        'response_ms'             => 'crawl_pages.response_ms',
        'bytes'                   => 'crawl_pages.bytes',
        'redirect_to'             => 'crawl_pages.redirect_to',
        'redirect_hops'           => 'crawl_pages.redirect_hops',
        'url_length'              => 'char_length(crawl_pages.url)',
        'canonical'               => 'crawl_pages.canonical',
        'robots_meta'             => 'crawl_pages.robots_meta',
        'x_robots_tag'            => "crawl_pages.facts->'headers'->>'x-robots-tag'",
        'hreflang_count'          => 'coalesce(jsonb_array_length(crawl_pages.hreflang), 0)',
        'pagination_next'         => "crawl_pages.facts->'pagination'->>'next'",
        'pagination_prev'         => "crawl_pages.facts->'pagination'->>'prev'",
        'images_without_alt'      => 'crawl_pages.images_without_alt',
        'is_in_sitemap'           => 'crawl_pages.is_in_sitemap',
        'sitemap_count'           => 'crawl_pages.sitemap_count',
    ];

    private const array NUMERIC_COLUMNS = [
        'status_code', 'title_length', 'title_pixels', 'meta_description_length', 'meta_description_pixels', 'h1_length', 'h1_count', 'h2_count', 'word_count', 'link_depth', 'inlinks',
        'internal_outlinks', 'response_ms', 'bytes', 'redirect_hops', 'url_length', 'hreflang_count', 'images_without_alt', 'sitemap_count',
    ];

    public const int MAX_PER_PAGE = 200;

    /**
     * @return array{source: string, issue: CrawlIssueTypeEnum|null, state_filter: string|null}
     */
    public function resolveFilter(CrawlTabEnum $tab, ?string $filter): array
    {
        $issue = $filter ? CrawlIssueTypeEnum::tryFrom($filter) : null;

        if ($issue) {
            return ['source' => 'pages', 'issue' => $issue, 'state_filter' => null];
        }

        $stateFilter = $filter && array_key_exists($filter, $tab->stateFilters()) ? $filter : null;

        return [
            'source'       => $stateFilter === 'blocked_by_robots' ? AuditWebsite::BLOCKED_BY_ROBOTS : $tab->source(),
            'issue'        => null,
            'state_filter' => $stateFilter === 'blocked_by_robots' ? null : $stateFilter,
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, numeric: bool}>
     */
    public function columns(CrawlTabEnum $tab, ?string $filter): array
    {
        $resolved = $this->resolveFilter($tab, $filter);

        $keys = match ($resolved['source']) {
            'link'  => ['status_code', 'content_type', 'inlinks'],
            'image' => ['status_code', 'bytes', 'alt_text', 'inlinks'],
            AuditWebsite::BLOCKED_BY_ROBOTS => ['inlinks'],
            default => $tab->pageColumns(),
        };

        if ($resolved['issue']) {
            $keys = [...array_values(array_diff($keys, ['details'])), 'details'];
        }

        $labels = self::columnLabels();

        return array_map(fn (string $key) => [
            'key'     => $key,
            'label'   => $labels[$key] ?? $key,
            'numeric' => in_array($key, [...self::NUMERIC_COLUMNS, 'bytes', 'reading_ease'], true),
        ], array_values(array_unique(array_filter($keys, fn (string $key) => $resolved['source'] !== 'pages' || isset(self::PAGE_COLUMNS[$key]) || $key === 'details'))));
    }

    public function query(Crawl $crawl, CrawlTabEnum $tab, ?string $filter, ?string $search = null): Builder
    {
        $resolved = $this->resolveFilter($tab, $filter);

        $query = $resolved['source'] === 'pages'
            ? $this->pagesQuery($crawl, $resolved['issue'], $resolved['state_filter'])
            : $this->resourcesQuery($crawl, $resolved['source'], $resolved['state_filter']);

        if ($search !== null && trim($search) !== '') {
            $table = $resolved['source'] === 'pages' ? 'crawl_pages' : 'crawl_resources';
            $query->where("$table.url", 'ilike', '%'.addcslashes(trim($search), '%_\\').'%');
        }

        return $query;
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function handle(Crawl $crawl, CrawlTabEnum $tab, ?string $filter, ?string $search, ?string $sort, bool $descending, int $page, int $perPage): array
    {
        $perPage  = max(1, min(self::MAX_PER_PAGE, $perPage));
        $columns  = array_column($this->columns($tab, $filter), 'key');
        $query    = $this->query($crawl, $tab, $filter, $search);
        $total    = (clone $query)->count();
        $sortable = ['url', ...array_diff($columns, ['details'])];

        if ($sort !== null && in_array($sort, $sortable, true)) {
            $query->orderByRaw('"'.$sort.'" '.($descending ? 'desc' : 'asc').' nulls last');
        } elseif (in_array('inlinks', $columns, true)) {
            $query->orderByDesc('inlinks');
        }

        $query->orderBy('url');

        $rows = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn (object $row) => $this->row($row, $columns))
            ->all();

        return [
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * @param  array<int, string>  $columns
     *
     * @return array<string, mixed>
     */
    public function row(object $row, array $columns): array
    {
        $values = [
            'url'      => $row->url,
            'url_hash' => $row->url_hash,
        ];

        foreach ($columns as $column) {
            $value = $row->$column ?? null;

            $values[$column] = match (true) {
                $column === 'details'                                  => $value === null ? null : json_decode($value, true),
                $column === 'indexability'                             => $value === null ? null : (bool) $value,
                $column === 'is_in_sitemap'                            => (bool) $value,
                $column === 'reading_ease'                             => $value === null ? null : (float) $value,
                in_array($column, self::NUMERIC_COLUMNS, true)         => $value === null ? null : (int) $value,
                default                                                => $value,
            };
        }

        return $values;
    }

    private function pagesQuery(Crawl $crawl, ?CrawlIssueTypeEnum $issue, ?string $stateFilter): Builder
    {
        $select = ['crawl_pages.url', 'crawl_pages.url_hash'];

        foreach (self::PAGE_COLUMNS as $key => $expression) {
            $select[] = DB::raw("$expression as \"$key\"");
        }

        $query = DB::table('crawl_pages')
            ->where('crawl_pages.crawl_id', $crawl->id)
            ->select($select);

        if ($issue) {
            $query->join('crawl_issues', 'crawl_issues.crawl_page_id', '=', 'crawl_pages.id')
                ->where('crawl_issues.type', $issue->value)
                ->addSelect('crawl_issues.details as details');
        }

        return match ($stateFilter) {
            'indexable'      => $query->where('crawl_pages.is_indexable', true),
            'non_indexable'  => $query->where('crawl_pages.is_indexable', false),
            'success'        => $query->whereBetween('crawl_pages.status_code', [200, 299]),
            'redirection'    => $query->whereBetween('crawl_pages.status_code', [300, 399]),
            'in_sitemap'     => $query->where('crawl_pages.is_in_sitemap', true),
            'multiple_h2'    => $query->whereRaw("coalesce((crawl_pages.facts->>'h2_count')::int, 0) > 1"),
            'not_in_sitemap' => $query->where('crawl_pages.is_in_sitemap', false),
            default          => $query,
        };
    }

    private function resourcesQuery(Crawl $crawl, string $kind, ?string $stateFilter): Builder
    {
        $inlinks = DB::table('crawl_links')
            ->whereColumn('crawl_links.crawl_id', 'crawl_resources.crawl_id')
            ->whereColumn('crawl_links.to_url_hash', 'crawl_resources.url_hash')
            ->selectRaw('count(*)');

        $altText = DB::table('crawl_links')
            ->whereColumn('crawl_links.crawl_id', 'crawl_resources.crawl_id')
            ->whereColumn('crawl_links.to_url_hash', 'crawl_resources.url_hash')
            ->where('crawl_links.kind', 'image')
            ->whereNotNull('crawl_links.anchor')
            ->limit(1)
            ->select('crawl_links.anchor');

        $query = DB::table('crawl_resources')
            ->where('crawl_resources.crawl_id', $crawl->id)
            ->where('crawl_resources.kind', $kind)
            ->select([
                'crawl_resources.url',
                'crawl_resources.url_hash',
                'crawl_resources.status_code',
                'crawl_resources.content_type',
                'crawl_resources.bytes',
            ])
            ->selectSub($inlinks, 'inlinks')
            ->selectSub($altText, 'alt_text');

        return match ($stateFilter) {
            'success'      => $query->whereBetween('crawl_resources.status_code', [200, 299]),
            'redirection'  => $query->whereBetween('crawl_resources.status_code', [300, 399]),
            'client_error' => $query->whereBetween('crawl_resources.status_code', [400, 499]),
            'server_error' => $query->whereBetween('crawl_resources.status_code', [500, 599]),
            'no_response'  => $query->whereNull('crawl_resources.status_code'),
            'over_size'    => $query->where('crawl_resources.bytes', '>', CrawlIssueTypeEnum::LARGE_IMAGE_KB * 1024),
            'missing_alt'  => $query->whereExists(fn ($exists) => $exists->from('crawl_links')
                ->whereColumn('crawl_links.crawl_id', 'crawl_resources.crawl_id')
                ->whereColumn('crawl_links.to_url_hash', 'crawl_resources.url_hash')
                ->where('crawl_links.kind', 'image')
                ->whereNull('crawl_links.anchor')),
            default        => $query,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function columnLabels(): array
    {
        return [
            'url'                     => __('Address'),
            'status_code'             => __('Status code'),
            'indexability'            => __('Indexability'),
            'content_type'            => __('Content type'),
            'title'                   => __('Title'),
            'title_length'            => __('Title length'),
            'title_pixels'            => __('Title pixel width'),
            'meta_description'        => __('Meta description'),
            'meta_description_length' => __('Meta description length'),
            'meta_description_pixels' => __('Meta description pixel width'),
            'reading_ease'            => __('Flesch reading ease'),
            'h1'                      => __('H1'),
            'h1_length'               => __('H1 length'),
            'h1_count'                => __('H1 count'),
            'h2_1'                    => __('H2-1'),
            'h2_2'                    => __('H2-2'),
            'h2_count'                => __('H2 count'),
            'word_count'              => __('Word count'),
            'link_depth'              => __('Crawl depth'),
            'inlinks'                 => __('Inlinks'),
            'internal_outlinks'       => __('Internal outlinks'),
            'response_ms'             => __('Response time (ms)'),
            'bytes'                   => __('Size (bytes)'),
            'redirect_to'             => __('Redirect URL'),
            'redirect_hops'           => __('Redirect hops'),
            'url_length'              => __('Length'),
            'canonical'               => __('Canonical'),
            'robots_meta'             => __('Meta robots'),
            'x_robots_tag'            => __('X-Robots-Tag'),
            'hreflang_count'          => __('Hreflang links'),
            'pagination_next'         => __('rel="next"'),
            'pagination_prev'         => __('rel="prev"'),
            'images_without_alt'      => __('Images without alt'),
            'is_in_sitemap'           => __('In sitemap'),
            'sitemap_count'           => __('Sitemaps'),
            'alt_text'                => __('Alt text'),
            'details'                 => __('Details'),
        ];
    }
}
