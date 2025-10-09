<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Sept 2025 12:07:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\CRM;

use App\Actions\CRM\BackInStockReminder\DeleteBackInStockReminder;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use Lorisleiva\Actions\ActionRequest;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Product;

class DeleteIrisBackInStockReminder extends RetinaAction
{
    use WithActionUpdate;


    public function handle(Customer $customer, Product $product): void
    {
        $backInStockReminder = $customer->backInStockReminder()->where('product_id', $product->id)->first();
        if ($backInStockReminder) {
            DeleteBackInStockReminder::make()->action($backInStockReminder);
        }
    }


    public function asController(Product $product, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($this->customer, $product);
    }
}
