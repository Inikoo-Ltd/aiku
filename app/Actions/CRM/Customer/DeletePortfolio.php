<?php

/*
 * Author: Arya Permana <aryapermana02@gmail.com>
 * Created: Fri, 14 Jun 2024 09:33:25 Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\OrgAction;
use App\Models\Dropshipping\Portfolio;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeletePortfolio extends OrgAction
{
    use AsController;
    use WithAttributes;


    public function handle(Portfolio $portfolio): void
    {
        if ($portfolio->stats()->exists()) {
            $portfolio->stats()->delete();
        }

        if ($portfolio->shopifyPortfolio()->exists()) {
            $portfolio->shopifyPortfolio()->delete();
        }

        $portfolio->delete();
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {
        $this->initialisationFromShop($portfolio->shop, $request);

        $this->handle($portfolio);
    }

    public function action(Portfolio $portfolio): void
    {
        $this->initialisationFromShop($portfolio->shop, []);

        $this->handle($portfolio);
    }
}
