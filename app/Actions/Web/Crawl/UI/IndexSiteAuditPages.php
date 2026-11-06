<?php

/*
 * Created: Fri, 06 Nov 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Crawl\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Http\Resources\Web\SiteAuditPageResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Crawl;
use App\Models\Web\CrawlPage;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * Every page the latest audit read, with what it found on each: the list the SEO team filtered in
 * Screaming Frog, read in Aiku.
 */
class IndexSiteAuditPages extends OrgAction
{
    use WithWebAuthorisation;

    private ?Crawl $audit = null;

    public function handle(Crawl $audit, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $search = '%'.addcslashes(strip_tags($value), '%_\\').'%';
            $query->where(fn ($query) => $query->where('crawl_pages.url', 'ilike', $search)->orWhere('crawl_pages.title', 'ilike', $search));
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(CrawlPage::class)
            ->leftJoin('webpages', 'webpages.id', '=', 'crawl_pages.webpage_id')
            ->where('crawl_pages.crawl_id', $audit->id)
            ->defaultSort('crawl_pages.url')
            ->select([
                'crawl_pages.id',
                'crawl_pages.url',
                'crawl_pages.status_code',
                'crawl_pages.redirect_to',
                'crawl_pages.title',
                'crawl_pages.meta_description',
                'crawl_pages.h1',
                'crawl_pages.canonical',
                'crawl_pages.is_indexable',
                'crawl_pages.is_in_sitemap',
                'crawl_pages.link_depth',
                'crawl_pages.inlinks',
                'crawl_pages.response_ms',
                'crawl_pages.word_count',
                'webpages.slug as webpage_slug',
                'webpages.code as webpage_code',
                DB::raw('(SELECT COUNT(*) FROM crawl_issues WHERE crawl_issues.crawl_page_id = crawl_pages.id) AS issues'),
            ])
            ->allowedSorts([
                AllowedSort::field('url', 'crawl_pages.url'),
                AllowedSort::field('status_code', 'crawl_pages.status_code'),
                AllowedSort::field('link_depth', 'crawl_pages.link_depth'),
                AllowedSort::field('inlinks', 'crawl_pages.inlinks'),
                AllowedSort::field('response_ms', 'crawl_pages.response_ms'),
                AllowedSort::field('word_count', 'crawl_pages.word_count'),
                'issues',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
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
                ->withEmptyState(['title' => __('The latest audit read no page')])
                ->column(key: 'url', label: __('URL'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'status_code', label: __('Status'), sortable: true, align: 'right')
                ->column(key: 'title', label: __('Title'))
                ->column(key: 'meta_description', label: __('Meta description'))
                ->column(key: 'h1', label: __('H1'))
                ->column(key: 'is_indexable', label: __('Indexable'), tooltip: __('No noindex, and the canonical is the page itself'))
                ->column(key: 'link_depth', label: __('Clicks'), tooltip: __('Clicks from the home page. Empty when only the sitemap lists the page'), sortable: true, align: 'right')
                ->column(key: 'inlinks', label: __('Links in'), tooltip: __('Links from other crawled pages of this website'), sortable: true, align: 'right')
                ->column(key: 'response_ms', label: __('Response'), tooltip: __('Milliseconds until the page arrived'), sortable: true, align: 'right')
                ->column(key: 'word_count', label: __('Words'), sortable: true, align: 'right')
                ->column(key: 'issues', label: __('Issues'), sortable: true, align: 'right')
                ->defaultSort('url');
        };
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        abort_unless($shop->website, 404);

        $this->initialisationFromShop($shop, $request);

        $this->audit = IndexSiteAuditIssuePages::make()->latestAudit($shop->website);
        abort_unless($this->audit, 404);

        return $this->handle($this->audit);
    }

    public function htmlResponse(LengthAwarePaginator $pages, ActionRequest $request): Response
    {
        $title = __('Pages read');

        return Inertia::render(
            'Org/Web/SiteAuditPages',
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
                'websiteSlug' => $this->audit->website->slug,
                'data'        => SiteAuditPageResource::collection($pages),
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
                            'name'       => 'grp.org.shops.show.seo.site_audit.pages',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Pages read'),
                    ],
                ],
            ]
        );
    }
}
