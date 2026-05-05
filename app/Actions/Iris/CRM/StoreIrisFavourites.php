<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Sept 2025 12:28:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\CRM;

use App\Actions\CRM\Favourite\StoreFavourite;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\ActionRequest;

class StoreIrisFavourites extends RetinaAction
{
    use WithActionUpdate;


    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Customer $customer, Product $product): void
    {
        StoreFavourite::make()->action($customer, $product, []);
    }

    public function authorize(ActionRequest $request): bool
    {
        $product = $request->route()->parameter('product');
        if ($product->shop_id !== $this->shop->id) {
            return false;
        }

        return true;
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(Product $product, ActionRequest $request): void
    {
        $this->initialisation($request);
        if ($this->customer) {
            $this->handle($this->customer, $product);
        }
    }
}
