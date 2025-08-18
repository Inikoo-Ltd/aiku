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
use App\Models\Billables\Charge;
use Illuminate\Validation\Validator;

class StoreInvoiceTransactionFromCharge extends OrgAction
{
    use WithOrderExchanges;
    use WithStoreNoProductInvoiceTransaction;
    use WithStoreNoProductTransaction;


    private ?Charge $charge;

    public function handle(Invoice $invoice, ?Charge $charge, array $modelData): InvoiceTransaction
    {
        $modelData = $this->prepareChargeTransaction($charge, $modelData);

        return $this->processNoProductInvoiceTransaction($invoice, $modelData);
    }

    public function rules(): array
    {
        return $this->getRules();
    }


    public function afterValidator(Validator $validator): void
    {
        if ($this->charge && $this->charge->shop_id != $this->shop->id) {
            $validator->errors()->add('charge', 'Charge does not belong to this shop');
        }
    }

    public function action(Invoice $invoice, ?Charge $charge, array $modelData, bool $strict = true): InvoiceTransaction
    {
        $this->asAction = true;
        $this->strict   = $strict;
        $this->charge   = $charge;
        $this->initialisationFromShop($invoice->shop, $modelData);

        return $this->handle($invoice, $charge, $this->validatedData);
    }


}
