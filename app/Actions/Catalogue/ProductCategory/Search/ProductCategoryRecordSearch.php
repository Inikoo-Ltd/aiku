<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 13 Nov 2024 11:58:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\Search;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductCategoryRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(ProductCategory $productCategory): void
    {

        if ($productCategory->trashed()) {
            $productCategory->universalSearch()->delete();

            return;
        }

        $productCategory->universalSearch()->updateOrCreate(
            [],
            [
                'group_id'          => $productCategory->group_id,
                'organisation_id'   => $productCategory->organisation_id,
                'organisation_slug' => $productCategory->organisation->slug,
                'shop_id'           => $productCategory->shop_id,
                'shop_slug'         => $productCategory->shop->slug,
                'sections'          => ['catalogue'],
                'haystack_tier_1'   => $productCategory->code,
                'haystack_tier_2'   => $productCategory->name,
                'result'            => [
                    'route'         => match($productCategory->type) {
                        ProductCategoryTypeEnum::DEPARTMENT => [
                            'name'          => 'grp.org.shops.show.catalogue.departments.show',
                            'parameters'    => [
                                $productCategory->organisation->slug,
                                $productCategory->shop->slug,
                                $productCategory->slug,
                            ]
                        ],
                        ProductCategoryTypeEnum::SUB_DEPARTMENT => [
                            'name'          => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show',
                            'parameters'    => [
                                $productCategory->organisation->slug,
                                $productCategory->shop->slug,
                                $productCategory->department->slug,
                                $productCategory->slug,
                            ]
                        ],
                        ProductCategoryTypeEnum::FAMILY => [
                            'name'          => 'grp.org.shops.show.catalogue.families.show',
                            'parameters'    => [
                                $productCategory->organisation->slug,
                                $productCategory->shop->slug,
                                $productCategory->slug,
                            ]
                        ],
                        default => null,
                    },
                    'code' => [
                        'label' => $productCategory->code,
                    ],

                    'description' => [
                        'label' => $productCategory->name,
                    ],
                    'meta'       => match ($productCategory->type) {
                        ProductCategoryTypeEnum::DEPARTMENT, ProductCategoryTypeEnum::SUB_DEPARTMENT => [
                            [
                                'type' => 'number',
                                'label' => __('Families') . ": ",
                                'icon'  => [
                                    'icon' => 'fal fa-folder'
                                ],
                                'number' => $productCategory->stats->number_current_families,
                            ],
                            [
                                'type' => 'number',
                                'label' => __('Products') . ": ",
                                'icon'  => [
                                    'icon' => 'fal fa-cube'
                                ],
                                'number' => $productCategory->stats->number_current_products
                            ]
                        ],
                        ProductCategoryTypeEnum::FAMILY => [
                            [
                                'type' => 'number',
                                'label' => __('Products') . ": ",
                                'icon'  => [
                                    'icon' => 'fal fa-cube'
                                ],
                                'number' => $productCategory->stats->number_current_products
                            ]
                        ]
                    },
                    'icon'        => match ($productCategory->type) {
                        ProductCategoryTypeEnum::FAMILY => [
                            'icon' => 'fal fa-folder',
                        ],
                        default => [
                            'icon' => 'fal fa-folder-tree',
                        ],
                    },
                    'state_icon' => $productCategory->state->stateIcon()[$productCategory->state->value]


                ]
            ]
        );
    }

}
