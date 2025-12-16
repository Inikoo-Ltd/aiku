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
            'id'                    => $masterCollection->id,
            'slug'                  => $masterCollection->slug,
            'code'                  => $masterCollection->code,
            'name'                  => $masterCollection->name,
            'description'           => $masterCollection->description,
            'state'                 => $masterCollection->state,
            'products_status'       => $masterCollection->products_status,
            'data'                  => $masterCollection->data,
            'parent_departments'    => $masterCollection->parentMasterDepartments,
            'parent_subdepartments' => $masterCollection->parentMasterSubDepartments,
            'image'                 => $masterCollection->imageSources(720, 480),
            'state_icon'            => $masterCollection->state ? $masterCollection->state->stateIcon()[$masterCollection->state->value] : null,
            'can_edit'              => true,
            'routes'                => [
                'departments_route'     => [
                    'name'       => 'grp.json.master_shop.master_departments',
                    'parameters' => [
                        'masterShop' => $masterCollection->masterShop->slug,
                        'scope'      => $masterCollection->slug,
                    ],
                ],
                'sub_departments_route' => [
                    'name'       => 'grp.json.master_shop.master_sub_departments',
                    'parameters' => [
                        'masterShop' => $masterCollection->masterShop->slug,
                        'scope'      => $masterCollection->slug,
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
                    'name'       => 'grp.models.master_product_category.master_collection.detach',
                    'parameters' => [
                        'masterCollection' => $masterCollection->id,
                    ],
                    'method'     => 'delete'
                ],
            ],
        ];
    }
}
