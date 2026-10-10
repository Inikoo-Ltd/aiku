<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsiteVisitor\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Website\PruneWebsiteVisitors;
use App\Actions\Web\Website\UI\ShowSeoDashboard;
use App\Actions\Web\Website\UI\ShowWebsiteAnalyticsDashboard;
use App\Actions\Web\Website\WithWebsiteAnalyticsSubNavigation;
use App\Enums\UI\Web\WebpageTabsEnum;
use App\Enums\Web\WebsiteVisitor\WebsiteVisitorChannelEnum;
use App\Http\Resources\Web\WebsiteVisitorResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use App\Models\Web\WebsiteVisitor;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWebsiteVisitors extends OrgAction
{
    use WithWebAuthorisation;
    use WithWebsiteAnalyticsSubNavigation;

    private Website $website;

    private ?Webpage $webpage = null;

    private ?array $elementGroups = null;

    private function elementGroups(Website $website, ?Webpage $webpage = null): array
    {
        if ($this->elementGroups !== null) {
            return $this->elementGroups;
        }

        $visitorsOfWebsite = fn () => $this->scopeToWebpage(WebsiteVisitor::where('website_id', $website->id), $webpage)->toBase();

        $bounceCounts = $visitorsOfWebsite()
            ->selectRaw('count(*) filter (where is_bounce) as bounced, count(*) filter (where not is_bounce) as engaged')
            ->first();

        $channelCounts = $visitorsOfWebsite()
            ->whereNotNull('traffic_source_type')
            ->groupBy('traffic_source_type')
            ->selectRaw('traffic_source_type, count(*) as visitors')
            ->pluck('visitors', 'traffic_source_type')
            ->groupBy(fn ($visitors, $type) => WebsiteVisitorChannelEnum::fromType($type)?->value, preserveKeys: true)
            ->map(fn ($visitorsPerType) => $visitorsPerType->sum());

        return $this->elementGroups = [
            'bounce'  => [
                'label'    => __('Bounce'),
                'elements' => [
                    'bounced' => [__('Bounced'), (int) $bounceCounts->bounced],
                    'engaged' => [__('Engaged'), (int) $bounceCounts->engaged],
                ],
                'engine'   => function ($query, array $elements) {
                    $query->where('website_visitors.is_bounce', in_array('bounced', $elements));
                },
            ],
            'channel' => [
                'label'    => __('Channel'),
                'elements' => collect(WebsiteVisitorChannelEnum::cases())
                    ->mapWithKeys(fn (WebsiteVisitorChannelEnum $channel) => [
                        $channel->value => [WebsiteVisitorChannelEnum::labels()[$channel->value], (int) $channelCounts->get($channel->value, 0)],
                    ])
                    ->all(),
                'engine'   => function ($query, array $elements) {
                    $query->whereIn(
                        'website_visitors.traffic_source_type',
                        collect($elements)->flatMap(fn (string $channel) => WebsiteVisitorChannelEnum::from($channel)->types())->all()
                    );
                },
            ],
        ];
    }

    private function scopeToWebpage(Builder|QueryBuilder $query, ?Webpage $webpage): Builder|QueryBuilder
    {
        if (!$webpage) {
            return $query;
        }

        return $query->whereExists(function ($query) use ($webpage) {
            $query->select(DB::raw(1))
                ->from('website_page_views')
                ->whereColumn('website_page_views.website_visitor_id', 'website_visitors.id')
                ->where('website_page_views.webpage_id', $webpage->id);
        });
    }

    public function handle(Website $parent, $prefix = null, ?Webpage $webpage = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('website_visitors.session_id', $value)
                    ->orWhereStartWith('website_visitors.ip_hash', $value)
                    ->orWhereStartWith('website_visitors.country_code', $value)
                    ->orWhereStartWith('website_visitors.city', $value)
                    ->orWhereStartWith('web_users.username', $value)
                    ->orWhereAnyWordStartWith('web_users.contact_name', $value)
                    ->orWhereAnyWordStartWith('customers.contact_name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(WebsiteVisitor::class);
        $queryBuilder->where('website_visitors.website_id', $parent->id);

        $this->scopeToWebpage($queryBuilder, $webpage);

        foreach ($this->elementGroups($parent, $webpage) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $queryBuilder
            ->leftJoin('web_users', 'web_users.id', '=', 'website_visitors.web_user_id')
            ->leftJoin('customers', 'customers.id', '=', 'web_users.customer_id')
            ->defaultSort('-first_seen_at')
            ->select([
                'website_visitors.*',
                'web_users.slug as web_user_slug',
                'customers.slug as customer_slug',
            ])
            ->selectRaw("COALESCE(NULLIF(web_users.contact_name, ''), NULLIF(customers.contact_name, ''), web_users.username) as web_user_contact_name")
            ->allowedSorts(['first_seen_at', 'last_seen_at', 'page_views', 'duration_seconds', 'device_type', 'country_code', 'traffic_source_type'])
            ->allowedFilters([$globalSearch, 'device_type', 'browser', 'os', 'country_code', 'is_bounce', 'is_new_visitor'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Website $website, ?Webpage $webpage = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($website, $webpage, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->elementGroups($website, $webpage) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->withGlobalSearch()
                ->column(key: 'session_id', label: __('Session ID'), canBeHidden: false, searchable: true)
                ->column(key: 'web_user', label: __('Web user'), tooltip: __('The account the visitor was logged in to when the visit started'), canBeHidden: false, tooltipIcon: true)
                ->column(key: 'device_type', label: __('Device'), canBeHidden: false, sortable: true)
                ->column(key: 'browser', label: __('Browser'), canBeHidden: false)
                ->column(key: 'os', label: __('OS'), canBeHidden: false)
                ->column(key: 'location', label: __('Location'), canBeHidden: false)
                ->column(key: 'traffic_source_type', label: __('Source'), tooltip: __('Where the visitor came from when the visit started, read from the landing page link and the referring website'), canBeHidden: false, sortable: true, tooltipIcon: true)
                ->column(key: 'page_views', label: __('Page Views'), canBeHidden: false, sortable: true)
                ->column(key: 'duration_seconds', label: __('Duration'), canBeHidden: false, sortable: true)
                ->column(key: 'bounce', label: __('Bounce'), tooltip: __('Left after viewing one page'), canBeHidden: false, tooltipIcon: true)
                ->column(key: 'first_seen_at', label: __('First Seen'), canBeHidden: false, sortable: true)
                ->column(key: 'last_seen_at', label: __('Last Seen'), canBeHidden: false, sortable: true)
                ->defaultSort('-first_seen_at');
        };
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->website = $website;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($website);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inSeoWebpage(Organisation $organisation, Shop $shop, Webpage $webpage, ActionRequest $request): LengthAwarePaginator
    {
        abort_unless($shop->website && $webpage->website_id === $shop->website->id, 404);

        $this->website = $shop->website;
        $this->webpage = $webpage;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($this->website, webpage: $webpage);
    }

    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->website = $website;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($website);
    }

    public function htmlResponse(LengthAwarePaginator $visitors, ActionRequest $request): Response
    {
        $title    = __('Website Visitors');
        $pageHead = [
            'title' => $title,
        ];

        if ($this->webpage) {
            $title    = __('Visitors of :webpage', ['webpage' => $this->webpage->code]);
            $pageHead = [
                'title' => $title,
                'meta'  => [
                    [
                        'key'      => 'webpage',
                        'label'    => __('Open webpage traffic sources'),
                        'leftIcon' => [
                            'icon'    => 'fal fa-browser',
                            'tooltip' => __('Webpage'),
                        ],
                        'route'    => [
                            'name'       => 'grp.org.shops.show.web.webpages.show',
                            'parameters' => [
                                'organisation' => $this->organisation->slug,
                                'shop'         => $this->shop->slug,
                                'website'      => $this->website->slug,
                                'webpage'      => $this->webpage->slug,
                                'tab'          => WebpageTabsEnum::TRAFFIC_SOURCES->value,
                            ],
                        ],
                    ],
                ],
            ];
        }

        if (!str_starts_with($request->route()->getName(), 'grp.org.shops.show.seo.')) {
            $pageHead['subNavigation'] = $this->getWebsiteAnalyticsNavigation($this->website);
        }

        return Inertia::render(
            'Org/Web/WebsiteVisitors',
            [
                'breadcrumbs'   => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'         => $title,
                'pageHead'      => $pageHead,
                'retentionDays' => PruneWebsiteVisitors::RETENTION_DAYS,
                'data'          => WebsiteVisitorResource::collection($visitors),
            ]
        )->table($this->tableStructure($this->website, $this->webpage));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        if ($routeName == 'grp.org.shops.show.seo.visitors.webpage') {
            return array_merge(
                $this->getBreadcrumbs('grp.org.shops.show.seo.visitors.index', Arr::only($routeParameters, ['organisation', 'shop'])),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.seo.visitors.webpage',
                                'parameters' => $routeParameters
                            ],
                            'label' => $this->webpage?->code,
                        ]
                    ]
                ]
            );
        }

        if ($routeName == 'grp.org.shops.show.seo.visitors.index') {
            return array_merge(
                ShowSeoDashboard::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.seo.visitors.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Visitors'),
                        ]
                    ]
                ]
            );
        }

        if ($routeName == 'grp.org.shops.show.web.analytics.visitors.index') {
            return array_merge(
                ShowWebsiteAnalyticsDashboard::make()->getBreadcrumbs('grp.org.shops.show.web.analytics.dashboard', $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.web.analytics.visitors.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Website Visitors'),
                        ]
                    ]
                ]
            );
        } else {
            return array_merge(
                ShowWebsiteAnalyticsDashboard::make()->getBreadcrumbs('grp.org.fulfilments.show.web.analytics.dashboard', $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.web.analytics.visitors.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Website Visitors'),
                        ]
                    ]
                ]
            );
        }
    }
}
