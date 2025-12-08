<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 02 Jan 2024 13:21:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SysAdmin\Organisation;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\HasSelfCall;
use App\Http\Resources\UI\FulfilmentsNavigationResource;
use App\Http\Resources\UI\ProductionsNavigationResource;
use App\Http\Resources\UI\ShopsNavigationResource;
use App\Http\Resources\UI\WarehousesNavigationResource;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class UserOrganisationResource extends JsonResource
{
    use HasSelfCall;

    private static User $user;

    public function toArray($request): array
    {
        /** @var Organisation $organisation */
        $organisation = $this;

        $user = self::$user;

        $authorisedShops = $user->authorisedShops()->where('organisation_id', $organisation->id)->where('shops.type', '!=', ShopTypeEnum::FULFILMENT)->get();

        return [
            'id' => $organisation->id,
            'slug' => $organisation->slug,
            'code' => $organisation->code,
            'label' => $organisation->name,
            'type' => $organisation->type,
            'currency' => $organisation->currency,
            'logo' => $organisation->imageSources(48, 48),
            'route' => [
                'name' => 'grp.org.dashboard.show',
                'parameters' => [
                    $organisation->slug,
                ],
            ],
            'authorised_shops' => ShopsNavigationResource::collection($authorisedShops),
            'authorised_fulfilments' => FulfilmentsNavigationResource::collection(
                $user->authorisedFulfilments()->where('organisation_id', $organisation->id)->get()
            ),
            'authorised_warehouses' => WarehousesNavigationResource::collection(
                $user->authorisedWarehouses()->where('organisation_id', $organisation->id)->get()
            ),
            'authorised_productions' => ProductionsNavigationResource::collection(
                $user->authorisedProductions()->where('organisation_id', $organisation->id)->get()
            ),
        ];
    }

    public static function collectionForUser(
        $resource,
        User $user
    ): AnonymousResourceCollection {
        // you can add as many params as you want.
        self::$user = $user;

        return parent::collection($resource);
    }
}
