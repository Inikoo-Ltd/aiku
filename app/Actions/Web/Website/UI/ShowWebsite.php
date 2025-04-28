<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 May 2023 12:18:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\CRM\WebUser\IndexWebUsers;
use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\HasWebAuthorisation;
use App\Actions\Web\ExternalLink\UI\IndexExternalLinks;
use App\Actions\Web\HasWorkshopAction;
use App\Actions\Web\Redirect\UI\IndexRedirects;
use App\Actions\Web\Website\GetWebsiteCloudflareAnalytics;
use App\Actions\Web\Website\GetWebsiteWorkshopLayout;
use App\Enums\UI\Web\WebsiteTabsEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Http\Resources\CRM\WebUsersResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Web\ExternalLinksResource;
use App\Http\Resources\Web\RedirectsResource;
use App\Http\Resources\Web\WebsiteResource;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowWebsite extends OrgAction
{
    use HasWorkshopAction;
    use HasWebAuthorisation;

    private Fulfilment|Shop|Organisation $parent;



    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): Website
    {
        $this->scope  = $shop;
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(WebsiteTabsEnum::values());
        return $website;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, ActionRequest $request): Website
    {
        $this->scope  = $fulfilment;
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(WebsiteTabsEnum::values());

        return $website;
    }


    public function htmlResponse(Website $website, ActionRequest $request): Response
    {

        $analyticReq = $request->only([
            'since',
            'until',
            'showTopNs',
            'partialShowTopNs',
            'partialFilterTimeseries',
            'partialTimeseriesData',
            'partialFilterPerfAnalytics',
            'partialWebVitals',
            'partialWebVitalsData',
        ]);

        return Inertia::render(
            'Org/Web/Website',
            [
                'title'       => __('Website'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    class_basename($this->scope),
                    $request->route()->originalParameters()
                ),
                'navigation'  => $this->parent instanceof Organisation ? [
                    'previous' => $this->getPrevious($website, $request),
                    'next'     => $this->getNext($website, $request),
                ] : null,
                'pageHead'    => [
                    'title'     => $website->name,
                    'model'     => __('Website'),
                    'icon'      => [
                        'title' => __('website'),
                        'icon'  => 'fal fa-globe'
                    ],
                    'iconRight' => $website->state->stateIcon()[$website->state->value],
                    'actions'   =>

                        array_merge(
                            $this->workshopActions($request),
                            [
                                $this->isSupervisor && $website->state == WebsiteStateEnum::IN_PROCESS ? [
                                    'type'  => 'button',
                                    'style' => 'edit',
                                    'label' => __('launch'),
                                    'icon'  => ["fal", "fa-rocket"],
                                    'route' => [
                                        'method'     => 'post',
                                        'name'       => 'grp.models.website.launch',
                                        'parameters' => $website->id
                                    ]
                                ] : [],
                            ]
                        ),


                ],
                // "website_layout" =>  GetWebsiteWorkshopLayout::run($this->scope, $website),
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => WebsiteTabsEnum::navigation()
                ],

                WebsiteTabsEnum::SHOWCASE->value => $this->tab == WebsiteTabsEnum::SHOWCASE->value ? array_merge(WebsiteResource::make($website)->getArray(), ['layout' => GetWebsiteWorkshopLayout::run($this->parent, $website)['routeList']]) : Inertia::lazy(fn () => WebsiteResource::make($website)->getArray()),

                WebsiteTabsEnum::WEB_USERS->value     => $this->tab == WebsiteTabsEnum::WEB_USERS->value
                    ?
                    WebUsersResource::collection(
                        IndexWebUsers::run(
                            parent: $website,
                            prefix: 'web_users'
                        )
                    )
                    : Inertia::lazy(fn () => WebUsersResource::collection(
                        IndexWebUsers::run(
                            parent: $website,
                            prefix: 'web_users'
                        )
                    )),
                WebsiteTabsEnum::CHANGELOG->value => $this->tab == WebsiteTabsEnum::CHANGELOG->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($website))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($website))),

                WebsiteTabsEnum::EXTERNAL_LINKS->value => $this->tab == WebsiteTabsEnum::EXTERNAL_LINKS->value ?
                    fn () => ExternalLinksResource::collection(IndexExternalLinks::run($website))
                    : Inertia::lazy(fn () => ExternalLinksResource::collection(IndexExternalLinks::run($website))),

                WebsiteTabsEnum::REDIRECTS->value => $this->tab == WebsiteTabsEnum::REDIRECTS->value ?
                    fn () => RedirectsResource::collection(IndexRedirects::run($website))
                    : Inertia::lazy(fn () => RedirectsResource::collection(IndexRedirects::run($website))),

                WebsiteTabsEnum::ANALYTICS->value => $this->tab == WebsiteTabsEnum::ANALYTICS->value ?
                    fn () => GetWebsiteCloudflareAnalytics::make()->action($website, $analyticReq)
                    : Inertia::lazy(fn () => GetWebsiteCloudflareAnalytics::make()->action($website, $analyticReq))
            ]
        )->table(
            IndexWebUsers::make()->tableStructure(
                parent: $website,
                modelOperations: [
                    'createLink' => $this->canEdit ? [
                        'route' => [
                            'name'       => 'grp.org.shop.show.websites.show.web-users.create',
                            'parameters' => array_values($request->route()->originalParameters())
                        ],
                        'label' => __('users')
                    ] : false,
                ],
                prefix: 'web_users'
            )
        )->table(IndexHistory::make()->tableStructure(prefix: WebsiteTabsEnum::CHANGELOG->value))
        ->table(IndexRedirects::make()->tableStructure(parent: $website, prefix: WebsiteTabsEnum::REDIRECTS->value))
        ->table(IndexExternalLinks::make()->tableStructure(parent: $website, prefix: WebsiteTabsEnum::EXTERNAL_LINKS->value));
    }


    public function jsonResponse(Website $website): WebsiteResource
    {
        return new WebsiteResource($website);
    }

    public function getBreadcrumbs(string $scope, array $routeParameters, $suffix = null): array
    {

        $website = Website::where('slug', $routeParameters['website'])->first();

        $modelRoute = match ($scope) {
            'Shop' => [
                'name'       => 'grp.org.shops.show.web.websites.show',
                'parameters' => Arr::only($routeParameters, ['organisation','shop','website'])
            ],
            'Fulfilment' => [
                'name'       => 'grp.org.fulfilments.show.web.websites.show',
                'parameters' => Arr::only($routeParameters, ['organisation','fulfilment','website'])
            ],
            default => null
        };


        return
            array_merge(
                ShowOrganisationDashboard::make()->getBreadcrumbs(Arr::only($routeParameters, 'organisation')),
                [
                    [
                        'type'           => 'modelWithIndex',
                        'modelWithIndex' => [
                            'index' => [
                                'route' => [
                                    'name'       => 'grp.org.websites.index',
                                    'parameters' => Arr::only($routeParameters, 'organisation')
                                ],
                                'label' => __('Websites'),
                                'icon'  => 'fal fa-bars'
                            ],
                            'model' => [
                                'route' => $modelRoute,
                                'label' => $website->code,
                                'icon'  => 'fal fa-bars'
                            ]


                        ],
                        'suffix'         => $suffix,
                    ]
                ]
            );

    }

    public function getBreadcrumbsold(string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (string $type, Website $website, array $routeParameters, string $suffix) {
            return [
                [

                    'type'           => $type,
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Websites')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $website->code,
                        ],
                    ],
                    'simple'         => [
                        'route' => $routeParameters['model'],
                        'label' => $website->code
                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.web.websites.show',
            'grp.org.shops.show.web.websites.edit' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    'modelWithIndex',
                    Website::where('slug', $routeParameters['website'])->first(),
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.web.websites.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.web.websites.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                ),
            ),
            'grp.org.fulfilments.show.web.websites.show',
            'grp.org.fulfilments.show.web.websites.edit' =>
            array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    'modelWithIndex',
                    Website::where('slug', $routeParameters['website'])->first(),
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.web.websites.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.web.websites.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }

    public function getPrevious(Website $website, ActionRequest $request): ?array
    {
        $previous = Website::where('code', '<', $website->code)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Website $website, ActionRequest $request): ?array
    {
        $next = Website::where('code', '>', $website->code)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Website $website, string $routeName): ?array
    {
        if (!$website) {
            return null;
        }

        return match ($routeName) {
            'grp.org.websites.show' => [
                'label' => $website->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $website->shop->organisation->slug,
                        'shop'         => $website->shop->slug,
                        'website'      => $website->slug
                    ]
                ]
            ],
        };
    }
}
