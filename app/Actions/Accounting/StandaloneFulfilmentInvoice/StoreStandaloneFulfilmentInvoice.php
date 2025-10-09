<?php

/*
 * author Arya Permana - Kirin
 * created on 27-02-2025-08h-24m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\StandaloneFulfilmentInvoice;

use App\Actions\Accounting\Invoice\StoreInvoice;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithOrderExchanges;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Fulfilment\FulfilmentCustomer;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreStandaloneFulfilmentInvoice extends OrgAction
{
    use WithFixedAddressActions;
    use WithOrderExchanges;
    use WithNoStrictRules;

    /**
     * @throws \Throwable
     */
    public function handle(FulfilmentCustomer $fulfilmentCustomer): Invoice
    {
        $invoiceData = [
            'currency_id'  => $this->fulfilment->shop->currency_id,
            'type'         => InvoiceTypeEnum::INVOICE,
            'net_amount'   => 0,
            'total_amount' => 0,
            'gross_amount' => 0,
            'tax_amount'   => 0,
            'in_process'   => true,
        ];

        return StoreInvoice::make()->action($fulfilmentCustomer->customer, $invoiceData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): Invoice
    {
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, $request);

        return $this->handle($fulfilmentCustomer);
    }

    /**
     * @throws \Throwable
     */
    public function action(FulfilmentCustomer $fulfilmentCustomer, $modelData, int $hydratorsDelay = 0, bool $strict = true): Invoice
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, $modelData);

        return $this->handle($fulfilmentCustomer);
    }


    public function htmlResponse(Invoice $invoice): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        return Redirect::route('grp.org.fulfilments.show.crm.customers.show.invoices.in-process.show', [
            'organisation'       => $invoice->organisation->slug,
            'fulfilment'         => $invoice->shop->fulfilment->slug,
            'fulfilmentCustomer' => $invoice->customer->fulfilmentCustomer->slug,
            'invoice'            => $invoice->slug
        ]);
    }
}
