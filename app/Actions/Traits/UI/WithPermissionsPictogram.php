<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 May 2025 22:54:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\UI;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\Api\Dropshipping\ShopResource;
use App\Http\Resources\HumanResources\JobPositionResource;
use App\Http\Resources\Inventory\WarehouseResource;
use App\Http\Resources\SysAdmin\Organisation\OrganisationsResource;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;

trait WithPermissionsPictogram
{
    public function getPermissionsPictogram(User $user, $permissionsGroupData, $jobPositionsOrganisationsData): array
    {
        return [
            'organisation_list'    => OrganisationsResource::collection($user->group->organisations),
            "current_organisation" => $user->getOrganisation(),
            'options'              => Organisation::get()->flatMap(function (Organisation $organisation) {
                return [
                    $organisation->slug => [
                        'positions'   => JobPositionResource::collection($organisation->jobPositions),
                        'shops'       => \App\Http\Resources\Catalogue\ShopResource::collection($organisation->shops()->where('type', '!=', ShopTypeEnum::FULFILMENT)->get()),
                        'fulfilments' => ShopResource::collection($organisation->shops()->where('type', '=', ShopTypeEnum::FULFILMENT)->get()),
                        'warehouses'  => WarehouseResource::collection($organisation->warehouses),
                    ]
                ];
            })->toArray(),
            'group'                => $permissionsGroupData,
            'organisations'        => $jobPositionsOrganisationsData
        ];
    }
}
