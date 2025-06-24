<?php

/*
 * author Arya Permana - Kirin
 * created on 23-06-2025-14h-57m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Group\Shop;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\Api\Dropshipping\ShopResource;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\ActionRequest;

class ShowApiShop extends OrgAction
{

    public function authorize(ActionRequest $request): bool
    {

        return $request->user()->authTo(
            [
                'org-supervisor.'.$this->organisation->id,
                'shops-view'.$this->organisation->id,
                "crm.{$this->shop->id}.view",
                "accounting.{$this->shop->organisation_id}.view",
                "products.{$this->shop->id}.view"
            ]
        );
    }


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
