<?php

/*
 * author Arya Permana - Kirin
 * created on 23-06-2025-14h-57m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Shop\Api;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\Api\Dropshipping\ShopResource;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\ActionRequest;

class ShowApiShop extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function handle(Shop $shop): Shop
    {
        return $shop;
    }

    public function jsonResponse(Shop $shop): \Illuminate\Http\Resources\Json\JsonResource|ShopResource
    {
        return ShopResource::make($shop);
    }

    public function asController(Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }
}
