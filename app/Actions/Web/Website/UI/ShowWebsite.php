<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 May 2023 12:18:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\ExternalLink\UI\IndexExternalLinks;
use App\Actions\Web\HasWorkshopAction;
use App\Actions\Web\Redirect\UI\IndexRedirects;
use App\Actions\Web\Website\GetWebsiteWorkshopLayout;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\UI\Web\WebsiteTabsEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
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
    use WithWebAuthorisation;

    private Fulfilment|Shop|Organisation $parent;


    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): Website
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(WebsiteTabsEnum::values());

        return $website;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, ActionRequest $request): Website
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(WebsiteTabsEnum::values());

        return $website;
    }


    public function htmlResponse(Website $website, ActionRequest $request): Response
    {
        $shop               = $website->shop;
        $stats              = [
            [
                'label' => __('Departments'),
                'route' => [
                    'name'       => 'grp.org.shops.show.web.webpages.index.sub_type.department',
                    'parameters' => [
                        'organisation' => $shop->organisation->slug,
                        'shop'         => $shop->slug,
                        'website'      => $website->slug
                    ]
                ],
                'icon'  => 'fal fa-folder-tree',
                "color" => "#b45309",
                'value' => $website->webStats->number_webpages_sub_type_department,
            ],
            [
                'label' => __('Sub Departments'),
                'route' => [
                    'name'       => 'grp.org.shops.show.web.webpages.index.sub_type.sub_department',
                    'parameters' => [
                        'organisation' => $shop->organisation->slug,
                        'shop'         => $shop->slug,
                        'website'      => $website->slug
                    ]
                ],
                'icon'  => 'fal fa-folder-tree',
                "color" => "#f59e0b",
                'value' => $website->webStats->number_webpages_sub_type_sub_department,
            ],
            [
                'label' => __('Families'),
                'route' => [
                    'name'       => 'grp.org.shops.show.web.webpages.index.sub_type.family',
                    'parameters' => [
                        'organisation' => $shop->organisation->slug,
                        'shop'         => $shop->slug,
                        'website'      => $website->slug
                    ]
                ],
                'icon'  => 'fal fa-folder',
                "color" => "#4338ca",
                'value' => $website->webStats->number_webpages_sub_type_family,
            ],
            [
                'label' => __('Products'),
                'route' => [
                    'name'       => 'grp.org.shops.show.web.webpages.index.sub_type.product',
                    'parameters' => [
                        'organisation' => $shop->organisation->slug,
                        'shop'         => $shop->slug,
                        'website'      => $website->slug
                    ]
                ],
                'icon'  => 'fal fa-cube',
                "color" => "#6366f1",
                'value' => $website->webStats->number_webpages_sub_type_product,
            ],
        ];
        $content_blog_stats = [
            [
                'label' => __('Contents'),
                'route' => [
                    'name'       => 'grp.org.shops.show.web.webpages.index.type.content',
                    'parameters' => [
                        'organisation' => $shop->organisation->slug,
                        'shop'         => $shop->slug,
                        'website'      => $website->slug
                    ]
                ],
                'icon'  => 'fal fa-columns',
                "color" => "#b45309",
                'value' => $website->webStats->number_webpages_sub_type_content,
            ],
            [
                'label' => __('Blogs'),
                'route' => [
                    'name'       => 'grp.org.shops.show.web.blogs.index',
                    'parameters' => [
                        'organisation' => $shop->organisation->slug,
                        'shop'         => $shop->slug,
                        'website'      => $website->slug
                    ]
                ],
                'icon'  => 'fal fa-newspaper',
                "color" => "#f59e0b",
                'value' => $website->webStats->number_webpages_sub_type_blog,
            ],
        ];

        $route_storefront = [
            'name'       => 'grp.org.shops.show.web.webpages.show',
            'parameters' => [
                'organisation' => $shop->organisation->slug,
                'shop'         => $shop->slug,
                'website'      => $website->slug,
                'webpage'      => 'storefront-'.$shop->slug,
            ]
        ];

        if ($website->shop->type == ShopTypeEnum::FULFILMENT) {
            $route_storefront = [
                'name'       => 'grp.org.fulfilments.show.web.webpages.show',
                'parameters' => [
                    'organisation' => $shop->organisation->slug,
                    'fulfilment'   => $shop->slug,
                    'website'      => $website->slug,
                    'webpage'      => 'storefront-'.$shop->slug,
                ]
            ];
        }

        return Inertia::render(
            'Org/Web/Website',
            [
                'title'       => __('Website'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $website,
                    $request->route()->getName(),
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
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => WebsiteTabsEnum::navigation()
                ],

                'route_storefront' => $route_storefront,

                'route_redirects' => [
                    'submit'              => [
                        'name'       => 'grp.models.website.redirect.store',
                        'parameters' => [
                            'organisation' => $shop->organisation->slug,
                            'shop'         => $shop->slug,
                            'website'      => $website->id
                        ]
                    ],
                    'fetch_live_webpages' => [
                        'name'       => 'grp.json.active_webpages.index',
                        'parameters' => [
                            'shop' => $shop->slug,
                        ]
                    ],
                ],
                'migrated' => $website->migrated,
                'luigi_data' => [
                    'last_reindexed'        => Arr::get($website->settings, "luigisbox.last_reindex_at"),
                    'luigisbox_tracker_id'  => Arr::get($website->settings, "luigisbox.tracker_id"),
                    'luigisbox_private_key' => Arr::get($website->settings, "luigisbox.private_key"),
                    'luigisbox_lbx_code'    => Arr::get($website->settings, "luigisbox.lbx_code"),
                ],

                WebsiteTabsEnum::SHOWCASE->value => $this->tab == WebsiteTabsEnum::SHOWCASE->value ? array_merge(
                    WebsiteResource::make($website)->getArray(),
                    ['layout' => GetWebsiteWorkshopLayout::run($this->parent, $website)['routeList']],
                    ['stats' => $stats, 'content_blog_stats' => $content_blog_stats]
                )
                    : Inertia::lazy(fn () => WebsiteResource::make($website)->getArray()),


                WebsiteTabsEnum::CHANGELOG->value => $this->tab == WebsiteTabsEnum::CHANGELOG->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($website))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($website))),

                WebsiteTabsEnum::EXTERNAL_LINKS->value => $this->tab == WebsiteTabsEnum::EXTERNAL_LINKS->value ?
                    fn () => ExternalLinksResource::collection(IndexExternalLinks::run($website))
                    : Inertia::lazy(fn () => ExternalLinksResource::collection(IndexExternalLinks::run($website))),

                WebsiteTabsEnum::REDIRECTS->value => $this->tab == WebsiteTabsEnum::REDIRECTS->value ?
                    fn () => RedirectsResource::collection(IndexRedirects::run($website))
                    : Inertia::lazy(fn () => RedirectsResource::collection(IndexRedirects::run($website))),

            ]
        )->table(IndexHistory::make()->tableStructure(prefix: WebsiteTabsEnum::CHANGELOG->value))
            ->table(IndexRedirects::make()->tableStructure(parent: $website, prefix: WebsiteTabsEnum::REDIRECTS->value))
            ->table(IndexExternalLinks::make()->tableStructure(parent: $website, prefix: WebsiteTabsEnum::EXTERNAL_LINKS->value));
    }


    public function jsonResponse(Website $website): WebsiteResource
    {
        return new WebsiteResource($website);
    }

    public function getBreadcrumbs(Website $website, string $routeName, array $routeParameters, $suffix = null): array
    {
        $modelRoute = match ($routeName) {
            'grp.org.shops.show.web.websites.show',
            'grp.org.shops.show.web.websites.edit' => [
                'name'       => 'grp.org.shops.show.web.websites.show',
                'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'website'])
            ],
            'grp.org.fulfilments.show.web.websites.show',
            'grp.org.fulfilments.show.web.websites.edit' => [
                'name'       => 'grp.org.fulfilments.show.web.websites.show',
                'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'website'])
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
                                'label' => $website->domain,
                                'icon'  => 'fal fa-bars'
                            ]


                        ],
                        'suffix'         => $suffix,
                    ]
                ]
            );
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
