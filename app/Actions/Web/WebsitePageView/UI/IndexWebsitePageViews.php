<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsitePageView\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Website\PruneWebsitePageViews;
use App\Actions\Web\Website\UI\ShowSeoDashboard;
use App\Http\Resources\Web\WebsitePageViewResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use App\Models\Web\WebsitePageView;
use App\Models\Web\WebsiteVisitor;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexWebsitePageViews extends OrgAction
{
    use WithWebAuthorisation;

    private Website $website;

    private ?WebsiteVisitor $websiteVisitor = null;

    public function handle(Website $website, ?WebsiteVisitor $websiteVisitor = null, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('website_page_views.page_path', $value)
                    ->orWhereStartWith('webpages.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(WebsitePageView::class)
            ->where('website_page_views.website_id', $website->id)
            ->when($websiteVisitor, fn ($query) => $query->where('website_page_views.website_visitor_id', $websiteVisitor->id))
            ->leftJoin('webpages', 'website_page_views.webpage_id', '=', 'webpages.id')
            ->leftJoin('website_visitors', 'website_page_views.website_visitor_id', '=', 'website_visitors.id');

        return $queryBuilder
            ->defaultSort(AllowedSort::field('-viewed_at', 'website_page_views.id'))
            ->select([
                'website_page_views.id',
                'website_page_views.website_visitor_id',
                'website_page_views.webpage_id',
                'website_page_views.page_path',
                'website_page_views.page_type',
                'website_page_views.duration_seconds',
                'website_page_views.created_at',
                'webpages.code as webpage_code',
                'webpages.slug as webpage_slug',
                'website_visitors.session_id',
                'website_visitors.device_type',
                'website_visitors.country_code',
                'website_visitors.city',
            ])
            ->allowedSorts([
                AllowedSort::field('viewed_at', 'website_page_views.id'),
                AllowedSort::field('duration_seconds', 'website_page_views.duration_seconds'),
                AllowedSort::field('page_path', 'website_page_views.page_path'),
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(bool $forOneVisitor = false, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($forOneVisitor, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('page view'), __('page views')])
                ->withEmptyState([
                    'title'       => __('No page views in the last :days days', ['days' => PruneWebsitePageViews::RETENTION_DAYS]),
                    'description' => __('Page views appear here a few seconds after someone opens a page on the website.'),
                ])
                ->column(key: 'viewed_at', label: __('Viewed at'), canBeHidden: false, sortable: true)
                ->column(key: 'page_path', label: __('Page'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'page_type', label: __('Type'))
                ->column(key: 'duration_seconds', label: __('Time on page'), sortable: true, align: 'right');

            if (!$forOneVisitor) {
                $table
                    ->column(key: 'visitor', label: __('Visitor'), canBeHidden: false)
                    ->column(key: 'device_type', label: __('Device'))
                    ->column(key: 'country_code', label: __('Country'));
            }

            $table->defaultSort('-viewed_at');
        };
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        abort_unless($shop->website, 404);

        $this->website = $shop->website;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($this->website);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inVisitor(Organisation $organisation, Shop $shop, WebsiteVisitor $websiteVisitor, ActionRequest $request): LengthAwarePaginator
    {
        abort_unless($shop->website && $websiteVisitor->website_id === $shop->website->id, 404);

        $this->website        = $shop->website;
        $this->websiteVisitor = $websiteVisitor;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($this->website, $websiteVisitor);
    }

    public function htmlResponse(LengthAwarePaginator $pageViews, ActionRequest $request): Response
    {
        $title    = __('Page views');
        $pageHead = [
            'title' => $title,
        ];

        if ($this->websiteVisitor) {
            $visitorLabel = substr($this->websiteVisitor->session_id, 0, 8);
            $title        = __('Page views of visitor :visitor', ['visitor' => $visitorLabel]);
            $pageHead     = [
                'title' => $title,
                'meta'  => array_values(array_filter([
                    [
                        'key'   => 'device',
                        'label' => ucfirst($this->websiteVisitor->device_type),
                    ],
                    $this->websiteVisitor->country_code ? [
                        'key'   => 'location',
                        'label' => implode(', ', array_filter([$this->websiteVisitor->city, $this->websiteVisitor->country_code])),
                    ] : null,
                    [
                        'key'   => 'first_seen',
                        'label' => __('First seen :date', ['date' => $this->websiteVisitor->first_seen_at->diffForHumans()]),
                    ],
                ])),
            ];
        }

        return Inertia::render(
            'Org/Web/WebsitePageViews',
            [
                'breadcrumbs'   => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'         => $title,
                'pageHead'      => $pageHead,
                'retentionDays' => PruneWebsitePageViews::RETENTION_DAYS,
                'data'          => WebsitePageViewResource::collection($pageViews),
            ]
        )->table($this->tableStructure(forOneVisitor: $this->websiteVisitor !== null));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $shopParameters = Arr::only($routeParameters, ['organisation', 'shop']);

        $breadcrumbs = array_merge(
            ShowSeoDashboard::make()->getBreadcrumbs($shopParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.seo.page_views.index',
                            'parameters' => $shopParameters
                        ],
                        'label' => __('Page views'),
                    ]
                ]
            ]
        );

        if ($routeName === 'grp.org.shops.show.seo.page_views.visitor' && $this->websiteVisitor) {
            $breadcrumbs[] = [
                'type'   => 'simple',
                'simple' => [
                    'route' => [
                        'name'       => 'grp.org.shops.show.seo.page_views.visitor',
                        'parameters' => $routeParameters
                    ],
                    'label' => substr($this->websiteVisitor->session_id, 0, 8),
                ]
            ];
        }

        return $breadcrumbs;
    }
}
