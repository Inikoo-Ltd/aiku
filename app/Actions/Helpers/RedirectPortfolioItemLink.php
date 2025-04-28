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
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectPortfolioItemLink extends OrgAction
{
    public function handle(Portfolio $portfolio): ?RedirectResponse
    {
        /** @var Product $product */
        $product = $portfolio->item;

        return Redirect::route(
            'grp.org.fulfilments.show.operations.invoices.invoices.index',
            [
                $product->organisation->slug,
                $product->shop->slug,
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
