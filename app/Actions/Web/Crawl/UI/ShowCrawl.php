<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl\UI;

use App\Actions\OrgAction;
use App\Actions\Web\Crawl\AuditWebsite;
use App\Enums\Web\Crawl\CrawlIssueSeverityEnum;
use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTabEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Models\Web\Crawl;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * One crawl in the site crawler: the tabs with their filters and counts, and the rows of the
 * selected tab and filter.
 */
class ShowCrawl extends OrgAction
{
    use WithSiteCrawlerAuthorisation;

    private Crawl $crawl;

    private const int DEFAULT_PER_PAGE = 100;

    private CrawlTabEnum $crawlTab;

    public function authorize(ActionRequest $request): bool
    {
        return $this->canViewCrawlsOf($request->user(), $this->crawl->website);
    }

    public function asController(Crawl $crawl, ActionRequest $request): Crawl
    {
        abort_unless($crawl->type === CrawlTypeEnum::AUDIT, 404);

        $this->crawl = $crawl;
        $this->initialisationFromGroup(group(), $request);

        $this->crawlTab = CrawlTabEnum::tryFrom((string) $request->query('tab')) ?? CrawlTabEnum::INTERNAL;

        return $crawl;
    }

    public function htmlResponse(Crawl $crawl, ActionRequest $request): Response
    {
        $filter   = $request->query('filter');
        $filter   = is_string($filter) && $filter !== '' ? $filter : null;
        $search   = is_string($request->query('search')) ? $request->query('search') : null;
        $sort     = is_string($request->query('sort')) ? $request->query('sort') : null;
        $desc     = $request->boolean('desc');
        $page     = max(1, (int) $request->query('page', 1));
        $perPage  = (int) $request->query('per_page', self::DEFAULT_PER_PAGE);
        $rows     = GetCrawlTabRows::make();
        $website  = $crawl->website;
        $title    = $crawl->name ?: $website->domain;
        $canEdit  = $website->shop && $this->canEditSeo($request->user(), $website->shop);

        return Inertia::render(
            'Org/Web/Crawler/Crawl',
            [
                'breadcrumbs' => $this->getBreadcrumbs($crawl),
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
                            'label' => $website->domain,
                        ],
                    ],
                ],
                'crawl'       => fn () => $this->crawlSummary($crawl->refresh(), $canEdit),
                'tabs'        => fn () => GetCrawlTabSummary::make()->handle($crawl),
                'severities'  => CrawlIssueSeverityEnum::labels(),
                'query'       => [
                    'tab'      => $this->crawlTab->value,
                    'filter'   => $filter,
                    'search'   => $search,
                    'sort'     => $sort,
                    'desc'     => $desc,
                    'page'     => $page,
                    'per_page' => $perPage,
                ],
                'table'       => fn () => [
                    'columns' => $rows->columns($this->crawlTab, $filter),
                    ...$rows->handle($crawl, $this->crawlTab, $filter, $search, $sort, $desc, $page, $perPage),
                ],
                'routes'      => [
                    'self'    => ['name' => 'grp.websites.crawler.show', 'parameters' => [$crawl->id]],
                    'details' => ['name' => 'grp.json.crawl.url_details', 'parameters' => [$crawl->id]],
                    'export'  => ['name' => 'grp.websites.crawler.export', 'parameters' => [$crawl->id]],
                    'stop'    => $canEdit ? ['name' => 'grp.models.crawl.stop', 'parameters' => [$crawl->id], 'method' => 'patch'] : null,
                    'index'   => ['name' => 'grp.websites.crawler.index', 'parameters' => ['website' => $website->slug]],
                ],
            ]
        );
    }

    private function crawlSummary(Crawl $crawl, bool $canEdit): array
    {
        return [
            'id'             => $crawl->id,
            'name'           => $crawl->name,
            'website'        => $crawl->website->domain,
            'state'          => $crawl->state->value,
            'is_running'     => $crawl->state !== CrawlStateEnum::FINISH,
            'should_stop'    => (bool) $crawl->should_stop,
            'finish_reason'  => $crawl->finish_reason,
            'start_at'       => $crawl->start_at,
            'end_at'         => $crawl->end_at,
            'urls_processed' => $crawl->urls_processed,
            'urls_found'     => $crawl->urls_found,
            'max_pages'      => $crawl->max_pages,
            'concurrency'    => $crawl->concurrency,
            'health_score'   => $crawl->health_score === null ? null : (float) $crawl->health_score,
            'errors'         => $crawl->number_errors,
            'warnings'       => $crawl->number_warnings,
            'notices'        => $crawl->number_notices,
            'config'         => AuditWebsite::config($crawl),
            'is_full_audit'  => $crawl->is_full_audit,
            'user_name'      => $crawl->user?->contact_name,
            'can_stop'       => $canEdit,
        ];
    }

    public function getBreadcrumbs(Crawl $crawl): array
    {
        return [
            ...IndexSiteCrawls::make()->getBreadcrumbs(),
            [
                'type'   => 'simple',
                'simple' => [
                    'route' => ['name' => 'grp.websites.crawler.show', 'parameters' => [$crawl->id]],
                    'label' => $crawl->name ?: $crawl->website->domain.' #'.$crawl->id,
                ],
            ],
        ];
    }
}
