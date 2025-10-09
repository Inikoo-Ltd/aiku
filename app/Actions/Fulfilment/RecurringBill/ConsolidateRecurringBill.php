<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 16:53:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\RecurringBill;

use App\Actions\Accounting\Invoice\InvoiceRecurringBillTransactions;
use App\Actions\Accounting\Invoice\StoreInvoice;
use App\Actions\Fulfilment\RecurringBillTransaction\UpdateRecurringBillTransaction;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Fulfilment\RecurringBill\RecurringBillStatusEnum;
use App\Models\Accounting\Invoice;
use App\Models\Fulfilment\RecurringBill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class ConsolidateRecurringBill extends OrgAction
{
    use WithActionUpdate;


    /**
     * @throws \Throwable
     */
    public function handle(RecurringBill $recurringBill): Invoice
    {
        return DB::transaction(function () use ($recurringBill) {
            $recurringBill = $this->update($recurringBill, [
                'status'   => RecurringBillStatusEnum::FORMER,
                'end_date' => now()
            ]);


            $invoiceData = [
                'currency_id'     => $recurringBill->currency_id,
                'type'            => InvoiceTypeEnum::INVOICE,
                'net_amount'      => $recurringBill->net_amount,
                'total_amount'    => $recurringBill->total_amount,
                'gross_amount'    => $recurringBill->gross_amount,
                'rental_amount'   => $recurringBill->rental_amount,
                'goods_amount'    => $recurringBill->goods_amount,
                'services_amount' => $recurringBill->services_amount,
                'tax_amount'      => $recurringBill->tax_amount
            ];

            $invoice = StoreInvoice::make()->action($recurringBill, $invoiceData);

            if ($recurringBill->stats->number_transactions < 50 || $this->asAction) {
                InvoiceRecurringBillTransactions::run($invoice, $recurringBill);
            } else {
                InvoiceRecurringBillTransactions::dispatch($invoice, $recurringBill);
            }

            foreach ($recurringBill->transactions as $transaction) {
                if (!$transaction->end_date) {
                    UpdateRecurringBillTransaction::make()->action($transaction, ['end_date' => now()]);
                }
            }

            $this->update($recurringBill->fulfilmentCustomer, [
                'current_recurring_bill_id'  => null,
                'previous_recurring_bill_id' => $recurringBill->id
            ]);

            CreateNextRecurringBillPostConsolidation::run($recurringBill);

            return $invoice;
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    public function htmlResponse(Invoice $invoice, ActionRequest $request): RedirectResponse
    {
        return Redirect::route(
            'grp.org.fulfilments.show.operations.invoices.show',
            [
                $invoice->organisation->slug,
                $invoice->shop->fulfilment->slug,
                $invoice->slug
            ]
        );
    }


    /**
     * @throws \Throwable
     */
    public function asController(RecurringBill $recurringBill, ActionRequest $request): Invoice
    {
        $this->initialisationFromFulfilment($recurringBill->fulfilment, $request);

        return $this->handle($recurringBill);
    }

    /**
     * @throws \Throwable
     */
    public function action(RecurringBill $recurringBill): Invoice
    {
        $this->asAction = true;
        $this->initialisationFromFulfilment($recurringBill->fulfilment, []);

        return $this->handle($recurringBill);
    }


}
