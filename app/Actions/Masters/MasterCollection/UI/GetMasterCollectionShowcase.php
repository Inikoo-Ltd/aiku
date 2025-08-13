<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 May 2023 20:59:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection\UI;

use App\Models\Masters\MasterCollection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterCollectionShowcase
{
    use AsObject;

    public function handle(MasterCollection $masterCollection): array
    {
        return [
            'id'             => $masterCollection->id,
            'slug'           => $masterCollection->slug,
            'code'           => $masterCollection->code,
            'name'           => $masterCollection->name,
            'description'    => $masterCollection->description,
            'state'          => $masterCollection->state,
            'products_status' => $masterCollection->products_status,
            'data'           => $masterCollection->data,
            'parent_departments' => $masterCollection->departments,
            'parent_subdepartments' => $masterCollection->subDepartments,
            'description_title'     => $masterCollection->description_title,
            'description_extra'     => $masterCollection->description_extra,
            'name_i8n'              => $masterCollection->getTranslations('name_i8n'),
            'description_i8n'       => $masterCollection->getTranslations('description_i8n'),
            'description_title_i8n' => $masterCollection->getTranslations('description_title_i8n'),
            'description_extra_i8n' => $masterCollection->getTranslations('description_extra_i8n'),
            'state_icon'          => $masterCollection->state ? $masterCollection->state->stateIcon()[$masterCollection->state->value] : null,
            'stats'                 => [
                [
                    'label' => __('Department'),
                    'icon'  => 'fal fa-folder-tree',
                    'value' => $masterCollection->stats->number_departments,
                    'meta'  => [
                        'value' => '+4',
                        'label' => __('from last month'),
                    ]
                ],
                [
                    'label' => __('Families'),
                    'icon'  => 'fal fa-folder',
                    'value' => $masterCollection->stats->number_families,
                    'meta'  => [
                        'value' => '+4',
                        'label' => __('from last month'),
                    ]
                ],
                [
                    'label' => __('Products'),
                    'icon'  => 'fal fa-cube',
                    'value' => $masterCollection->stats->number_products,
                    'meta'  => [
                        'value' => '+4',
                        'label' => __('from last month'),
                    ]
                ],
                [
                    'label' => __('masterCollections'),
                    'icon'  => 'fal fa-cube',
                    'value' => $masterCollection->stats->number_masterCollections,
                    'meta'  => [
                        'value' => '+4',
                        'label' => __('from last month'),
                    ]
                ],
            ],
            'routes' => [
                'departments_route'     => [
                    'name'       => 'grp.json.master_shop.master_departments',
                    'parameters' => [
                        'masterShop'  => $masterCollection->masterShop->slug,
                        'scope' => $masterCollection->slug,
                    ],
                ],
                'sub_departments_route' => [
                    'name'       => 'grp.json.master_shop.master_sub_departments',
                    'parameters' => [
                        'masterShop'  => $masterCollection->masterShop->slug,
                        'scope' => $masterCollection->slug,
                    ],
                ],
                'attach_parent'         => [
                    'name'       => 'grp.models.master_collection.attach_parents',
                    'parameters' => [
                        'masterCollection' => $masterCollection->id,
                    ],
                    'method'     => 'post'
                ],
                'detach_parent'         => [
                    'name'       => 'grp.models.master_collection.detach-models',
                    'method'     => 'delete'
                ],
            ],
            'translation_box' => [
                'title' => __('Multi-language Translations'),
                'save_route' => [
                'name'       => 'grp.models.master_collection.translations.update',
                'parameters' => []
                ],
            ],
        ];
    }
}
