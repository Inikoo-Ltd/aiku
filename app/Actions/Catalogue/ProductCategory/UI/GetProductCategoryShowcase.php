<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 May 2023 20:59:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\DepartmentResource;
use App\Http\Resources\Catalogue\FamilyResource;
use App\Http\Resources\Catalogue\SubDepartmentResource;
use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\Concerns\AsObject;

class GetProductCategoryShowcase
{
    use AsObject;

    public function handle(ProductCategory $productCategory): array
    {
        $routeName = request()->route()->getName();

        if ($productCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {

            $data = [
                'department' => DepartmentResource::make($productCategory)->toArray(request()),
            ];
            $data['routeList'] = [
                'collectionRoute' => [
                    'name' => 'grp.org.shops.show.catalogue.departments.show.collection.create',
                    'parameters' => [
                        'organisation' => $productCategory->organisation->slug,
                        'shop'         => $productCategory->shop->slug,
                        'department'   => $productCategory->slug,
                    ],
                    'method' => 'get'
                ],
                'collections_route' =>  [
                    'name'       => 'grp.json.shop.catalogue.collections',
                    'parameters' => [
                        'shop'         => $productCategory->shop->slug,
                        'scope'   => $productCategory->shop->slug
                    ],
                    'method' => 'get'
                ]
            ];
        } elseif ($productCategory->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            $data = [
                'subDepartment' => SubDepartmentResource::make($productCategory)->toArray(request()),
                'families'   => FamilyResource::collection($productCategory->getFamilies())->toArray(request()),
            ];
            $data['routeList'] = [
                'collectionRoute' => [
                    'name' => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.create',
                    'parameters' => [
                        'organisation' => $productCategory->organisation->slug,
                        'shop'         => $productCategory->shop?->slug,
                        'department'   => $productCategory->department->slug,
                        'subDepartment' => $productCategory->slug,
                    ],
                    'method' => 'get'
                ],
                'moveProductRoute' => [
                    'name' => 'grp.models.product.move_family',
                    'parameters' => [
                        //add product id
                    ],
                    'method' => 'patch'
                ],
                'collections_route' =>  [
                    'name'       => 'grp.json.shop.catalogue.collections',
                    'parameters' => [
                        'shop'         => $productCategory->shop->slug,
                        'scope'   => $productCategory->shop->slug
                    ],
                    'method' => 'get'
                ]
            ];
        } else {
            $data = [
                'family' => FamilyResource::make($productCategory),
                'departments' => $productCategory->shop->productCategories()
                    ->where('type', ProductCategoryTypeEnum::DEPARTMENT)
                    ->get(['id', 'name'])
                    ->toArray(),
            ];
            if ($routeName == 'grp.org.shops.show.catalogue.families.show') {
                $data['routeList'] = [
                    'collectionRoute' => [
                        'name' => 'grp.org.shops.show.catalogue.families.show.collection.create',
                        'parameters' => [
                            'organisation' => $productCategory->organisation->slug,
                            'shop'         => $productCategory->shop?->slug,
                            'family' => $productCategory->slug,
                        ],
                        'method' => 'get'
                    ],
                    'collections_route' =>  [
                        'name'       => 'grp.json.shop.catalogue.collections',
                        'parameters' => [
                            'shop'         => $productCategory->shop->slug,
                            'scope'   => $productCategory->shop->slug
                        ],
                        'method' => 'get'
                    ]
                ];
            } elseif ($routeName == 'grp.org.shops.show.catalogue.departments.show.families.show') {
                $data['routeList'] = [
                    'collectionRoute' => [
                        'name' => 'grp.org.shops.show.catalogue.departments.show.families.show.collection.create',
                        'parameters' => [
                            'organisation' => $productCategory->organisation->slug,
                            'shop'         => $productCategory->shop->slug,
                            'department'   => $productCategory->department->slug,
                            'family'       => $productCategory->slug,
                        ],
                        'method' => 'get'
                    ],
                    'collections_route' =>  [
                        'name'       => 'grp.json.shop.catalogue.collections',
                        'parameters' => [
                            'shop'         => $productCategory->shop->slug,
                            'scope'   => $productCategory->shop->slug
                        ],
                        'method' => 'get'
                    ]
                ];
            } elseif ($routeName == 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show') {
                $data['routeList'] = [
                    'collectionRoute' => [
                        'name' => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show.collection.create',
                        'parameters' => [
                            'organisation' => $productCategory->organisation->slug,
                            'shop'         => $productCategory->shop->slug,
                            'department'   => $productCategory->parent->parent->slug,
                            'subDepartment'   => $productCategory->parent->slug,
                            'family'       => $productCategory->slug,
                        ],
                        'method' => 'get'
                    ],
                    'collections_route' =>  [
                        'name'       => 'grp.json.shop.catalogue.collections',
                        'parameters' => [
                            'shop'         => $productCategory->shop->slug,
                            'scope'   => $productCategory->shop->slug
                        ],
                        'method' => 'get'
                    ]

                ];
            } elseif ($routeName == 'grp.org.shops.show.catalogue.departments.show.sub_departments.show') {
                $data['routeList'] = [
                    'collectionRoute' => [
                        'name' => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.families.show.collection.create',
                        'parameters' => [
                            'organisation' => $productCategory->organisation->slug,
                            'shop'         => $productCategory->shop?->slug,
                            'department'   => $productCategory->department->slug,
                            'subDepartment' => $productCategory->subDepartment?->slug,
                            'family'       => $productCategory->slug,
                        ],
                        'method' => 'get'
                    ],
                    'collections_route' =>  [
                        'name'       => 'grp.json.shop.catalogue.collections.in-product-category',
                        'parameters' => [
                            'shop'         => $productCategory->shop->slug,
                            'scope'   => $productCategory->slug
                        ],
                        'method' => 'get'
                    ]
                ];
            }
        }

        $data['routes'] = [
            'detach_family' => [
                'name'       => 'grp.models.sub-department.family.detach',
                'parameters' => [
                    'subDepartment' => $productCategory->slug,
                ],
                'method'     => 'delete'
            ],
        ];



        $data['has_webpage'] = (bool)$productCategory->webpage;

        return $data;
    }
}
