<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 18 Apr 2025 00:10:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers;

use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Portfolio;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectPortfolioItemLink extends OrgAction
{
    public function handle(Portfolio $portfolio): ?RedirectResponse
    {
        /** @var Product|StoredItem $product */
        $product = $portfolio->item;

        if ($product instanceof Product) {
            return Redirect::route(
                'grp.org.shops.show.catalogue.products.all_products.show',
                [
                    $product->organisation->slug,
                    $product->shop->slug,
                    $product->slug,
                ]
            );
        }

        return Redirect::route(
            'grp.org.warehouses.show.inventory.stored_items.current.show',
            [
                $product->organisation->slug,
                $product->warehouse->slug,
                $product->slug,
            ]
        );
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($portfolio->shop, $request);

        return $this->handle($portfolio);
    }

}
