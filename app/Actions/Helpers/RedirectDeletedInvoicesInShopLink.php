<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Apr 2025 23:27:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectDeletedInvoicesInShopLink extends OrgAction
{
    public function handle(Shop $shop): ?RedirectResponse
    {
        if ($shop->type == ShopTypeEnum::FULFILMENT) {
            return Redirect::route(
                'grp.org.fulfilments.show.operations.invoices.deleted_invoices.index',
                [
                    $shop->organisation->slug,
                    $shop->fulfilment->slug,
                ]
            );
        } else {
            return Redirect::route(
                'grp.org.shops.show.dashboard.invoices.deleted.index',
                [
                    $shop->organisation->slug,
                    $shop->slug
                ]
            );
        }
    }


    public function asController(Shop $shop, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

}
