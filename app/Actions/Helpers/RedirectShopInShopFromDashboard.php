<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 22-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectShopInShopFromDashboard extends OrgAction
{
    public function handle(Shop $shop): ?RedirectResponse
    {
        if ($shop->type == ShopTypeEnum::FULFILMENT) {
            return $this->getFulfilmentRedirects($shop);
        } else {
            return $this->getShopRedirects($shop);
        }
    }

    private function getFulfilmentRedirects(Shop $shop): RedirectResponse
    {
        return Redirect::route(
            'grp.org.fulfilments.show.operations.dashboard',
            [
                $shop->organisation->slug,
                $shop->fulfilment->slug,
            ]
        );
    }

    private function getShopRedirects(Shop $shop): RedirectResponse
    {
        return Redirect::route(
            'grp.org.shops.show.dashboard.show',
            [
                $shop->organisation->slug,
                $shop->slug,
            ]
        );
    }

    public function asController(Shop $shop, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

}
