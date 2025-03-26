<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Mar 2025 17:26:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Aurora;

use App\Actions\Traits\WithOrganisationSource;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\RecurringBillTransaction;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairFulfilmentInvoiceMissingPalletData
{
    use AsAction;
    use WithOrganisationSource;


    public function handle(Command $command, Invoice $invoice): void
    {
        if ($invoice->shop->type !== ShopTypeEnum::FULFILMENT) {
            return;
        }

        if (!$invoice->recurringBill) {
            return;
        }

        $recurringBillTransactions     = $invoice->recurringBill->transactions()->where('item_type', 'Pallet')->get();
        $freeRecurringBillTransactions = $this->getFreeRecurringBillTransactions($invoice, $recurringBillTransactions);

        $invoiceTransactions     = $invoice->invoiceTransactions()->leftJoin('assets', 'invoice_transactions.asset_id', '=', 'assets.id')->where('assets.type', 'rental')->get();
        $freeInvoiceTransactions = $this->getFreeTransactions($invoice);
        $command->table(
            ['Invoice ID', 'Recurring Bill ID', 'RB Transactions', 'Free RB Transactions', 'Invoice Transactions', 'Free Invoice Transactions'],
            [
                [
                    $invoice->reference,
                    $invoice->recurringBill->reference,
                    $recurringBillTransactions->count(),
                    $freeRecurringBillTransactions->count(),
                    $invoiceTransactions->count(),
                    $freeInvoiceTransactions->count()
                ]
            ]
        );

        foreach ($freeInvoiceTransactions as $invoiceTransaction) {
            $recurringBillTransaction = $this->matchRecurringBillTransaction($invoiceTransaction);
            if ($recurringBillTransaction) {
                $invoiceTransaction->update(['recurring_bill_transaction_id' => $recurringBillTransaction->id]);
                $command->info('Matched Invoice Transaction '.$invoiceTransaction->id.' to Recurring Bill Transaction '.$recurringBillTransaction->id);
            }
        }
    }


    public function matchRecurringBillTransaction(InvoiceTransaction $invoiceTransaction)
    {
        return RecurringBillTransaction::leftJoin('invoice_transactions', 'recurring_bill_transactions.id', '=', 'invoice_transactions.recurring_bill_transaction_id')
            ->select('recurring_bill_transactions.*')
            ->where('recurring_bill_id', $invoiceTransaction->invoice->recurring_bill_id)
            ->where('item_type', 'Pallet')
            ->where('recurring_bill_transactions.asset_id', $invoiceTransaction->asset_id)
            ->where('recurring_bill_transactions.temporal_quantity', $invoiceTransaction->quantity)
            ->whereNull('invoice_transactions.id')
            ->orderBy('recurring_bill_transactions.created_at')
            ->first();
    }

    public function getFreeTransactions($invoice): Collection
    {
        return InvoiceTransaction::leftJoin('assets', 'invoice_transactions.asset_id', '=', 'assets.id')
            ->select('invoice_transactions.*')
            ->where('invoice_id', $invoice->id)
            ->where('assets.type', 'rental')
            ->where('recurring_bill_transaction_id', null)
            ->get();
    }

    public function getFreeRecurringBillTransactions($invoice, $recurringBillTransactions)
    {
        return $recurringBillTransactions->filter(function ($transaction) use ($invoice) {
            return !InvoiceTransaction::withTrashed()
                ->where('invoice_id', $invoice->id)
                ->where('recurring_bill_transaction_id', $transaction->id)->exists();
        });
    }

    public function getCommandSignature(): string
    {
        return 'maintenance:repair_fulfilment_invoice_missing_pallet {invoice_id?}';
    }


    public function asCommand(Command $command): int
    {
        if ($command->argument('invoice_id')) {
            $invoice = Invoice::find($command->argument('invoice_id'));
            $this->handle($command, $invoice);
        } else {
            $fulfilmentShops = Shop::where('type', ShopTypeEnum::FULFILMENT)->pluck('id')->toArray();

            $invoices = Invoice::whereNull('source_id')->whereIn('shop_id', $fulfilmentShops)->get();

            foreach ($invoices as $invoice) {
                $this->handle($command, $invoice);
            }
        }


        return 0;
    }
}
