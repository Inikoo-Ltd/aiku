<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 08 Apr 2024 09:52:43 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateProduct extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function handle(Organisation|Shop|ProductCategory $parent, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'    => __('new product'),
                'pageHead' => [
                    'title'        => __('new product'),
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name' => match ($request->route()->getName()) {
                                    'shops.show.products.create'    => 'shops.show.products.index',
                                    'shops.products.create'         => 'shops',
                                    default                         => preg_replace('/create$/', 'index', $request->route()->getName())
                                },
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData' => [
                    'fullLayout' => true,
                    'blueprint'  =>
                        [
                            [
                                'title'  => __('Create Product'),
                                'fields' => [
                                    'code' => [
                                        'type'       => 'input',
                                        'label'      => __('code'),
                                        'required'   => true
                                    ],
                                    'name' => [
                                        'type'       => 'input',
                                        'label'      => __('name'),
                                        'required'   => true
                                    ],
                                    'price' => [
                                        'type'       => 'input',
                                        'label'      => __('price'),
                                        'required'   => true
                                    ],
                                    'unit' => [
                                        'type'     => 'input',
                                        'label'    => __('unit'),
                                        'required' => true,
                                    ],
                                    'is_main' => [
                                        'type'     => 'toggle',
                                        'label'    => __('main'),
                                        'value'    => true,
                                        'required' => true,
                                    ],
                                    'org_stocks' => [
                                        'value' => []
                                    ]
                                ]
                            ]
                        ],
                    'route' => [
                        'name'       => 'grp.models.org.catalogue.families.product.store',
                        'parameters' => [
                            'organisation' => $parent->organisation_id,
                            'shop'         => $parent->shop_id,
                            'family'       => $parent->id
                        ]
                    ]
                ],

            ]
        );
    }

    public function inFamilyInDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $family, ActionRequest $request): Response
    {
        $this->parent = $family;
        $this->initialisationFromShop($shop, $request);
        return $this->handle($family, $request);
    }

    public function inFamily(Organisation $organisation, Shop $shop, ProductCategory $family, ActionRequest $request): Response
    {
        $this->parent = $family;
        $this->initialisationFromShop($shop, $request);
        return $this->handle($family, $request);
    }

    public function inFamilyInSubDepartmentInDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, ProductCategory $family, ActionRequest $request): Response
    {
        $this->parent = $family;
        $this->initialisationFromShop($shop, $request);
        return $this->handle($family, $request);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexProductsInProductCategory::make()->getBreadcrumbs(
                productCategory: $this->parent,
                routeName: preg_replace('/create$/', 'index', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'         => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating product'),
                    ]
                ]
            ]
        );
    }

}
