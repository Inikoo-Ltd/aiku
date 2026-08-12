<?php

/*
 * author Arya Permana - Kirin
 * created on 05-08-2026-11h-00m
 * github: https://github.com/KirinZero0
 * copyright 2026
*/

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Masters\MasterAsset\UI\ShowMasterProduct;
use App\Actions\Masters\MasterAsset\WithMasterProductSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Http\Resources\Catalogue\ProductsResource;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexProductsInMasterProductSales extends OrgAction
{
    use WithMasterProductSubNavigation;
    use WithMastersAuthorisation;

    public const string PREFIX = 'sales';

    private MasterAsset $parent;

    public function handle(MasterAsset $masterAsset): LengthAwarePaginator
    {
        return IndexProductsInMasterProduct::make()->handle($masterAsset, self::PREFIX);
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return ProductsResource::collection($products);
    }

    public function htmlResponse(LengthAwarePaginator $products, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Catalogue/Products',
            [
                'breadcrumbs'    => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'     => [],
                'title'          => $this->parent->name,
                'pageHead'       => [
                    'title'         => $this->parent->name,
                    'model'         => '',
                    'icon'          => [
                        'icon'  => ['fal', 'fa-cube'],
                        'title' => $this->parent->name
                    ],
                    'afterTitle'    => [
                        'label' => __('Products in shop sales')
                    ],
                    'iconRight'     => [
                        'icon' => 'fal fa-money-bill-wave',
                    ],
                    'actions'       => [],
                    'subNavigation' => $this->getMasterProductsSubNavigation($this->parent),
                ],
                'data'           => ProductsResource::collection($products),
                'editable_table' => false,
                'tabs'           => [
                    'current'    => self::PREFIX,
                    'navigation' => [],
                ],
                self::PREFIX     => ProductsResource::collection($products),
            ]
        )->table(IndexProductsInMasterProduct::make()->tableStructure(self::PREFIX, $this->parent));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowMasterProduct::make()->getBreadcrumbs(
                $this->parent,
                'grp.masters.master_shops.show.master_products.show',
                $routeParameters
            ),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Sales'),
                        'icon'  => 'fal fa-money-bill-wave',
                    ],
                ]
            ]
        );
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(MasterShop $masterShop, MasterAsset $masterProduct, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $masterProduct;
        $this->initialisationFromGroup($masterProduct->group, $request);

        return $this->handle($masterProduct);
    }
}
