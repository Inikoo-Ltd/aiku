<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Sept 2025 02:58:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectInvoicesInShopFromDashboard extends OrgAction
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
            'grp.org.fulfilments.show.operations.invoices.invoices.index',
            [
                $shop->organisation->slug,
                $shop->fulfilment->slug,
                'between[date]' => $this->get('between', ''),
            ]
        );
    }

    private function getShopRedirects(Shop $shop): RedirectResponse
    {
        return Redirect::route(
            'grp.org.shops.show.dashboard.invoices.index',
            [
                $shop->organisation->slug,
                $shop->slug,
                'between[date]' => $this->get('between', ''),
            ]
        );
    }

    public function asController(Shop $shop, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

}
