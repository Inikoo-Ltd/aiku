<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Http\Resources\Web\SiteAuditIssuePageResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Crawl;
use App\Models\Web\CrawlIssue;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexSiteAuditIssuePages extends OrgAction
{
    use WithWebAuthorisation;

    private CrawlIssueTypeEnum $issueType;

    private ?Crawl $audit = null;

    public function handle(Crawl $audit, CrawlIssueTypeEnum $issueType, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where('crawl_pages.url', 'ilike', '%'.addcslashes(strip_tags($value), '%_\\').'%');
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(CrawlIssue::class)
            ->join('crawl_pages', 'crawl_pages.id', '=', 'crawl_issues.crawl_page_id')
            ->leftJoin('webpages', 'webpages.id', '=', 'crawl_pages.webpage_id')
            ->where('crawl_issues.crawl_id', $audit->id)
            ->where('crawl_issues.type', $issueType->value)
            ->defaultSort(AllowedSort::field('-inlinks', 'crawl_pages.inlinks'))
            ->select([
                'crawl_issues.id',
                'crawl_issues.details',
                'crawl_pages.url',
                'crawl_pages.status_code',
                'crawl_pages.inlinks',
                'crawl_pages.response_ms',
                'crawl_pages.title',
                'crawl_pages.is_in_sitemap',
                'webpages.slug as webpage_slug',
                'webpages.code as webpage_code',
            ])
            ->allowedSorts([
                AllowedSort::field('url', 'crawl_pages.url'),
                AllowedSort::field('status_code', 'crawl_pages.status_code'),
                AllowedSort::field('inlinks', 'crawl_pages.inlinks'),
                AllowedSort::field('response_ms', 'crawl_pages.response_ms'),
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('page'), __('pages')])
                ->withEmptyState([
                    'title' => __('No page has this issue in the latest audit'),
                ])
                ->column(key: 'url', label: __('URL'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'webpage_code', label: __('Webpage'))
                ->column(key: 'status_code', label: __('Status'), sortable: true, align: 'right')
                ->column(key: 'inlinks', label: __('Internal links in'), tooltip: __('Links from other crawled pages of this website'), sortable: true, align: 'right')
                ->column(key: 'details', label: __('Details'))
                ->defaultSort('-inlinks');
        };
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Shop $shop, string $issueType, ActionRequest $request): LengthAwarePaginator
    {
        abort_unless($shop->website, 404);

        $this->issueType = CrawlIssueTypeEnum::tryFrom($issueType) ?? abort(404);
        $this->initialisationFromShop($shop, $request);

        $this->audit = $this->latestAudit($shop->website);
        abort_unless($this->audit, 404);

        return $this->handle($this->audit, $this->issueType);
    }

    private function latestAudit(Website $website): ?Crawl
    {
        return Crawl::where('website_id', $website->id)
            ->where('type', CrawlTypeEnum::AUDIT)
            ->where('state', CrawlStateEnum::FINISH)
            ->whereNotNull('health_score')
            ->latest('id')
            ->first();
    }

    public function htmlResponse(LengthAwarePaginator $issuePages, ActionRequest $request): Response
    {
        $title = CrawlIssueTypeEnum::labels()[$this->issueType->value];

        return Inertia::render(
            'Org/Web/SiteAuditIssuePages',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-clipboard-check'],
                        'title' => __('Site audit'),
                    ],
                    'title' => $title,
                    'meta'  => [
                        [
                            'key'   => 'audited_at',
                            'label' => __('Audit finished :date', ['date' => $this->audit->end_at?->diffForHumans()]),
                        ],
                    ],
                ],
                'issueType'   => $this->issueType->value,
                'websiteSlug' => $this->audit->website->slug,
                'severity'    => $this->issueType->severity()->value,
                'description' => CrawlIssueTypeEnum::descriptions()[$this->issueType->value],
                'data'        => SiteAuditIssuePageResource::collection($issuePages),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowSiteAudit::make()->getBreadcrumbs(Arr::only($routeParameters, ['organisation', 'shop'])),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.seo.site_audit.issue',
                            'parameters' => $routeParameters,
                        ],
                        'label' => CrawlIssueTypeEnum::labels()[$this->issueType->value],
                    ],
                ],
            ]
        );
    }
}
