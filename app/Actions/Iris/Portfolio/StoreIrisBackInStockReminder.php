<?php

/*
 * author Arya Permana - Kirin
 * created on 08-05-2025-16h-49m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Iris\Portfolio;

use App\Actions\CRM\BackInStockReminder\StoreBackInStockReminder;
use App\Actions\IrisAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Portfolio;
use Lorisleiva\Actions\ActionRequest;

class StoreIrisBackInStockReminder extends IrisAction
{
    use WithActionUpdate;

    private Portfolio $portfolio;

    public function handle(Customer $customer, Product $product, array $modelData): void
    {
        StoreBackInStockReminder::make()->action($customer, $product, $modelData);
    }

    public function asController(Customer $customer, Product $product, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($customer, $product, $this->validatedData);
    }
}
