<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 05 May 2026 00:59:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectProductLink extends OrgAction
{
    public function handle(Product $product): ?RedirectResponse
    {
        $organisation = $product->organisation;
        $shop         = $product->shop;


        $route = [
            'name'       => 'grp.org.shops.show.catalogue.products.all_products.show',
            'parameters' => [
                'organisation' => $organisation->slug,
                'shop'         => $shop->slug,
                'product'      => $product->slug
            ]
        ];

        return Redirect::route($route['name'], $route['parameters']);
    }


    public function asController(Product $product, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($product->shop, $request);

        return $this->handle($product);
    }

}
