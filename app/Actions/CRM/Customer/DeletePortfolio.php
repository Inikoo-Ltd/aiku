<?php

/*
 * Author: Arya Permana <aryapermana02@gmail.com>
 * Created: Fri, 14 Jun 2024 09:33:25 Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\OrgAction;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeletePortfolio extends OrgAction
{
    use AsController;
    use WithAttributes;


    public function handle(Portfolio $portfolio): Portfolio
    {
        if ($portfolio->stats()->exists()) {
            $portfolio->stats()->delete();
        }

        if ($portfolio->shopifyPortfolio()->exists()) {
            $portfolio->shopifyPortfolio()->delete();
        }

        $portfolio->delete();

        return $portfolio;
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): Portfolio
    {
        $this->initialisationFromShop($portfolio->shop, $request);

        return $this->handle($portfolio);
    }



    public function htmlResponse(Portfolio $portfolio): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.crm.customers.show.portfolios.index', [$portfolio->organisation->slug, $portfolio->shop->slug, $portfolio->customer->slug]);
    }
}
