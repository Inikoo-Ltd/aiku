<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 08 Apr 2024 09:52:43 Central Indonesia Time, Bali Office, Indonesia
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

//todo, Consider delete this action, new products are created in masters.
class CreateProduct extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function handle(Organisation|Shop|ProductCategory $parent, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $parent,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('New product'),
                'pageHead'    => [
                    'title'   => __('New product'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => match ($request->route()->getName()) {
                                    'shops.show.products.create' => 'shops.show.products.index',
                                    'shops.products.create' => 'shops',
                                    default => preg_replace('/create$/', 'index', $request->route()->getName())
                                },
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'fullLayout' => true,
                    'blueprint'  =>
                        [
                            [
                                'title'  => __('Create Product'),
                                'fields' => [
                                    'code'       => [
                                        'type'     => 'input',
                                        'label'    => __('Code'),
                                        'required' => true
                                    ],
                                    'name'       => [
                                        'type'     => 'input',
                                        'label'    => __('Name'),
                                        'required' => true
                                    ],
                                    'price'      => [
                                        'type'     => 'input',
                                        'label'    => __('Price'),
                                        'required' => true
                                    ],
                                    'unit'       => [
                                        'type'     => 'input',
                                        'label'    => __('unit'),
                                        'required' => true,
                                    ],
                                    'is_main'    => [
                                        'type'     => 'toggle',
                                        'label'    => __('main'),
                                        'value'    => true,
                                        'required' => true,
                                    ],
                                    'org_stocks' => [
                                        'type'        => 'product_parts',
                                        'label'       => __('Parts'),
                                        'full'        => true,
                                        'fetch_route' => [
                                            'name'       => 'grp.json.org_stocks.index',
                                            'parameters' => [
                                                'organisation' => $parent->organisation_id,
                                            ]
                                        ],
                                        'value'       => []
                                    ],
                                ]
                            ]
                        ],
                    'route'      => [
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

    /** @noinspection PhpUnusedParameterInspection */
    public function inFamilyInDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $family, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($family, $request);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFamily(Organisation $organisation, Shop $shop, ProductCategory $family, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($family, $request);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFamilyInSubDepartmentInDepartment(Organisation $organisation, Shop $shop, ProductCategory $department, ProductCategory $subDepartment, ProductCategory $family, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($family, $request);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFamilyInSubDepartmentInShop(Organisation $organisation, Shop $shop, ProductCategory $subDepartment, ProductCategory $family, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($family, $request);
    }

    public function getBreadcrumbs(Organisation|Shop|ProductCategory $parent, string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexProductsInProductCategory::make()->getBreadcrumbs(
                productCategory: $parent,
                routeName: preg_replace('/create$/', 'index', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating product'),
                    ]
                ]
            ]
        );
    }

}
