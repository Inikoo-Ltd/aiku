<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Jun 2023 23:52:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Websites;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\Web\Crawl\UI\IndexCrawls;
use App\Actions\Web\Website\UI\GetWebsitesEdgeTraffic;
use App\Actions\Web\Website\UI\GetWebsitesVarnishMetrics;
use App\Actions\Web\Website\UI\IndexWebsitesEdgeTraffic;
use App\Actions\Web\Website\UI\IndexWebsiteVisitorCountries;
use App\Actions\Web\Website\UI\IndexWebsitesEdgeDatacenters;
use App\Enums\Web\Website\WebsiteDashboardTabsEnum;
use App\Http\Resources\Web\CrawlsResource;
use App\Http\Resources\Web\WebsitesEdgeTrafficResource;
use App\Http\Resources\Web\WebsiteVisitorCountriesResource;
use App\Http\Resources\Web\WebsitesEdgeDatacentersResource;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class WebsitesDashboard extends OrgAction
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasGroupAccess();
    }

    public function asController(ActionRequest $request): Group
    {
        $this->initialisationFromGroup(group(), $request)->withTab(WebsiteDashboardTabsEnum::values());

        return group();
    }

    public function htmlResponse(Group $group): Response
    {
        $user    = request()->user();
        $query   = fn (string $key) => is_string(request()->query($key)) ? request()->query($key) : null;
        $website = $query('website');
        $days    = $query('days') === null ? null : (int) $query('days');
        $range   = array_key_exists((string) $query('varnish_range'), GetWebsitesVarnishMetrics::RANGES) ? $query('varnish_range') : null;
        [$website, $days, $range] = $this->rememberedFilters($user, $website, $days, $range);
        $day     = $query('day');
        $country = $query('country');

        return Inertia::render(
            'Org/Web/WebsitesDashboard',
            [
                'breadcrumbs'  => $this->getBreadcrumbs(),
                'title'        => __('WebOps'),
                'pageHead'     => [
                    'title'     => __('WebOps'),
                ],
                'tabs'         => [
                    'current'    => $this->tab,
                    'navigation' => WebsiteDashboardTabsEnum::navigation()
                ],

                WebsiteDashboardTabsEnum::SHOWCASE->value => $this->tab == WebsiteDashboardTabsEnum::SHOWCASE->value ?
                    fn () => GetWebsitesEdgeTraffic::run($group, $user, $website, $days)
                    : Inertia::optional(fn () => GetWebsitesEdgeTraffic::run($group, $user, $website, $days)),

                WebsiteDashboardTabsEnum::EDGE_WEBSITES->value => $this->tab == WebsiteDashboardTabsEnum::EDGE_WEBSITES->value ?
                    fn () => WebsitesEdgeTrafficResource::collection(IndexWebsitesEdgeTraffic::make()->handle($group, $user, $website, $days, $day, WebsiteDashboardTabsEnum::EDGE_WEBSITES->value))
                    : Inertia::optional(fn () => WebsitesEdgeTrafficResource::collection(IndexWebsitesEdgeTraffic::make()->handle($group, $user, $website, $days, $day, WebsiteDashboardTabsEnum::EDGE_WEBSITES->value))),

                WebsiteDashboardTabsEnum::EDGE_DATACENTERS->value => $this->tab == WebsiteDashboardTabsEnum::EDGE_DATACENTERS->value ?
                    fn () => WebsitesEdgeDatacentersResource::collection(IndexWebsitesEdgeDatacenters::make()->handle($group, $user, $website, $days, $day, WebsiteDashboardTabsEnum::EDGE_DATACENTERS->value))
                    : Inertia::optional(fn () => WebsitesEdgeDatacentersResource::collection(IndexWebsitesEdgeDatacenters::make()->handle($group, $user, $website, $days, $day, WebsiteDashboardTabsEnum::EDGE_DATACENTERS->value))),

                WebsiteDashboardTabsEnum::VISITOR_COUNTRIES->value => $this->tab == WebsiteDashboardTabsEnum::VISITOR_COUNTRIES->value ?
                    fn () => WebsiteVisitorCountriesResource::collection(IndexWebsiteVisitorCountries::make()->handle($group, $user, $website, $days, $day, $country, WebsiteDashboardTabsEnum::VISITOR_COUNTRIES->value))
                    : Inertia::optional(fn () => WebsiteVisitorCountriesResource::collection(IndexWebsiteVisitorCountries::make()->handle($group, $user, $website, $days, $day, $country, WebsiteDashboardTabsEnum::VISITOR_COUNTRIES->value))),

                WebsiteDashboardTabsEnum::VARNISH->value => $this->tab == WebsiteDashboardTabsEnum::VARNISH->value ?
                    fn () => GetWebsitesVarnishMetrics::run($group, $range)
                    : Inertia::optional(fn () => GetWebsitesVarnishMetrics::run($group, $range)),

                'edge_filters' => $this->edgeFilters($group, $user, $website, $days, $day, $country),

                WebsiteDashboardTabsEnum::CRAWLS->value => $this->tab == WebsiteDashboardTabsEnum::CRAWLS->value ?
                    fn () => CrawlsResource::collection(IndexCrawls::make()->inGroup($group))
                    : Inertia::optional(fn () => CrawlsResource::collection(IndexCrawls::make()->inGroup($group))),
            ]
        )->table(IndexCrawls::make()->tableStructure(parent: $group, prefix: WebsiteDashboardTabsEnum::CRAWLS->value))
            ->table(IndexWebsitesEdgeTraffic::make()->tableStructure(prefix: WebsiteDashboardTabsEnum::EDGE_WEBSITES->value))
            ->table(IndexWebsitesEdgeDatacenters::make()->tableStructure(prefix: WebsiteDashboardTabsEnum::EDGE_DATACENTERS->value))
            ->table(IndexWebsiteVisitorCountries::make()->tableStructure(prefix: WebsiteDashboardTabsEnum::VISITOR_COUNTRIES->value));
    }

    /**
     * The website, period and Varnish range chosen last are kept in the user's settings, so a
     * refresh, a new login or another device opens the dashboard as it was left.
     *
     * @return array{0: string|null, 1: int|null, 2: string|null}
     */
    protected function rememberedFilters(User $user, ?string $website, ?int $days, ?string $range): array
    {
        $saved  = Arr::get($user->settings ?? [], 'websites_dashboard', []);
        $chosen = array_filter([
            'website'       => $website ?? Arr::get($saved, 'website'),
            'days'          => $days ?? Arr::get($saved, 'days'),
            'varnish_range' => $range ?? Arr::get($saved, 'varnish_range'),
        ], fn ($value) => $value !== null);

        if ($chosen != $saved) {
            $user->updateQuietly(['settings' => array_merge($user->settings ?? [], ['websites_dashboard' => $chosen])]);
        }

        return [Arr::get($chosen, 'website'), isset($chosen['days']) ? (int) $chosen['days'] : null, Arr::get($chosen, 'varnish_range')];
    }

    /**
     * @return array{website: string, website_label: string|null, days: int, days_options: array<int, int>, day: string|null, country: string|null}
     */
    protected function edgeFilters(Group $group, User $user, ?string $website, ?int $days, ?string $day, ?string $country): array
    {
        [, $chosen, $selected] = GetWebsitesEdgeTraffic::make()->chosenWebsites($group, $user, $website);
        [$from, $to, $days]    = GetWebsitesEdgeTraffic::make()->window($days, $day);

        return [
            'website'       => $selected,
            'website_label' => match (true) {
                $selected === 'all' => null,
                str_starts_with($selected, 'org:') => $chosen->first()->organisation->name,
                default => $chosen->first()->domain,
            },
            'days'          => $days,
            'days_options'  => GetWebsitesEdgeTraffic::DAYS,
            'day'           => $from->isSameDay($to) ? $from->toDateString() : null,
            'country'       => $country && preg_match('/^[A-Z]{2}$/', $country) ? $country : null,
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                'type'   => 'simple',
                'simple' => [
                    'route' => [
                        'name' => 'grp.websites.index'
                    ],
                    'label' => __('WebOps'),
                ]
            ]
        ];
    }
}
