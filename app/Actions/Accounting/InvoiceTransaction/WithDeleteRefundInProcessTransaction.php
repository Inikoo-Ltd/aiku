<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 17 Mar 2025 16:09:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\InvoiceTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

trait WithDeleteRefundInProcessTransaction
{
    public function afterValidator(Validator $validator, ActionRequest $request): void
    {

        if ($this->asAction) {
            return;
        }

        $invoiceTransaction = $request->route()->parameter('invoiceTransaction');

        if ($invoiceTransaction->invoice->type != InvoiceTypeEnum::REFUND) {
            $validator->errors()->add('invoiceTransaction', 'Transaction is not a refund');
        }

        if (!$invoiceTransaction->invoice->in_process) {
            $validator->errors()->add('invoiceTransaction', 'Refund is not in process');
        }
    }


    public function asController(InvoiceTransaction $invoiceTransaction, ActionRequest $actionRequest): void
    {
        $this->initialisationFromShop($invoiceTransaction->shop, $actionRequest);

        $this->handle($invoiceTransaction);
    }


    public function action(InvoiceTransaction $invoiceTransaction): void
    {
        $this->asAction = true;
        $this->initialisationFromShop($invoiceTransaction->shop, []);


        $this->handle($invoiceTransaction);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

}
