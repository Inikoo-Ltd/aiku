<?php

/*
* author Louis Perez
* created on 23-12-2025-13h-27m
* github: https://github.com/louis-perez
* copyright 2025
*/

namespace App\Actions\Catalogue\Variant;

use App\Actions\OrgAction;
use App\Actions\Catalogue\Product\UI\IndexProductsInVariant;
use App\Actions\Catalogue\ProductCategory\UI\ShowFamily;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\UI\Catalogue\VariantTabsEnum;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Http\Resources\Catalogue\ProductVariantResource;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\Variant;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowVariant extends OrgAction
{
    use WithCatalogueAuthorisation;

    private Organisation|ProductCategory|Shop $parent;

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, ProductCategory $family, Variant $variant, ActionRequest $request): Variant
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(VariantTabsEnum::values());

        return $this->handle($variant);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inSubDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, ProductCategory $family, Variant $variant, ActionRequest $request): Variant
    {
        $this->parent = $subDepartment;

        $this->initialisationFromShop($shop, $request)->withTab(VariantTabsEnum::values());

        return $this->handle($variant);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inSubDepartmentInShop(Organisation $organisation, Shop $shop, ProductCategory $subDepartment, ProductCategory $family, Variant $variant, ActionRequest $request): Variant
    {
        $this->parent = $subDepartment;

        $this->initialisationFromShop($shop, $request)->withTab(VariantTabsEnum::values());

        return $this->handle($variant);
    }

    public function htmlResponse(Variant $variant): Response
    {
        $masterProductInVariant = ProductVariantResource::collection(Product::whereIn('id', data_get($variant->data, 'products.*.product.id', []))->get());
        $variantData = [
                'variant'            => $variant,
                'products'           => $masterProductInVariant
            ];

        return Inertia::render(
            'Org/Catalogue/Variant',
            [
                'breadcrumbs'     => $this->getBreadcrumbs(
                    $variant,
                    request()->route()->getName(),
                    request()->route()->originalParameters()
                ),
                'title'           => __('Show Variant'),
                'pageHead'        => [
                    'title' => $variant->code,
                    'model'         => __('Variants'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-shapes'],
                        'title' => __('Variant')
                    ],
                ],
                'tabs'                    => [
                    'current'    => $this->tab,
                    'navigation' => VariantTabsEnum::navigation()
                ],
                'masterRoute'        => [
                    'name'      => 'grp.masters.master_shops.show.master_families.master_variants.show',
                    'parameters' => [
                        'masterShop'      => $variant->masterVariant->masterShop->slug,
                        'masterFamily'    => $variant->masterVariant->masterFamily->slug,
                        'masterVariant'   => $variant->masterVariant->slug,
                    ],
                ],
                'variantSlugs'   => [$variant->slug => productCodeToHexCode($variant->slug)],
                VariantTabsEnum::SHOWCASE->value =>
                    $this->tab === VariantTabsEnum::SHOWCASE->value ? $variantData : Inertia::lazy(fn () => $variantData),
                VariantTabsEnum::PRODUCTS->value => 
                    $this->tab === VariantTabsEnum::PRODUCTS->value ? ProductsResource::collection(IndexProductsInVariant::run($variant)) : Inertia::lazy(fn () => ProductsResource::collection(IndexProductsInVariant::run($variant))),
            ]
        )
                ->table(IndexProductsInVariant::make()->tableStructure(variant:$variant,prefix: VariantTabsEnum::PRODUCTS->value));
    }

    /**
     * @throws \Throwable
     */
    public function handle(Variant $variant): Variant
    {
        return $variant;
    }

    public function getBreadcrumbs(Variant $variant, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (Variant $variant, array $routeParameters, $suffix) {
            return [

                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'label' => __('Variant')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $variant->code,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],

            ];
        };

        return array_merge(
            ShowFamily::make()->getBreadcrumbs(
                family: $variant->family,
                routeName: 'grp.org.shops.show.catalogue.families.show',
                routeParameters: $routeParameters,
            ),
            $headCrumb(
                $variant,
                [
                    'model' => [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                ],
                $suffix
            )
        );
    }
}
