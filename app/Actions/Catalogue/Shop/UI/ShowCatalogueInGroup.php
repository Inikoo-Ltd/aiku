<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\Catalogue\UI\IndexTopListedFamilies;
use App\Actions\Catalogue\UI\IndexTopListedProducts;
use App\Actions\Catalogue\UI\IndexTopSoldProducts;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Catalogue\CatalogueTabsEnum;
use App\Http\Resources\CRM\TopListedProductsResource;
use App\Http\Resources\CRM\TopSoldProductsResource;
use App\Http\Resources\SysAdmin\Group\GroupResource;
use App\Models\SysAdmin\Group;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowCatalogueInGroup extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function asController(ActionRequest $request): Group
    {
        $this->asAction = true;
        $this->initialisationFromGroup(group(), $request)->withTab(CatalogueTabsEnum::valuesExcept([CatalogueTabsEnum::SHOWCASE]));

        return $this->group;
    }

    public function htmlResponse(Group $group): Response
    {
        return Inertia::render(
            'Org/Catalogue/Catalogue',
            [
                'title'       => __('catalogue'),
                'breadcrumbs' => $this->getBreadcrumbs(),
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
                    'navigation' => CatalogueTabsEnum::navigationExcept([CatalogueTabsEnum::SHOWCASE])
                ],
                CatalogueTabsEnum::TOP_LISTED_FAMILIES->value =>
                    $this->tab == CatalogueTabsEnum::TOP_LISTED_FAMILIES->value
                        ? fn () => TopListedProductsResource::collection(IndexTopListedFamilies::run($group, prefix: CatalogueTabsEnum::TOP_LISTED_FAMILIES->value))
                        : Inertia::lazy(fn () => TopListedProductsResource::collection(IndexTopListedFamilies::run($group, prefix: CatalogueTabsEnum::TOP_LISTED_FAMILIES->value))),
                CatalogueTabsEnum::TOP_LISTED_PRODUCTS->value =>
                    $this->tab == CatalogueTabsEnum::TOP_LISTED_PRODUCTS->value
                        ? fn () => TopListedProductsResource::collection(IndexTopListedProducts::run($group, prefix: CatalogueTabsEnum::TOP_LISTED_PRODUCTS->value))
                        : Inertia::lazy(fn () => TopListedProductsResource::collection(IndexTopListedProducts::run($group, prefix: CatalogueTabsEnum::TOP_LISTED_PRODUCTS->value))),
                CatalogueTabsEnum::TOP_SOLD_PRODUCTS->value =>
                    $this->tab == CatalogueTabsEnum::TOP_SOLD_PRODUCTS->value
                        ? fn () => TopSoldProductsResource::collection(IndexTopSoldProducts::run($group, prefix: CatalogueTabsEnum::TOP_SOLD_PRODUCTS->value))
                        : Inertia::lazy(fn () => TopSoldProductsResource::collection(IndexTopSoldProducts::run($group, prefix: CatalogueTabsEnum::TOP_SOLD_PRODUCTS->value))),
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

    public function jsonResponse(Group $group): GroupResource
    {
        return new GroupResource($group);
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.catalogue.show',
                            'parameters' => []
                        ],
                        'label' => __('Catalogue'),
                    ]
                ]
            ]
        );
    }
}
