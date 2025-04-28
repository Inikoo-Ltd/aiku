<?php

/*
 * author Arya Permana - Kirin
 * created on 09-04-2025-13h-10m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\Invoice;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

class DeleteInProcessInvoice extends OrgAction
{
    use WithActionUpdate;


    public function handle(Invoice $invoice, array $modelData): Invoice
    {
        try {
            $invoice = DB::transaction(function () use ($invoice, $modelData) {
                $invoice = $this->update($invoice, $modelData);
                $invoice->invoiceTransactions()->delete();
                $invoice->delete();
            });

            StoreDeletedInvoiceHistory::run(invoice: $invoice);
            DeleteInvoice::make()->postDeleteInvoiceHydrators($invoice);
        } catch (Throwable) {
            //
        }

        return $invoice;
    }

    public function rules(): array
    {
        return DeleteInvoice::make()->rules();
    }

    public function asController(Invoice $invoice, ActionRequest $request): void
    {
        $this->set('deleted_by', $request->user()->id);
        $this->initialisationFromShop($invoice->shop, $request);

        $this->handle($invoice, $this->validatedData);
    }


    public function action(Invoice $invoice, array $modelData): void
    {
        $this->initialisationFromShop($invoice->shop, $modelData);
        $this->handle($invoice, $this->validatedData);
    }
}
