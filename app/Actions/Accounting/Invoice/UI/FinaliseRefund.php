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
use App\Enums\UI\Accounting\InvoicesTabsEnum;
use App\Models\Accounting\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
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

    public function htmlResponse(Invoice $refund, ActionRequest $request): RedirectResponse
    {
        return Redirect::route('grp.org.fulfilments.show.crm.customers.show.invoices.index', [
            $refund->organisation->slug,
            $refund->customer->fulfilmentCustomer->fulfilment->slug,
            $refund->customer->fulfilmentCustomer->slug,
            'tab' => InvoicesTabsEnum::REFUNDS->value
        ]);
    }

    public function asController(Invoice $refund, ActionRequest $request): Invoice
    {
        $this->initialisationFromShop($refund->shop, $request);

        return $this->handle($refund);
    }
}
