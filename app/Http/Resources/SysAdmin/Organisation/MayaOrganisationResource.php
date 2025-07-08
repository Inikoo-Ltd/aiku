<?php
/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-12h-57m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\SysAdmin\Organisation;

use App\Http\Resources\Api\Dropshipping\ShopResource;
use App\Http\Resources\Fulfilment\FulfilmentResource;
use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Inventory\WarehouseResource;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\JsonResource;

class MayaOrganisationResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Organisation $organisation */
        $organisation = $this;

        return [
            'id'    => $organisation->id,
            'slug'  => $organisation->slug,
            'name'  => $organisation->name,
            'email' => $organisation->email,
            'logo'  => $organisation->imageSources(48, 48),
            'shops' => ShopResource::collection($organisation->shops)->resolve(),
            'fulfilments' => FulfilmentResource::collection($organisation->fulfilments)->resolve(),
            'warehouses' => WarehouseResource::collection($organisation->warehouses)->resolve()
        ];
    }
}
