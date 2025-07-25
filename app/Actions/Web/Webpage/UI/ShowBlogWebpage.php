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

class ShowBlogWebpage extends OrgAction
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

    public function getTypeSpecificActions(Webpage $webpage): array
    {
        $actions = [];

        if (!$this->canEdit) {
            return $actions;
        }

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
        

        return $actions;
    }

    public function htmlResponse(Webpage $webpage, ActionRequest $request): Response
    {

        $actions = [];

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
                    'iconRight'     => $webpage->state->stateIcon()[$webpage->state->value],
                    'actions'       => $actions,
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
                            'label' => __('Blogs')
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
        /** @var Website $website */
        $website = request()->route()->parameter('website');

        return match ($routeName) {
            'grp.org.shops.show.web.blogs.show', 'grp.org.shops.show.web.webpages.edit', 'grp.org.shops.show.web.webpages.workshop', 'grp.org.shops.show.web.webpages.redirect.create' => array_merge(
                ShowWebsite::make()->getBreadcrumbs(
                    $website,
                    'grp.org.shops.show.web.websites.show',
                    Arr::only($routeParameters, ['organisation', 'shop', 'website'])
                ),
                $headCrumb(
                    $webpage,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.web.blogs.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'website'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.web.blogs.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'website', 'webpage'])
                        ]
                    ],
                    $suffix
                ),
            ),
            default => [],
        };
    }
}
