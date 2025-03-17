<?php

/*
 * author Arya Permana - Kirin
 * created on 17-03-2025-17h-01m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\Accounting\Invoice\WithRunInvoiceHydrators;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use Lorisleiva\Actions\ActionRequest;

class FinaliseRefund extends OrgAction
{
    use WithActionUpdate;
    use WithRunInvoiceHydrators;

    public function handle(Invoice $refund): Invoice
    {
        $refund->invoiceTransactions()->update(
            [
                'in_process' => false
            ]
        );

        $refund->update(
            [
                'in_process' => false
            ]
        );

        $this->runInvoiceHydrators($refund);


        return $refund;
    }

    public function asController(Invoice $refund, ActionRequest $request): Invoice
    {
        $this->initialisationFromShop($refund->shop, $request);

        return $this->handle($refund);
    }
}
