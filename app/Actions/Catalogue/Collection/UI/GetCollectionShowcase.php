<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 May 2023 20:59:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection\UI;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\DepartmentResource;
use App\Http\Resources\Catalogue\SubDepartmentResource;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\Concerns\AsObject;

class GetCollectionShowcase
{
    use AsObject;

    public function handle(Collection $collection): array
    {
        $parentRoute = null;

        if ($collection->parent) {
            if ($collection->parent instanceof ProductCategory) {
                if ($collection->parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
                    $parentRoute = [
                        'name' => 'grp.org.shops.show.catalogue.departments.show',
                        'parameters' => [
                            'organisation' => $collection->parent->shop->organisation->slug,
                            'shop'         => $collection->parent->shop->slug,
                            'department'   => $collection->parent->slug
                        ],
                        'method' => 'get'
                    ];
                } elseif ($collection->parent->type == ProductCategoryTypeEnum::FAMILY) {
                    $parentRoute = [
                        'name' => 'grp.org.shops.show.catalogue.families.show',
                        'parameters' => [
                            'organisation' => $collection->parent->shop->organisation->slug,
                            'shop'         => $collection->parent->shop->slug,
                            'family'       => $collection->parent->slug
                        ],
                        'method' => 'get'
                    ];
                } elseif ($collection->parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                    $parentRoute = [
                        'name' => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show',
                        'parameters' => [
                            'organisation' => $collection->parent->shop->organisation->slug,
                            'shop'         => $collection->parent->shop->slug,
                            'department'      => $collection->parent->department->slug,
                            'subDepartment'  => $collection->parent->slug
                        ],
                        'method' => 'get'
                    ];
                }
            }
        }

        return [
            'parent'      => $collection->parent ? [
                'id'   => $collection->parent->id,
                'name' => $collection->parent->name,
                'slug' => $collection->parent->slug,
                'route' => $parentRoute
            ] : [],
            'image'           => $collection->imageSources(720, 480),
            'description' => $collection->description,
            'name'        => $collection->name,
             'id'        => $collection->id,
             'slug'        => $collection->slug,
            'stats'       => [
                [
                    'label' => __('Department'),
                    'icon'  => 'fal fa-folder-tree',
                    'value' => $collection->stats->number_departments,
                    'meta'  => [
                        'value' => '+4',
                        'label' => __('from last month'),
                    ]
                ],
                [
                    'label' => __('Families'),
                    'icon'  => 'fal fa-folder',
                    'value' => $collection->stats->number_families,
                    'meta'  => [
                        'value' => '+4',
                        'label' => __('from last month'),
                    ]
                ],
                [
                    'label' => __('Products'),
                    'icon'  => 'fal fa-cube',
                    'value' => $collection->stats->number_products,
                    'meta'  => [
                        'value' => '+4',
                        'label' => __('from last month'),
                    ]
                ],
                [
                    'label' => __('Collections'),
                    'icon'  => 'fal fa-cube',
                    'value' => $collection->stats->number_collections,
                    'meta'  => [
                        'value' => '+4',
                        'label' => __('from last month'),
                    ]
                ],
            ],
            'parent_departments' => DepartmentResource::collection($collection->departments)->toArray(request()),
            'parent_subdepartments' => SubDepartmentResource::collection($collection->subdepartments)->toArray(request()),

            'routes' => [
                'departments_route' => [
                    'name'  => 'grp.json.shop.catalogue.departments',
                    'parameters' => [
                        'shop' => $collection->shop->slug,
                        'scope' => $collection->slug,
                    ],
                ],
                'sub_departments_route' => [
                    'name'  => 'grp.json.shop.catalogue.sub-departments',
                    'parameters' => [
                        'shop' => $collection->shop->slug,
                        'scope' => $collection->slug,
                    ],
                ],
                'attach_parent' => [
                    'name'       => 'grp.models.product_category.collection.attach',
                    'parameters' => [
                        'collection' => $collection->id,
                    ],
                    'method'     => 'post'
                ],
                'detach_parent' => [
                    'name'       => 'grp.models.product_category.collection.detach',
                    'parameters' => [
                        'collection' => $collection->id,
                    ],
                    'method'     => 'delete'
                ],
            ],
        ];
    }
}
