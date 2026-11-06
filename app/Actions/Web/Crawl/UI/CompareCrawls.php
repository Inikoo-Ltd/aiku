<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl\UI;

use App\Actions\OrgAction;
use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTabEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Models\Web\Crawl;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * Two crawls of the same website side by side: for every issue, how many URLs had it before and
 * now, which URLs got it and which were fixed. URLs are matched by address, so a URL missing from
 * one of the crawls counts as fixed or new.
 */
class CompareCrawls extends OrgAction
{
    use WithSiteCrawlerAuthorisation;

    public const int URLS_SHOWN = 500;

    private Crawl $crawl;

    private ?Crawl $previous = null;

    public function authorize(ActionRequest $request): bool
    {
        return $this->canViewCrawlsOf($request->user(), $this->crawl->website);
    }

    /**
     * @return array<int, array{type: string, label: string, severity: string, tab: string, previous: int, current: int, added: int, resolved: int}>
     */
    public function handle(Crawl $crawl, Crawl $previous): array
    {
        $current = $this->issueUrls($crawl);
        $before  = $this->issueUrls($previous);

        $counts = DB::query()
            ->fromSub($current->unionAll($before), 'issue_urls')
            ->groupBy('type')
            ->select('type')
            ->selectRaw('count(*) filter (where crawl_id = ?) as current', [$crawl->id])
            ->selectRaw('count(*) filter (where crawl_id = ?) as previous', [$previous->id])
            ->get()
            ->keyBy('type');

        $added    = $this->differenceCounts($crawl, $previous);
        $resolved = $this->differenceCounts($previous, $crawl);
        $labels   = CrawlIssueTypeEnum::labels();
        $tabs     = CrawlTabEnum::labels();

        return collect(CrawlIssueTypeEnum::cases())
            ->filter(fn (CrawlIssueTypeEnum $type) => $counts->has($type->value))
            ->map(fn (CrawlIssueTypeEnum $type) => [
                'type'          => $type->value,
                'label'         => $labels[$type->value],
                'severity'      => $type->severity()->value,
                'severity_rank' => $type->severity()->rank(),
                'tab'           => $tabs[$type->tab()->value],
                'previous'      => (int) $counts->get($type->value)->previous,
                'current'       => (int) $counts->get($type->value)->current,
                'added'         => (int) ($added[$type->value] ?? 0),
                'resolved'      => (int) ($resolved[$type->value] ?? 0),
            ])
            ->sortBy([['severity_rank', 'asc'], fn (array $a, array $b) => ($b['added'] + $b['resolved']) <=> ($a['added'] + $a['resolved'])])
            ->values()
            ->all();
    }

    /**
     * The URLs that have the issue in one crawl and not in the other.
     *
     * @return array{rows: array<int, string>, total: int}
     */
    public function differenceUrls(Crawl $in, Crawl $notIn, CrawlIssueTypeEnum $type): array
    {
        $query = $this->issueUrls($in)
            ->where('crawl_issues.type', $type->value)
            ->whereNotExists(fn ($exists) => $exists->from('crawl_issues as other_issues')
                ->join('crawl_pages as other_pages', 'other_pages.id', '=', 'other_issues.crawl_page_id')
                ->where('other_issues.crawl_id', $notIn->id)
                ->where('other_issues.type', $type->value)
                ->whereColumn('other_pages.url_hash', 'crawl_pages.url_hash'));

        return [
            'rows'  => (clone $query)->select('crawl_pages.url')->orderBy('crawl_pages.url')->limit(self::URLS_SHOWN)->pluck('url')->all(),
            'total' => $query->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function differenceCounts(Crawl $in, Crawl $notIn): array
    {
        return $this->issueUrls($in)
            ->whereNotExists(fn ($exists) => $exists->from('crawl_issues as other_issues')
                ->join('crawl_pages as other_pages', 'other_pages.id', '=', 'other_issues.crawl_page_id')
                ->where('other_issues.crawl_id', $notIn->id)
                ->whereColumn('other_issues.type', 'crawl_issues.type')
                ->whereColumn('other_pages.url_hash', 'crawl_pages.url_hash'))
            ->reorder()
            ->groupBy('crawl_issues.type')
            ->select(DB::raw('crawl_issues.type as type, count(*) as urls'))
            ->pluck('urls', 'type')
            ->map(fn ($urls) => (int) $urls)
            ->all();
    }

    private function issueUrls(Crawl $crawl): Builder
    {
        return DB::table('crawl_issues')
            ->join('crawl_pages', 'crawl_pages.id', '=', 'crawl_issues.crawl_page_id')
            ->where('crawl_issues.crawl_id', $crawl->id)
            ->select(['crawl_issues.crawl_id', 'crawl_issues.type', 'crawl_pages.url_hash']);
    }

    public function asController(Crawl $crawl, ActionRequest $request): Crawl
    {
        abort_unless($crawl->type === CrawlTypeEnum::AUDIT, 404);

        $this->crawl = $crawl;
        $this->initialisationFromGroup(group(), $request);

        $withId         = (int) $request->query('with');
        $this->previous = $this->finishedCrawls($crawl)->firstWhere('id', $withId)
            ?? $this->finishedCrawls($crawl)->first(fn (Crawl $other) => $other->id < $crawl->id);

        return $crawl;
    }

    /**
     * @return Collection<int, Crawl>
     */
    private function finishedCrawls(Crawl $crawl): Collection
    {
        return Crawl::where('website_id', $crawl->website_id)
            ->where('type', CrawlTypeEnum::AUDIT)
            ->where('state', CrawlStateEnum::FINISH)
            ->whereNotNull('health_score')
            ->where('id', '!=', $crawl->id)
            ->orderByDesc('id')
            ->get();
    }

    public function htmlResponse(Crawl $crawl, ActionRequest $request): Response
    {
        $title    = __('Compare crawls');
        $previous = $this->previous;
        $type     = CrawlIssueTypeEnum::tryFrom((string) $request->query('type'));
        $side     = $request->query('side') === 'resolved' ? 'resolved' : 'added';

        return Inertia::render(
            'Org/Web/Crawler/CrawlComparison',
            [
                'breadcrumbs' => [
                    ...ShowCrawl::make()->getBreadcrumbs($crawl),
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => ['name' => 'grp.websites.crawler.compare', 'parameters' => ['crawl' => $crawl->id]],
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
                'current'     => $this->crawlSummary($crawl),
                'previous'    => $previous ? $this->crawlSummary($previous) : null,
                'others'      => $this->finishedCrawls($crawl)->map(fn (Crawl $other) => [
                    'id'    => $other->id,
                    'label' => trim(($other->name ? $other->name.' · ' : '').$other->end_at?->format('Y-m-d H:i').($other->is_full_audit ? '' : ' · '.__('Limited'))),
                ])->values()->all(),
                'issues'      => $previous ? $this->handle($crawl, $previous) : [],
                'selected'    => $previous && $type ? [
                    'type'  => $type->value,
                    'label' => CrawlIssueTypeEnum::labels()[$type->value],
                    'side'  => $side,
                    ...($side === 'resolved' ? $this->differenceUrls($previous, $crawl, $type) : $this->differenceUrls($crawl, $previous, $type)),
                ] : null,
                'routes'      => [
                    'self'  => ['name' => 'grp.websites.crawler.compare', 'parameters' => ['crawl' => $crawl->id]],
                    'crawl' => ['name' => 'grp.websites.crawler.show', 'parameters' => ['crawl' => $crawl->id]],
                ],
            ]
        );
    }

    private function crawlSummary(Crawl $crawl): array
    {
        return [
            'id'             => $crawl->id,
            'name'           => $crawl->name,
            'end_at'         => $crawl->end_at,
            'urls_processed' => $crawl->urls_processed,
            'health_score'   => $crawl->health_score === null ? null : (float) $crawl->health_score,
            'errors'         => $crawl->number_errors,
            'warnings'       => $crawl->number_warnings,
            'notices'        => $crawl->number_notices,
            'is_full_audit'  => $crawl->is_full_audit,
        ];
    }
}
