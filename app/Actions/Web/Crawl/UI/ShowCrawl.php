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
use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTabEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Models\Web\Crawl;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
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
        $filter   = $request->query('check');
        $filter   = is_string($filter) && $filter !== '' ? $filter : null;
        $segment  = is_string($request->query('segment')) && array_key_exists($request->query('segment'), GetCrawlTabRows::segments()) ? $request->query('segment') : null;
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
                'segments'    => GetCrawlTabRows::segments(),
                'query'       => [
                    'tab'     => $this->crawlTab->value,
                    'filter'  => $filter,
                    'segment' => $segment,
                ],
                'columns'     => $rows->columns($this->crawlTab, $filter, $crawl),
                'data'        => JsonResource::collection($rows->handle($crawl, $this->crawlTab, $filter, $segment)),
                'routes'      => [
                    'self'    => ['name' => 'grp.websites.crawler.show', 'parameters' => ['crawl' => $crawl->id]],
                    'details' => ['name' => 'grp.json.crawl.url_details', 'parameters' => ['crawl' => $crawl->id]],
                    'export'  => ['name' => 'grp.websites.crawler.export', 'parameters' => ['crawl' => $crawl->id]],
                    'report'  => ['name' => 'grp.websites.crawler.report', 'parameters' => ['crawl' => $crawl->id]],
                    'compare'   => ['name' => 'grp.websites.crawler.compare', 'parameters' => ['crawl' => $crawl->id]],
                    'structure' => ['name' => 'grp.websites.crawler.structure', 'parameters' => ['crawl' => $crawl->id]],
                    'stop'    => $canEdit ? ['name' => 'grp.models.crawl.stop', 'parameters' => [$crawl->id], 'method' => 'patch'] : null,
                    'index'   => ['name' => 'grp.websites.crawler.index', 'parameters' => ['website' => $website->slug]],
                ],
            ]
        )->table($rows->tableStructure($crawl, $this->crawlTab, $filter, $this->emptyText($crawl, $filter)));
    }

    private function emptyText(Crawl $crawl, ?string $filter): string
    {
        if ($crawl->state !== CrawlStateEnum::FINISH && $filter !== null && CrawlIssueTypeEnum::tryFrom($filter) !== null) {
            return __('Issues are checked when the crawl finishes.');
        }

        return $filter ? __('No URL has this. Nothing to fix here.') : __('The crawl found no URL for this tab.');
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
            'config'         => [...Arr::except(AuditWebsite::config($crawl), ['urls']), 'urls_count' => count(AuditWebsite::config($crawl)['urls'])],
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
                    'route' => ['name' => 'grp.websites.crawler.show', 'parameters' => ['crawl' => $crawl->id]],
                    'label' => $crawl->name ?: $crawl->website->domain.' #'.$crawl->id,
                ],
            ],
        ];
    }
}
