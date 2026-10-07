<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Crawl\AuditWebsite;
use App\Actions\Web\Website\UI\ShowSeoDashboard;
use App\Enums\Web\Crawl\CrawlIssueSeverityEnum;
use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Crawl;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowSiteAudit extends OrgAction
{
    use WithWebAuthorisation;

    public function handle(Website $website): array
    {
        $audits = Crawl::where('website_id', $website->id)
            ->where('type', CrawlTypeEnum::AUDIT)
            ->orderByDesc('id')
            ->limit(AuditWebsite::AUDITS_KEPT)
            ->get();

        $finishedAudits = $audits->filter(fn (Crawl $crawl) => $crawl->state === CrawlStateEnum::FINISH && $crawl->health_score !== null)->values();
        $latestAudit    = $finishedAudits->first();
        $previousAudit  = $finishedAudits->get(1);
        $runningAudit   = $audits->first(fn (Crawl $crawl) => $crawl->state !== CrawlStateEnum::FINISH);

        return [
            'running'  => $runningAudit ? [
                'state'          => $runningAudit->state->value,
                'start_at'       => $runningAudit->start_at,
                'urls_processed' => $runningAudit->urls_processed,
                'urls_found'     => $runningAudit->urls_found,
            ] : null,
            'latest'   => $latestAudit ? $this->auditSummary($latestAudit) : null,
            'previous' => $previousAudit ? $this->auditSummary($previousAudit) : null,
            'history'  => $finishedAudits->reverse()->values()->map(fn (Crawl $crawl) => [
                'end_at'       => $crawl->end_at,
                'health_score' => (float) $crawl->health_score,
                'errors'       => $crawl->number_errors,
                'warnings'     => $crawl->number_warnings,
                'notices'      => $crawl->number_notices,
            ])->all(),
            'issues'   => $latestAudit ? $this->issueSummary($latestAudit, $previousAudit) : [],
        ];
    }

    private function auditSummary(Crawl $crawl): array
    {
        return [
            'id'                => $crawl->id,
            'end_at'            => $crawl->end_at,
            'finish_reason'     => $crawl->finish_reason,
            'health_score'      => (float) $crawl->health_score,
            'pages'             => $crawl->urls_processed,
            'urls_found'        => $crawl->urls_found,
            'pages_with_errors' => $crawl->pages_with_errors,
            'errors'            => $crawl->number_errors,
            'warnings'          => $crawl->number_warnings,
            'notices'           => $crawl->number_notices,
        ];
    }

    private function issueSummary(Crawl $latestAudit, ?Crawl $previousAudit): array
    {
        $countsByType = fn (Crawl $crawl) => DB::table('crawl_issues')
            ->where('crawl_id', $crawl->id)
            ->groupBy('type')
            ->selectRaw('type, COUNT(*) as pages')
            ->pluck('pages', 'type');

        $latestCounts   = $countsByType($latestAudit);
        $previousCounts = $previousAudit ? $countsByType($previousAudit) : collect();
        $labels         = CrawlIssueTypeEnum::labels();
        $descriptions   = CrawlIssueTypeEnum::descriptions();

        return collect(CrawlIssueTypeEnum::cases())
            ->filter(fn (CrawlIssueTypeEnum $type) => $latestCounts->has($type->value) || $previousCounts->has($type->value))
            ->map(fn (CrawlIssueTypeEnum $type) => [
                'type'           => $type->value,
                'label'          => $labels[$type->value],
                'description'    => $descriptions[$type->value],
                'severity'       => $type->severity()->value,
                'severity_rank'  => $type->severity()->rank(),
                'pages'          => (int) $latestCounts->get($type->value, 0),
                'previous_pages' => $previousAudit ? (int) $previousCounts->get($type->value, 0) : null,
            ])
            ->sortBy([['severity_rank', 'asc'], ['pages', 'desc']])
            ->values()
            ->all();
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Website
    {
        abort_unless($shop->website, 404);

        $this->initialisationFromShop($shop, $request);

        return $shop->website;
    }

    public function htmlResponse(Website $website, ActionRequest $request): Response
    {
        $title = __('Site audit');

        return Inertia::render(
            'Org/Web/SiteAudit',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-clipboard-check'],
                        'title' => $title,
                    ],
                    'title' => $title,
                ],
                'website'     => [
                    'domain' => $website->domain,
                ],
                'canRunAudit' => $this->canEdit,
                'runRoute'    => [
                    'name'       => 'grp.models.website.site_audit.store',
                    'parameters' => [$website->id],
                    'method'     => 'post',
                ],
                'severities'  => CrawlIssueSeverityEnum::labels(),
                'audit'       => fn () => $this->handle($website),
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        $shopParameters = Arr::only($routeParameters, ['organisation', 'shop']);

        return array_merge(
            ShowSeoDashboard::make()->getBreadcrumbs($shopParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.seo.site_audit.show',
                            'parameters' => $shopParameters,
                        ],
                        'label' => __('Site audit'),
                    ],
                ],
            ]
        );
    }
}
