<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 May 2023 20:59:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection\UI;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\Concerns\AsObject;

class GetCollectionShowcase
{
    use AsObject;

    public function handle(Collection $collection): array
    {
        // dd($collection);
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
            'routes' => [
                'attach_webpage' => [
                    'name'       => 'grp.models.collection.attach_webpages',
                    'parameters' => [ $collection->id ],
                    'method'     => 'post'
                ],
                'detach_webpage' => [
                    'name'       => 'grp.models.collection.detach_webpage',
                    'parameters' => [],
                    'method'     => 'delete'
                ],
            ],
        ];
    }
}
