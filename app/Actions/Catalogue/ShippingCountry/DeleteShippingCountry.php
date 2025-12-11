<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Dec 2025 15:41:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ShippingCountry;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateShippingCountries;
use App\Actions\OrgAction;
use App\Models\Ordering\ShippingCountry;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteShippingCountry extends OrgAction
{
    use AsAction;

    public function handle(ShippingCountry $shippingCountry): void
    {
        $shop = $shippingCountry->shop;

        $shippingCountry->delete();

        ShopHydrateShippingCountries::dispatch($shop)->delay($this->hydratorsDelay);
    }

    public function action(ShippingCountry $shippingCountry, int $hydratorsDelay = 0, bool $audit = true): void
    {
        if (!$audit) {
            ShippingCountry::disableAuditing();
        }

        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($shippingCountry->shop, []);

        $this->handle($shippingCountry);
    }
}
