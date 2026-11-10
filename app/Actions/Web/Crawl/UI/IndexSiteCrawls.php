<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Websites\WebsitesDashboard;
use App\Actions\Web\Crawl\AuditWebsite;
use App\Enums\Web\Crawl\CrawlScheduleFrequencyEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Http\Resources\Web\SiteCrawlsResource;
use App\InertiaTable\InertiaTable;
use App\Models\SysAdmin\User;
use App\Models\Web\Crawl;
use App\Models\Web\CrawlSchedule;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * Every crawl of the group's websites made by the site crawler or the scheduled site audit, newest
 * first, with the websites a new crawl can be started on.
 */
class IndexSiteCrawls extends OrgAction
{
    use WithSiteCrawlerAuthorisation;

    private ?Website $website = null;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->website) {
            return $this->canViewCrawlsOf($request->user(), $this->website);
        }

        return $this->canViewSiteCrawler($request->user());
    }

    public function handle(?Website $website = null, ?array $websiteIds = null, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $search = '%'.addcslashes(strip_tags($value), '%_\\').'%';
            $query->where(fn ($query) => $query->where('crawls.name', 'ilike', $search)->orWhere('websites.domain', 'ilike', $search));
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(Crawl::class)
            ->join('websites', 'websites.id', '=', 'crawls.website_id')
            ->leftJoin('users', 'users.id', '=', 'crawls.user_id')
            ->where('crawls.type', CrawlTypeEnum::AUDIT)
            ->when($website, fn ($query) => $query->where('crawls.website_id', $website->id))
            ->when($websiteIds !== null, fn ($query) => $query->whereIn('crawls.website_id', $websiteIds))
            ->defaultSort(AllowedSort::field('-id', 'crawls.id'))
            ->select([
                'crawls.id',
                'crawls.name',
                'crawls.state',
                'crawls.finish_reason',
                'crawls.trigger',
                'crawls.start_at',
                'crawls.end_at',
                'crawls.urls_processed',
                'crawls.urls_found',
                'crawls.health_score',
                'crawls.number_errors',
                'crawls.number_warnings',
                'crawls.number_notices',
                'crawls.is_full_audit',
                'websites.domain as website_domain',
                'websites.slug as website_slug',
                'users.contact_name as user_name',
            ])
            ->allowedSorts([
                AllowedSort::field('id', 'crawls.id'),
                AllowedSort::field('start_at', 'crawls.start_at'),
                AllowedSort::field('urls_processed', 'crawls.urls_processed'),
                AllowedSort::field('health_score', 'crawls.health_score'),
                AllowedSort::field('website_domain', 'websites.domain'),
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
                ->withLabelRecord([__('crawl'), __('crawls')])
                ->withEmptyState([
                    'title'       => __('No crawls yet'),
                    'description' => __('Start one with New crawl to see every URL, link and issue of a website.'),
                ])
                ->column(key: 'website_domain', label: __('Crawl'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'state', label: __('Status'))
                ->column(key: 'start_at', label: __('Started'), sortable: true)
                ->column(key: 'urls_processed', label: __('URLs'), sortable: true, align: 'right')
                ->column(key: 'health_score', label: __('Health'), sortable: true, align: 'right')
                ->column(key: 'issues', label: __('Errors / warnings / notices'), align: 'right')
                ->column(key: 'user_name', label: __('Started by'))
                ->defaultSort('-id');
        };
    }

    public function asController(ActionRequest $request): ?Website
    {
        $websiteSlug   = $request->query('website');
        $this->website = is_string($websiteSlug) ? Website::where('slug', $websiteSlug)->firstOrFail() : null;

        $this->initialisationFromGroup(group(), $request);

        return $this->website;
    }

    public function htmlResponse(?Website $website, ActionRequest $request): Response
    {
        $title = __('Site crawler');

        return Inertia::render(
            'Org/Web/Crawler/Crawls',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => [
                        'icon'  => ['fal', 'fa-spider'],
                        'title' => $title,
                    ],
                    'meta'  => $website ? [
                        [
                            'key'   => 'website',
                            'label' => $website->domain,
                        ],
                    ] : [],
                ],
                'website'     => $website ? ['id' => $website->id, 'domain' => $website->domain, 'slug' => $website->slug] : null,
                'newCrawl'    => [
                    'websites' => $this->startableWebsites($request->user()),
                    'defaults' => [
                        ...AuditWebsite::DEFAULT_CONFIG,
                        'max_pages'   => null,
                        'concurrency' => AuditWebsite::DEFAULT_CONCURRENCY,
                    ],
                    'maxConcurrency' => AuditWebsite::CONCURRENT_FETCH_BUDGET,
                    'maxListUrls'    => AuditWebsite::LIST_MAX_URLS,
                    'maxCustomItems' => AuditWebsite::CUSTOM_ITEMS_MAX,
                    'route'    => [
                        'name'   => 'grp.models.website.crawl.store',
                        'method' => 'post',
                    ],
                ],
                'schedules'   => $this->schedules($request->user(), $website),
                'scheduleSettings' => [
                    'frequencies' => CrawlScheduleFrequencyEnum::labels(),
                    'saveRoute'   => ['name' => 'grp.models.website.crawl_schedule.save', 'method' => 'post'],
                    'deleteRoute' => ['name' => 'grp.models.website.crawl_schedule.delete', 'method' => 'delete'],
                ],
                'data'        => SiteCrawlsResource::collection($this->handle($website, $this->viewableWebsiteIds($request->user()))),
            ]
        )->table($this->tableStructure());
    }

    /**
     * @return array<int, array{id: int, domain: string, slug: string}>
     */
    private function startableWebsites(User $user): array
    {
        return Website::where('state', WebsiteStateEnum::LIVE)
            ->with('shop')
            ->orderBy('domain')
            ->get()
            ->filter(fn (Website $website) => $website->shop && $this->canEditSeo($user, $website->shop))
            ->map(fn (Website $website) => [
                'id'     => $website->id,
                'domain' => $website->domain,
                'slug'   => $website->slug,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function schedules(User $user, ?Website $website): array
    {
        $websiteIds = $this->viewableWebsiteIds($user);

        return CrawlSchedule::with(['website.shop', 'user'])
            ->when($website, fn ($query) => $query->where('website_id', $website->id))
            ->when($websiteIds !== null, fn ($query) => $query->whereIn('website_id', $websiteIds))
            ->get()
            ->sortBy(fn (CrawlSchedule $schedule) => $schedule->website->domain)
            ->map(fn (CrawlSchedule $schedule) => [
                'website_id'   => $schedule->website_id,
                'domain'       => $schedule->website->domain,
                'frequency'    => $schedule->frequency->value,
                'weekday'      => $schedule->weekday,
                'day_of_month' => $schedule->day_of_month,
                'hour'         => $schedule->hour,
                'is_active'    => $schedule->is_active,
                'next_run_at'  => $schedule->next_run_at,
                'last_run_at'  => $schedule->last_run_at,
                'user_name'    => $schedule->user?->contact_name,
                'can_edit'     => $schedule->website->shop && $this->canEditSeo($user, $schedule->website->shop),
            ])
            ->values()
            ->all();
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...WebsitesDashboard::make()->getBreadcrumbs(),
            [
                'type'   => 'simple',
                'simple' => [
                    'route' => ['name' => 'grp.websites.crawler.index'],
                    'label' => __('Site crawler'),
                ],
            ],
        ];
    }
}
