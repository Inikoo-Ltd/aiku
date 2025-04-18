<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Apr 2025 23:27:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Asset\AssetTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Asset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectAssetLink extends OrgAction
{
    public function handle(Asset $asset): ?RedirectResponse
    {
        if ($asset->shop->type != ShopTypeEnum::FULFILMENT) {
            return $this->redirectAssetToShop($asset);
        } else {
            return $this->redirectAssetToFulfilment($asset);
        }
    }

    protected function redirectAssetToShop(Asset $asset): ?RedirectResponse
    {
        $organisation = $asset->organisation;
        $shop         = $asset->shop;
        $route        = match ($asset->type) {
            AssetTypeEnum::PRODUCT => [
                'name'       => 'grp.org.shops.show.catalogue.products.all_products.show',
                'parameters' => [
                    'organisation' => $organisation->slug,
                    'shop'         => $shop->slug,
                    'product'      => $asset->product->slug
                ]
            ],
            AssetTypeEnum::CHARGE => [
                'name'       => 'grp.org.shops.show.billables.charges.show',
                'parameters' => [
                    'organisation' => $organisation->slug,
                    'shop'         => $shop->slug,
                    'charge'       => $asset->charge->slug
                ]
            ],
            default => null,
        };

        return Redirect::route($route['name'], $route['parameters']);
    }

    protected function redirectAssetToFulfilment(Asset $asset): RedirectResponse
    {
        $organisation = $asset->organisation;
        $fulfilment   = $asset->shop->fulfilment;

        $route = match ($asset->type) {
            AssetTypeEnum::PRODUCT => [
                'name'       => 'grp.org.fulfilments.show.catalogue.physical_goods.show',
                'parameters' => [
                    'organisation' => $organisation->slug,
                    'fulfilment'   => $fulfilment->slug,
                    'product'      => $asset->product->slug
                ]
            ],
            AssetTypeEnum::RENTAL => [
                'name'       => 'grp.org.fulfilments.show.catalogue.rentals.show',
                'parameters' => [
                    'organisation' => $organisation->slug,
                    'fulfilment'   => $fulfilment->slug,
                    'rental'       => $asset->rental->slug
                ]
            ],
            AssetTypeEnum::SERVICE => [
                'name'       => 'grp.org.fulfilments.show.catalogue.services.show',
                'parameters' => [
                    'organisation' => $organisation->slug,
                    'fulfilment'   => $fulfilment->slug,
                    'service'      => $asset->service->slug
                ]
            ],
            default => null,
        };

        return Redirect::route($route['name'], $route['parameters']);
    }

    public function asController(Asset $asset, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($asset->shop, $request);

        return $this->handle($asset);
    }

}
