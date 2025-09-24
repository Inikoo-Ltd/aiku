<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Sept 2024 15:22:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Actions\OrgAction;
use App\Actions\Traits\WithOrderExchanges;
use App\Actions\Traits\WithStoreNoProductTransaction;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Ordering\ShippingZone;
use Illuminate\Validation\Validator;

class StoreInvoiceTransactionFromShipping extends OrgAction
{
    use WithOrderExchanges;
    use WithStoreNoProductInvoiceTransaction;
    use WithStoreNoProductTransaction;


    private ?ShippingZone $shippingZone;

    public function handle(Invoice $invoice, ?ShippingZone $shippingZone, array $modelData): InvoiceTransaction
    {
        $modelData = $this->prepareShippingTransaction($shippingZone, $modelData);

        return $this->processNoProductInvoiceTransaction($invoice, $modelData);
    }

    public function rules(): array
    {
        return $this->getRules();
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->shippingZone && $this->shippingZone->shop_id != $this->shop->id) {
            $validator->errors()->add('shipping_zone', 'Shipping Zone does not belong to this shop');
        }
    }

    public function action(Invoice $invoice, ?ShippingZone $shippingZone, array $modelData, bool $strict = true): InvoiceTransaction
    {
        $this->asAction     = true;
        $this->strict       = $strict;
        $this->shippingZone = $shippingZone;

        $this->initialisationFromShop($invoice->shop, $modelData);

        return $this->handle($invoice, $shippingZone, $this->validatedData);
    }


}
