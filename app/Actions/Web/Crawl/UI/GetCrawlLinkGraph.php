<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl\UI;

use App\Actions\OrgAction;
use App\Enums\Web\Crawl\CrawlIssueSeverityEnum;
use App\Enums\Web\Crawl\CrawlLinkKindEnum;
use App\Enums\Web\Crawl\CrawlLinkPositionEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Models\Web\Crawl;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * The internal link graph of a crawl for the force graph: the most linked pages and the links between
 * them. Links in the header, menu, sidebar and footer can be left out, since they join every page to
 * the same few and hide how the content links.
 */
class GetCrawlLinkGraph extends OrgAction
{
    use WithSiteCrawlerAuthorisation;

    public const int MAX_NODES = 300;

    private const array TEMPLATE_POSITIONS = [CrawlLinkPositionEnum::HEADER, CrawlLinkPositionEnum::NAV, CrawlLinkPositionEnum::ASIDE, CrawlLinkPositionEnum::FOOTER];

    private Crawl $crawl;

    public function authorize(ActionRequest $request): bool
    {
        return $this->canViewCrawlsOf($request->user(), $this->crawl->website);
    }

    /**
     * @return array{nodes: array<int, array<string, mixed>>, links: array<int, array{source: string, target: string, count: int}>, total_pages: int, is_truncated: bool, has_links: bool}
     */
    public function handle(Crawl $crawl, bool $contentOnly): array
    {
        $totalPages = DB::table('crawl_pages')->where('crawl_id', $crawl->id)->where('status_code', 200)->count();

        $pages = DB::table('crawl_pages')
            ->where('crawl_id', $crawl->id)
            ->where('status_code', 200)
            ->orderByRaw('link_depth = 0 desc nulls last')
            ->orderByDesc('inlinks')
            ->limit(self::MAX_NODES)
            ->get(['id', 'url', 'url_hash', 'link_depth', 'inlinks', 'is_indexable'])
            ->keyBy('url_hash');

        $pagesWithErrors = DB::table('crawl_issues')
            ->where('crawl_id', $crawl->id)
            ->where('severity', CrawlIssueSeverityEnum::ERROR->value)
            ->whereIn('crawl_page_id', $pages->pluck('id'))
            ->distinct()
            ->pluck('crawl_page_id')
            ->flip();

        $hashes = $pages->keys()->all();

        $links = DB::table('crawl_links')
            ->where('crawl_id', $crawl->id)
            ->where('kind', CrawlLinkKindEnum::HYPERLINK->value)
            ->where('is_internal', true)
            ->whereIn('from_url_hash', $hashes)
            ->whereIn('to_url_hash', $hashes)
            ->whereColumn('from_url_hash', '!=', 'to_url_hash')
            ->when($contentOnly, fn ($query) => $query->where(fn ($query) => $query->whereNull('position')->orWhereNotIn('position', array_map(fn (CrawlLinkPositionEnum $position) => $position->value, self::TEMPLATE_POSITIONS))))
            ->groupBy('from_url_hash', 'to_url_hash')
            ->selectRaw('from_url_hash as source, to_url_hash as target, count(*) as count')
            ->get()
            ->map(fn (object $link) => ['source' => $link->source, 'target' => $link->target, 'count' => (int) $link->count])
            ->all();

        return [
            'nodes'        => $pages->map(fn (object $page) => [
                'id'           => $page->url_hash,
                'path'         => (string) (parse_url($page->url, PHP_URL_PATH) ?: '/'),
                'depth'        => $page->link_depth === null ? null : (int) $page->link_depth,
                'inlinks'      => (int) $page->inlinks,
                'is_indexable' => (bool) $page->is_indexable,
                'has_errors'   => $pagesWithErrors->has($page->id),
            ])->values()->all(),
            'links'        => $links,
            'total_pages'  => $totalPages,
            'is_truncated' => $totalPages > $pages->count(),
            'has_links'    => DB::table('crawl_links')->where('crawl_id', $crawl->id)->exists(),
        ];
    }

    public function asController(Crawl $crawl, ActionRequest $request): array
    {
        abort_unless($crawl->type === CrawlTypeEnum::AUDIT, 404);

        $this->crawl = $crawl;
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($crawl, $request->boolean('content_only', true));
    }
}
