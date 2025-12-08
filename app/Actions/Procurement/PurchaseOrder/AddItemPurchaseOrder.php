<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 05 May 2023 09:23:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class AddItemPurchaseOrder
{
    use AsAction;
    use WithAttributes;

    public function handle(PurchaseOrder $purchaseOrder, array $modelData): PurchaseOrderTransaction
    {
        /** @var PurchaseOrderTransaction $items */
        $items = $purchaseOrder->purchaseOrderTransactions()->create($modelData);

        return $items;
    }

    public function rules(): array
    {
        return [
            'supplier_product_id' => ['required', 'exists:supplier_products,id'],
            'unit_price' => ['required', 'numeric', 'gt:0'],
            'unit_quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function action(PurchaseOrder $purchaseOrder, array $modelData): PurchaseOrderTransaction
    {
        $this->setRawAttributes($modelData);
        $validatedData = $this->validateAttributes();

        return $this->handle($purchaseOrder, $validatedData);
    }
}
