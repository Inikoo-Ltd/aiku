<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Banner\UI;

use App\Actions\Helpers\Snapshot\UI\IndexSnapshots;
use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Enums\Web\Banner\BannerTabsEnum;
use App\Http\Resources\Helpers\SnapshotResource;
use App\Http\Resources\Web\BannerResource;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Banner;
use App\Models\Web\Website;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowBanner extends OrgAction
{
    use WithActionButtons;
    use WithWebAuthorisation;

    private Website $parent;

    public function handle(Banner $banner): Banner
    {
        return $banner;
    }


    public function asController(Organisation $organisation, Shop $shop, Website $website, Banner $banner, ActionRequest $request): Banner
    {
        $this->parent = $website;
        $this->initialisationFromShop($shop, $request)->withTab(BannerTabsEnum::values());

        return $this->handle($banner);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, Banner $banner, ActionRequest $request): Banner
    {
        $this->parent = $website;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(BannerTabsEnum::values());

        return $this->handle($banner);
    }

    public function htmlResponse(Banner $banner, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Web/Banners/Banner',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($banner, $request),
                    'next'     => $this->getNext($banner, $request),
                ],
                'title'       => $banner->name,
                'pageHead'    => [
                    'title'     => $banner->name,
                    'icon'      => [
                        'tooltip' => __('banner'),
                        'icon'    => 'fal fa-sign'
                    ],
                    'container' => [
                        'icon'    => ['fal', 'fa-globe'],
                        'tooltip' => __('Website'),
                        'label'   => Str::possessive($this->parent->name)
                    ],
                    'iconRight' => $banner->state->stateIcon()[$banner->state->value],
                    'actions'   => [
                        $this->canEdit ? $this->getEditActionIcon($request) : null,
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'primary',
                            'label' => __('Workshop'),
                            'icon'  => ["fal", "fa-drafting-compass"],
                            'route' => [
                                'name'       => preg_replace('/show$/', 'workshop', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false,
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => BannerTabsEnum::navigation()
                ],

                BannerTabsEnum::SHOWCASE->value => $this->tab == BannerTabsEnum::SHOWCASE->value
                    ?
                    fn () => BannerResource::make($banner)->getArray()
                    : Inertia::lazy(
                        fn () => BannerResource::make($banner)->getArray()
                    ),

                BannerTabsEnum::SNAPSHOTS->value => $this->tab == BannerTabsEnum::SNAPSHOTS->value
                    ?
                    fn () => SnapshotResource::collection(
                        IndexSnapshots::run(
                            parent: $banner,
                            prefix: BannerTabsEnum::SNAPSHOTS->value
                        )
                    )
                    : Inertia::lazy(fn () => SnapshotResource::collection(
                        IndexSnapshots::run(
                            parent: $banner,
                            prefix: BannerTabsEnum::SNAPSHOTS->value
                        )
                    )),
            ]
        )->table(
            IndexSnapshots::make()->tableStructure(
                parent: $banner,
                prefix: BannerTabsEnum::SNAPSHOTS->value
            )
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (string $type, Banner $banner, array $routeParameters, string $suffix = null) {
            return [
                [
                    'type'           => $type,
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('banners')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $banner->name,
                        ],

                    ],
                    'simple'         => [
                        'route' => $routeParameters['model'],
                        'label' => $banner->name
                    ],
                    'suffix'         => $suffix
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.web.banners.show',
            'grp.org.shops.show.web.banners.edit',
            =>
            array_merge(
                IndexBanners::make()->getBreadcrumbs($routeName, $routeParameters),
                $headCrumb(
                    'modelWithIndex',
                    Banner::firstWhere('slug', $routeParameters['banner']),
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.web.banners.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.web.banners.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                ),
            ),
            'grp.org.fulfilments.show.web.banners.show',
            'grp.org.fulfilments.show.web.banners.edit'
            =>
            array_merge(
                IndexBanners::make()->getBreadcrumbs($routeName, $routeParameters),
                $headCrumb(
                    'modelWithIndex',
                    Banner::firstWhere('slug', $routeParameters['banner']),
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.web.banners.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.web.banners.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }

    public function getPrevious(Banner $banner, ActionRequest $request): ?array
    {
        if (class_basename($this->parent) == 'PortfolioWebsite') {
            // todo, need to use a join
            $previous = null;
        } else {
            $previous = Banner::where('slug', '<', $banner->slug)->orderBy('slug')->first();
        }

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Banner $banner, ActionRequest $request): ?array
    {
        if (class_basename($this->parent) == 'PortfolioWebsite') {
            // todo, need to use a join
            $next = null;
        } else {
            $next = Banner::where('slug', '>', $banner->slug)->orderBy('slug')->first();
        }


        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Banner $banner, string $routeName): ?array
    {
        if (!$banner) {
            return null;
        }


        return match ($routeName) {
            'customer.banners.banners.show',
            'customer.banners.banners.edit' => [
                'label' => $banner->slug,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'banner' => $banner->slug
                    ]
                ]
            ],
            default => []
        };
    }

}
