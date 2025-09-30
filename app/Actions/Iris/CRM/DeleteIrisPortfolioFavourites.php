<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Sept 2025 12:13:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\CRM;

use App\Actions\CRM\Favourite\UnFavourite;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\CRM\Favourite;
use Lorisleiva\Actions\ActionRequest;

class DeleteIrisPortfolioFavourites extends RetinaAction
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
