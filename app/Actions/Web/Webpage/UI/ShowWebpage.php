<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 11:35:41 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Helpers\Snapshot\UI\IndexSnapshots;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\UI\WithInertia;
use App\Actions\Web\ExternalLink\UI\IndexExternalLinks;
use App\Actions\Web\HasWorkshopAction;
use App\Actions\Web\Redirect\UI\IndexRedirects;
use App\Actions\Web\Webpage\GetWebpageGoogleCloud;
use App\Actions\Web\Webpage\WithWebpageSubNavigation;
use App\Actions\Web\Website\UI\ShowWebsite;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\UI\Web\WebpageTabsEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Http\Resources\Helpers\SnapshotResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Web\ExternalLinksResource;
use App\Http\Resources\Web\RedirectsResource;
use App\Http\Resources\Web\WebpageResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowWebpage extends OrgAction
{
    use AsAction;
    use WithInertia;
    use HasWorkshopAction;
    use WithWebAuthorisation;
    use WithWebpageSubNavigation;


    public function asController(Organisation $organisation, Shop $shop, Website $website, Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->initialisationFromShop($shop, $request)->withTab(WebpageTabsEnum::values());

        return $webpage;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(WebpageTabsEnum::values());

        return $webpage;
    }


    public function getModelActions(Webpage $webpage): array
    {
        $actions = [];
        if ($webpage->model_type == 'ProductCategory') {
            $actions = $this->getModelProductCategoryActions($webpage);
        } elseif ($webpage->model_type == 'Product') {
            $actions = $this->getModelProductActions($webpage);
        } elseif ($webpage->model_type == 'Collection') {
            $actions = $this->getModelCollectionActions($webpage);
        }

        return $actions;
    }


    public function createRedirectAction(Webpage $webpage): array
    {
        $actions = [];

        if ($this->canEdit) {
            if ($webpage->shop->type == ShopTypeEnum::FULFILMENT) {
                $redirectRoute = [
                    'name'       => 'grp.org.fulfilments.show.web.webpages.redirect.create',
                    'parameters' => [
                        'organisation' => $webpage->organisation->slug,
                        'fulfilment'   => $webpage->shop->fulfilment->slug,
                        'website'      => $webpage->website->slug,
                        'webpage'      => $webpage->slug
                    ]
                ];
            } else {
                $redirectRoute = [
                    'name'       => 'grp.org.shops.show.web.webpages.redirect.create',
                    'parameters' => [
                        'organisation' => $webpage->organisation->slug,
                        'shop'         => $webpage->shop->slug,
                        'website'      => $webpage->website->slug,
                        'webpage'      => $webpage->slug
                    ]
                ];
            }

            $actions[] = [
                'type'    => 'button',
                'style'   => 'edit',
                'icon'    => ["fal", "fa-directions"],
                'tooltip' => __('New Redirect'),
                'route'   => $redirectRoute
            ];
        }


        return $actions;
    }


    public function getModelCollectionActions(Webpage $webpage): array
    {
        $actions = [];

        /** @var \App\Models\Catalogue\Collection $collection */
        $collection = $webpage->model;


        $actions[] = [
            'type'    => 'button',
            'style'   => 'edit',
            'tooltip' => __('Collection'),
            'icon'    => ["fal", "fa-album-collection"],
            'route'   => [
                'name'       => 'grp.org.shops.show.catalogue.collections.show',
                'parameters' => [
                    'organisation' => $webpage->organisation->slug,
                    'shop'         => $webpage->shop->slug,
                    'collection'   => $collection->slug
                ]
            ]
        ];


        return $actions;
    }

    public function getModelProductActions(Webpage $webpage): array
    {
        $actions = [];

        /** @var Product $product */
        $product = $webpage->model;


        $actions[] = [
            'type'    => 'button',
            'style'   => 'edit',
            'tooltip' => __('Product'),
            'icon'    => ["fal", "fa-cube"],
            'route'   => [
                'name'       => 'grp.org.shops.show.catalogue.products.show',
                'parameters' => [
                    'organisation' => $webpage->organisation->slug,
                    'shop'         => $webpage->shop->slug,
                    'department'   => $product->slug
                ]
            ]
        ];


        return $actions;
    }

    public function getModelProductCategoryActions(Webpage $webpage): array
    {
        $actions = [];

        /** @var ProductCategory $productCategory */
        $productCategory = $webpage->model;

        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $actions[] = [
                'type'    => 'button',
                'style'   => 'edit',
                'tooltip' => __('Department'),
                'icon'    => ["fal", "fa-folder-tree"],
                'route'   => [
                    'name'       => 'grp.org.shops.show.catalogue.departments.show',
                    'parameters' => [
                        'organisation' => $webpage->organisation->slug,
                        'shop'         => $webpage->shop->slug,
                        'department'   => $productCategory->slug
                    ]
                ]
            ];
        } elseif ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $actions[] = [
                'type'    => 'button',
                'style'   => 'edit',
                'tooltip' => __('Sub Department'),
                'icon'    => ["fal", "fa-folder-tree"],
                'route'   => [
                    'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show',
                    'parameters' => [
                        'organisation'  => $webpage->organisation->slug,
                        'shop'          => $webpage->shop->slug,
                        'department'    => $productCategory->department->slug,
                        'subDepartment' => $productCategory->slug
                    ]
                ]
            ];
        } else {
            $actions[] = [
                'type'  => 'button',
                'style' => 'edit',
                'icon'  => ["fal", "fa-folder"],
                'route' => [
                    'name'       => 'grp.org.shops.show.catalogue.families.show',
                    'parameters' => [
                        'organisation' => $webpage->organisation->slug,
                        'shop'         => $webpage->shop->slug,
                        'family'       => $productCategory->slug
                    ]
                ]
            ];
        }


        if ($this->canEdit) {
            if ($webpage->shop->type == ShopTypeEnum::FULFILMENT) {
                $workshopRoute = [
                    'name'       => 'grp.org.fulfilments.show.web.webpages.show.blueprint.show',
                    'parameters' => [
                        'organisation' => $webpage->organisation->slug,
                        'fulfilment'   => $webpage->shop?->fulfilment->slug,
                        'website'      => $webpage->website->slug,
                        'webpage'      => $webpage->slug
                    ]
                ];
            } else {
                $workshopRoute = [
                    'name'       => 'grp.org.shops.show.web.webpages.show.blueprint.show',
                    'parameters' => [
                        'organisation' => $webpage->organisation->slug,
                        'shop'         => $webpage->shop->slug,
                        'website'      => $webpage->website->slug,
                        'webpage'      => $webpage->slug
                    ]
                ];
            }

            $actions[] = [
                'type'    => 'button',
                'style'   => 'edit',
                'icon'    => ["fal", "fa-object-group"],
                'tooltip' => __('blueprint'),
                'route'   => $workshopRoute
            ];
        }


        return $actions;
    }

    public function getTypeSpecificActions(Webpage $webpage): array
    {
        $actions = [];

        if (!$this->canEdit) {
            return $actions;
        }


        if ($webpage->sub_type == WebpageSubTypeEnum::BLOG) {
            $actions[] = [
                'type'  => 'button',
                'style' => 'create',
                'label' => __('new article'),
                'route' => [
                    'name'       => 'org.websites.show.blog.article.create',
                    'parameters' => [
                        'website' => $webpage->website->slug,
                    ]
                ]
            ];
        } elseif (in_array($webpage->type, [WebpageTypeEnum::STOREFRONT, WebpageTypeEnum::CONTENT])) {
            $actions[] = [
                'type'  => 'button',
                'style' => 'create',
                'label' => __('new webpage'),
                'route' => [
                    'name'       => 'org.websites.show.webpages.show.webpages.create',
                    'parameters' => [
                        'website' => $webpage->website->slug,
                        'webpage' => $webpage->slug
                    ]
                ]
            ];
        }

        return $actions;
    }

    public function htmlResponse(Webpage $webpage, ActionRequest $request): Response
    {
        $subNavigation = $this->getWebpageNavigation($webpage->website);


        $actions = $this->getModelActions($webpage);


        $actions = array_merge($actions, $this->createRedirectAction($webpage));
        $actions = array_merge($actions, $this->workshopActions($request));
        $actions = array_merge($actions, $this->getTypeSpecificActions($webpage));


        $subNavigationRoot = '';

        return Inertia::render(
            'Org/Web/Webpage',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('webpage'),
                'pageHead'    => [
                    'title'         => $webpage->code,
                    'afterTitle'    => [
                        'label' => '../'.$webpage->url,
                    ],
                    'icon'          => [
                        'title' => __('webpage'),
                        'icon'  => 'fal fa-browser'
                    ],
                     'iconRight'          => $webpage->state->stateIcon()[$webpage->state->value],
                    'actions'       => $actions,
                    'subNavigation' => $subNavigation,
                ],

                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => WebpageTabsEnum::navigation()
                ],
                'root_active' => $subNavigationRoot,

                WebpageTabsEnum::SHOWCASE->value => $this->tab == WebpageTabsEnum::SHOWCASE->value ?
                    fn () => WebpageResource::make($webpage)->getArray()
                    : Inertia::lazy(fn () => WebpageResource::make($webpage)->getArray()),

                WebpageTabsEnum::SNAPSHOTS->value => $this->tab == WebpageTabsEnum::SNAPSHOTS->value ?
                    fn () => SnapshotResource::collection(IndexSnapshots::run(parent: $webpage, prefix: 'snapshots'))
                    : Inertia::lazy(fn () => SnapshotResource::collection(IndexSnapshots::run(parent: $webpage, prefix: 'snapshots'))),

                WebpageTabsEnum::EXTERNAL_LINKS->value => $this->tab == WebpageTabsEnum::EXTERNAL_LINKS->value ?
                    fn () => ExternalLinksResource::collection(IndexExternalLinks::run($webpage))
                    : Inertia::lazy(fn () => ExternalLinksResource::collection(IndexExternalLinks::run($webpage))),

                WebpageTabsEnum::WEBPAGES->value  => $this->tab == WebpageTabsEnum::WEBPAGES->value
                    ?
                    fn () => WebpageResource::collection(
                        IndexWebpages::run(
                            parent: $webpage,
                            prefix: 'webpages'
                        )
                    )
                    : Inertia::lazy(fn () => WebpageResource::collection(
                        IndexWebpages::run(
                            parent: $webpage,
                            prefix: 'webpages'
                        )
                    )),
                WebpageTabsEnum::ANALYTICS->value => $this->tab == WebpageTabsEnum::ANALYTICS->value ?
                    fn () => GetWebpageGoogleCloud::make()->action($webpage, $request->only(['startDate', 'endDate', 'searchType']))
                    : Inertia::lazy(fn () => GetWebpageGoogleCloud::make()->action($webpage, $request->only(['startDate', 'endDate', 'searchType']))),

                WebpageTabsEnum::CHANGELOG->value => $this->tab == WebpageTabsEnum::CHANGELOG->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($webpage))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($webpage))),

                WebpageTabsEnum::REDIRECTS->value => $this->tab == WebpageTabsEnum::REDIRECTS->value ?
                    fn () => RedirectsResource::collection(IndexRedirects::run($webpage))
                    : Inertia::lazy(fn () => RedirectsResource::collection(IndexRedirects::run($webpage)))


            ]
        )->table(
            IndexWebpages::make()->tableStructure(parent: $webpage, prefix: 'webpages')
        )->table(
            IndexExternalLinks::make()->tableStructure(parent: $webpage, prefix: WebpageTabsEnum::EXTERNAL_LINKS->value)
        )->table(
            IndexSnapshots::make()->tableStructure(
                parent: $webpage,
                prefix: 'snapshots'
            )
        )->table(
            IndexRedirects::make()->tableStructure(
                parent: $webpage,
                prefix: WebpageTabsEnum::REDIRECTS->value
            )
        )
            ->table(
                IndexHistory::make()->tableStructure(
                    prefix: WebpageTabsEnum::CHANGELOG->value
                )
            );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (Webpage $webpage, array $routeParameters, string $suffix) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Webpages')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $webpage->code,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };


        $webpage = Webpage::where('slug', $routeParameters['webpage'])->first();

        return
            match ($routeName) {
                'grp.org.shops.show.web.webpages.show',
                'grp.org.shops.show.web.webpages.edit',
                'grp.org.shops.show.web.webpages.workshop',
                'grp.org.shops.show.web.webpages.redirect.create' =>
                array_merge(
                    ShowWebsite::make()->getBreadcrumbs(
                        'Shop',
                        Arr::only($routeParameters, ['organisation', 'shop', 'website'])
                    ),
                    $headCrumb(
                        $webpage,
                        [
                            'index' => [
                                'name'       => 'grp.org.shops.show.web.webpages.index',
                                'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'website'])
                            ],
                            'model' => [
                                'name'       => 'grp.org.shops.show.web.webpages.show',
                                'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'website', 'webpage'])
                            ]
                        ],
                        $suffix
                    ),
                ),

                'grp.org.fulfilments.show.web.webpages.show',
                'grp.org.fulfilments.show.web.webpages.edit',
                'grp.org.fulfilments.show.web.webpages.workshop',
                'grp.org.fulfilments.show.web.webpages.redirect.create', =>
                array_merge(
                    ShowWebsite::make()->getBreadcrumbs(
                        'Fulfilment',
                        Arr::only($routeParameters, ['organisation', 'fulfilment', 'website'])
                    ),
                    $headCrumb(
                        $webpage,
                        [
                            'index' => [
                                'name'       => 'grp.org.fulfilments.show.web.webpages.index',
                                'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'website'])
                            ],
                            'model' => [
                                'name'       => 'grp.org.fulfilments.show.web.webpages.show',
                                'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'website', 'webpage'])
                            ]
                        ],
                        $suffix
                    ),
                )
            };
    }
}
