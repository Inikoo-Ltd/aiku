<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 18 May 2023 14:27:38 Central European Summer, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\Catalogue\UI\IndexTopListedFamilies;
use App\Actions\Catalogue\UI\IndexTopListedProducts;
use App\Actions\Catalogue\UI\IndexTopSoldProducts;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\CatalogueTabsEnum;
use App\Http\Resources\Catalogue\ShopResource;
use App\Http\Resources\CRM\TopListedProductsResource;
use App\Http\Resources\CRM\TopSoldProductsResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowCatalogue extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request)->withTab(CatalogueTabsEnum::values());

        return $shop;
    }

    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Catalogue/Catalogue',
            [
                'title'       => __('catalogue'),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters(), $request->route()->getName()),
                'navigation'  => [
                    'previous' => $this->getPrevious($shop, $request),
                    'next'     => $this->getNext($shop, $request),
                ],
                'pageHead'    => [
                    'title' => __('Catalogue'),
                    'model' => '',
                    'icon'  => [
                        'title' => __('Catalogue'),
                        'icon'  => 'fal fa-books'
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => CatalogueTabsEnum::navigation()
                ],
                CatalogueTabsEnum::SHOWCASE->value =>
                    $this->tab == CatalogueTabsEnum::SHOWCASE->value
                        ? fn () => GetCatalogueShowcase::run($shop)
                        : Inertia::lazy(fn () => GetCatalogueShowcase::run($shop)),
                CatalogueTabsEnum::TOP_LISTED_FAMILIES->value =>
                    $this->tab == CatalogueTabsEnum::TOP_LISTED_FAMILIES->value
                        ? fn () => TopListedProductsResource::collection(IndexTopListedFamilies::run($shop, prefix: CatalogueTabsEnum::TOP_LISTED_FAMILIES->value))
                        : Inertia::lazy(fn () => TopListedProductsResource::collection(IndexTopListedFamilies::run($shop, prefix: CatalogueTabsEnum::TOP_LISTED_FAMILIES->value))),
                CatalogueTabsEnum::TOP_LISTED_PRODUCTS->value =>
                    $this->tab == CatalogueTabsEnum::TOP_LISTED_PRODUCTS->value
                        ? fn () => TopListedProductsResource::collection(IndexTopListedProducts::run($shop, prefix: CatalogueTabsEnum::TOP_LISTED_PRODUCTS->value))
                        : Inertia::lazy(fn () => TopListedProductsResource::collection(IndexTopListedProducts::run($shop, prefix: CatalogueTabsEnum::TOP_LISTED_PRODUCTS->value))),
                CatalogueTabsEnum::TOP_SOLD_PRODUCTS->value =>
                    $this->tab == CatalogueTabsEnum::TOP_SOLD_PRODUCTS->value
                        ? fn () => TopSoldProductsResource::collection(IndexTopSoldProducts::run($shop, prefix: CatalogueTabsEnum::TOP_SOLD_PRODUCTS->value))
                        : Inertia::lazy(fn () => TopSoldProductsResource::collection(IndexTopSoldProducts::run($shop, prefix: CatalogueTabsEnum::TOP_SOLD_PRODUCTS->value))),
            ]
        )->table(
            IndexTopListedFamilies::make()->tableStructure(
                prefix: CatalogueTabsEnum::TOP_LISTED_FAMILIES->value,
            )
        )->table(
            IndexTopListedProducts::make()->tableStructure(
                prefix: CatalogueTabsEnum::TOP_LISTED_PRODUCTS->value,
            )
        )->table(
            IndexTopSoldProducts::make()->tableStructure(
                prefix: CatalogueTabsEnum::TOP_SOLD_PRODUCTS->value,
            )
        );
    }

    public function jsonResponse(Shop $shop): ShopResource
    {
        return new ShopResource($shop);
    }

    public function getPrevious(Shop $shop, ActionRequest $request): ?array
    {
        $previous = Shop::where('code', '<', $shop->code)->where('organisation_id', $this->organisation->id)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Shop $shop, ActionRequest $request): ?array
    {
        $next = Shop::where('code', '>', $shop->code)->where('organisation_id', $this->organisation->id)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Shop $shop, string $routeName): ?array
    {
        if (!$shop) {
            return null;
        }

        return match ($routeName) {
            'grp.org.shops.show.catalogue.dashboard' => [
                'label' => $shop->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'shop'         => $shop->slug
                    ]
                ]
            ]
        };
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowShop::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.catalogue.dashboard',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Catalogue'),
                    ]
                ]
            ]
        );
    }
}
