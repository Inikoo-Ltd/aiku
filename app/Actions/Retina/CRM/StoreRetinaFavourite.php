<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Oct 2024 11:16:11 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\CRM;

use App\Actions\CRM\Favourite\StoreFavourite;
use App\Actions\RetinaAction;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\CRM\Favourite;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaFavourite extends RetinaAction
{
    public function handle(Customer $customer, Product $product): Favourite
    {
        return StoreFavourite::make()->action($customer, $product, []);
    }

    public function asController(Product $product, ActionRequest $request): Favourite
    {
        $this->initialisation($request);

        return $this->handle($this->customer, $product);
    }

}
