<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Catalogue\WithDepartmentSubNavigation;
use App\Actions\Catalogue\WithFamilySubNavigation;
use App\Actions\Catalogue\WithSubDepartmentSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexProductsSales extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithDepartmentSubNavigation;
    use WithFamilySubNavigation;
    use WithSubDepartmentSubNavigation;

    public const string PREFIX = 'sales';

    private Shop|ProductCategory $parent;

    private ?ProductCategory $grandParent = null;

    public function handle(Shop|ProductCategory $parent): LengthAwarePaginator
    {
        if ($parent instanceof Shop) {
            return IndexProducts::make()->handle($parent, self::PREFIX);
        }

        /** @var LengthAwarePaginator $products */
        $products = IndexProductsInProductCategory::make()->handle($parent, self::PREFIX);

        return $products;
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return ProductsResource::collection($products);
    }

    public function htmlResponse(LengthAwarePaginator $products, ActionRequest $request): Response
    {
        $parent = $this->parent;

        if ($parent instanceof Shop) {
            $subNavigation = IndexProductsInCatalogue::make()->getShopProductsSubNavigation($parent);
            $icon          = [
                'icon'  => ['fal', 'fa-cube'],
                'title' => __('Products')
            ];
            $title         = __('Products');
        } elseif ($parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $subNavigation = $this->getDepartmentSubNavigation($parent);
            $icon          = [
                'icon'  => ['fal', 'fa-folder-tree'],
                'title' => __('Department')
            ];
            $title         = $parent->name;
        } elseif ($parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $subNavigation = $this->getSubDepartmentSubNavigation($parent);
            $icon          = [
                'icon'  => ['fal', 'fa-dot-circle'],
                'title' => __('Sub Department')
            ];
            $title         = $parent->name;
        } else {
            $subNavigation = $this->getFamilySubNavigation($parent, $this->grandParent ?? $parent->shop, $request);
            $icon          = [
                'icon'  => ['fal', 'fa-folder'],
                'title' => __('Family')
            ];
            $title         = $parent->name;
        }

        return Inertia::render(
            'Org/Catalogue/Products',
            [
                'breadcrumbs'    => $this->getBreadcrumbs(
                    $parent,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'     => [],
                'title'          => __('Products sales'),
                'pageHead'       => [
                    'title'         => $title,
                    'model'         => '',
                    'icon'          => $icon,
                    'afterTitle'    => [
                        'label' => __('Products sales')
                    ],
                    'iconRight'     => [
                        'icon' => 'fal fa-money-bill-wave',
                    ],
                    'actions'       => [],
                    'subNavigation' => $subNavigation,
                ],
                'editable_table' => false,
                'shop_id'        => $this->shop->id,
                'currencies'     => $this->shop->currency,
                'familyId'       => $parent instanceof ProductCategory && $parent->type == ProductCategoryTypeEnum::FAMILY ? $parent->id : null,
                'data'           => ProductsResource::collection($products),
                'tabs'           => [
                    'current'    => self::PREFIX,
                    'navigation' => [],
                ],
                self::PREFIX     => ProductsResource::collection($products),
            ]
        )->table(
            $parent instanceof Shop
                ? IndexProducts::make()->tableStructure(shop: $parent, prefix: self::PREFIX)
                : IndexProductsInProductCategory::make()->tableStructure(productCategory: $parent, prefix: self::PREFIX)
        );
    }

    public function getBreadcrumbs(Shop|ProductCategory $parent, string $routeName, array $routeParameters): array
    {
        $suffix = '('.__('Sales').')';

        if ($parent instanceof Shop) {
            return IndexProductsInCatalogue::make()->getBreadcrumbs($routeName, $routeParameters, $suffix);
        }

        return IndexProductsInProductCategory::make()->getBreadcrumbs($parent, $routeName, $routeParameters, $suffix);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFamily(Organisation $organisation, Shop $shop, ProductCategory $family, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $family;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($family);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFamilyInDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $family, ActionRequest $request): LengthAwarePaginator
    {
        $this->grandParent = $department;
        $this->parent      = $family;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($family);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFamilyInSubDepartmentInDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, ProductCategory $family, ActionRequest $request): LengthAwarePaginator
    {
        $this->grandParent = $subDepartment;
        $this->parent      = $family;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($family);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFamilyInSubDepartmentInShop(Organisation $organisation, Shop $shop, ProductCategory $subDepartment, ProductCategory $family, ActionRequest $request): LengthAwarePaginator
    {
        $this->grandParent = $subDepartment;
        $this->parent      = $family;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($family);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $department;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($department);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inSubDepartmentInDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $subDepartment;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($subDepartment);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inSubDepartmentInShop(Organisation $organisation, Shop $shop, ProductCategory $subDepartment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $subDepartment;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($subDepartment);
    }
}
