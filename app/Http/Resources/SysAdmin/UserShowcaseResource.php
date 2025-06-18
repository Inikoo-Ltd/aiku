<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 07 Sept 2022 21:56:20 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SysAdmin;

use App\Actions\Utils\GetLocationFromIp;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use App\Http\Resources\HumanResources\JobPositionResource;
use App\Http\Resources\Api\Dropshipping\ShopResource;
use App\Http\Resources\Inventory\WarehouseResource;
use App\Actions\SysAdmin\User\GetUserGroupScopeJobPositionsData;
use App\Actions\SysAdmin\User\GetUserOrganisationScopeJobPositionsData;
use App\Http\Resources\SysAdmin\Organisation\OrganisationsResource;
use App\Models\SysAdmin\Organisation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;

class UserShowcaseResource extends JsonResource
{
    public function toArray($request): array|Arrayable|JsonSerializable
    {
        /** @var User $user */
        $user = $this;

        $jobPositionsOrganisationsData = [];
        foreach ($user->group->organisations as $organisation) {
            $jobPositionsOrganisationData                       = GetUserOrganisationScopeJobPositionsData::run($user->resource, $organisation);
            $jobPositionsOrganisationsData[$organisation->slug] = $jobPositionsOrganisationData;
        }



        $permissionsGroupData = GetUserGroupScopeJobPositionsData::run($user->resource);

        return [
            'id'                      => $user->id,
            'username'                => $user->username,
            'avatar'                  => $user->imageSources(48, 48),
            'email'                   => $user->email,
            'about'                   => $user->about,
            'contact_name'            => $user->contact_name,
            'access_token'            => $user->tokens->last,
            'authorizedOrganisations' => $user->authorisedOrganisations->map(fn ($organisation) => [
                'slug' => $organisation->slug,
                'name' => $organisation->name,
                'type' => $organisation->type,
            ]),
            'permissions_pictogram' => [
                'organisation_list' => ['data' => OrganisationsResource::collection($user->group->organisations)],
                "current_organisation"  => $user->getOrganisation(),
                'options'           => Organisation::get()->flatMap(function (Organisation $organisation) {
                    return [
                        $organisation->slug => [
                            'positions'   => ['data' => JobPositionResource::collection($organisation->jobPositions)],
                            'shops'       => ['data' => \App\Http\Resources\Catalogue\ShopResource::collection($organisation->shops()->where('type', '!=', ShopTypeEnum::FULFILMENT)->get())],
                            'fulfilments' => ['data' => ShopResource::collection($organisation->shops()->where('type', '=', ShopTypeEnum::FULFILMENT)->get())],
                            'warehouses'  => ['data' => WarehouseResource::collection($organisation->warehouses)],
                        ]
                    ];
                })->toArray(),
                'group' => $permissionsGroupData,
                'organisations' => $jobPositionsOrganisationsData
            ],
         /*    'permissions'             => $user->getAllPermissions()->pluck('name')->toArray(), */
            'last_active_at'          => $user->stats->last_active_at,
            'last_login'              => [
                'ip'          => $user->stats->last_login_ip,
                'geolocation' => GetLocationFromIp::run($user->stats->last_login_ip)
            ]
        ];
    }
}
