<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Sept 2025 12:28:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\CRM;

use App\Actions\CRM\BackInStockReminder\StoreBackInStockReminder;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\ActionRequest;

class StoreIrisBackInStockReminder extends RetinaAction
{
    use WithActionUpdate;


    public function handle(Customer $customer, Product $product, array $modelData): void
    {
        StoreBackInStockReminder::make()->action($customer, $product, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        $product = $request->route()->parameter('product');
        if ($product->shop_id !== $this->shop->id) {
            return false;
        }
        return true;
    }

    public function asController(Product $product, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($this->customer, $product, $this->validatedData);
    }
}
