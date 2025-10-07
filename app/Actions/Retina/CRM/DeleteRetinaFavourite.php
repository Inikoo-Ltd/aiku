<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Oct 2024 11:16:11 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\CRM;

use App\Actions\CRM\Favourite\UnFavourite;
use App\Actions\RetinaAction;
use App\Models\CRM\Favourite;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;

class DeleteRetinaFavourite extends RetinaAction
{
    use WithActionUpdate;

    public function handle(Customer $customer, Product $product): void
    {
     /** @var Favourite $favourite */
        $favourite = $customer->favourites()->where('product_id', $product->id)->first();
        if ($favourite) {
            UnFavourite::make()->action($favourite, []);
        }
    }

    public function asController(Product $product, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($this->customer, $product);
    }

}
