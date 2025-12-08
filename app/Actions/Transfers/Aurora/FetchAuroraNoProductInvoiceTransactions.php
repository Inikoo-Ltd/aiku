<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Sept 2024 15:22:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransactionFromAdjustment;
use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransactionFromCharge;
use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransactionFromShipping;
use App\Actions\Accounting\InvoiceTransaction\UpdateInvoiceTransaction;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Billables\Charge;
use App\Models\Billables\ShippingZone;
use App\Transfers\SourceOrganisationService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchAuroraNoProductInvoiceTransactions
{
    use AsAction;

    public function handle(SourceOrganisationService $organisationSource, int $source_id, Invoice $invoice): ?InvoiceTransaction
    {
        if ($invoiceTransactionData = $organisationSource->fetchNoProductInvoiceTransaction(
            id: $source_id,
            invoice: $invoice,
        )) {
            if ($invoiceTransactionData['type'] == 'Adjustment') {
                $invoiceTransaction = $this->processAdjustmentInvoiceTransaction($invoice, $invoiceTransactionData);
            } elseif ($invoiceTransactionData['type'] == 'Shipping') {
                $invoiceTransaction = $this->processShippingInvoiceTransaction($invoice, $invoiceTransactionData);
            } elseif ($invoiceTransactionData['type'] == 'Charges') {
                $invoiceTransaction = $this->processChargeInvoiceTransaction($invoice, $invoiceTransactionData);
            } else {
                dd($invoiceTransactionData['type']);
            }

            return $invoiceTransaction;
        }

        return null;
    }

    public function processAdjustmentInvoiceTransaction(Invoice $invoice, array $invoiceTransactionData): InvoiceTransaction
    {
        if ($invoiceTransaction = InvoiceTransaction::where('source_alt_id', $invoiceTransactionData['transaction']['source_alt_id'])->first()) {
            $invoiceTransaction = UpdateInvoiceTransaction::make()->action(
                invoiceTransaction: $invoiceTransaction,
                modelData: $invoiceTransactionData['transaction'],
                strict: false
            );
        } else {
            $invoiceTransaction = StoreInvoiceTransactionFromAdjustment::make()->action(
                invoice: $invoice,
                adjustment: $invoiceTransactionData['adjustment'],
                modelData: $invoiceTransactionData['transaction'],
                strict: false
            );

            $sourceData = explode(':', $invoiceTransaction->source_alt_id);
            DB::connection('aurora')->table('Order No Product Transaction Fact')
                ->where('Order No Product Transaction Fact Key', $sourceData[1])
                ->update(['aiku_invoice_id' => $invoiceTransaction->id]);
        }

        return $invoiceTransaction;
    }

    public function processShippingInvoiceTransaction(Invoice $invoice, array $invoiceTransactionData): InvoiceTransaction
    {
        /** @var ShippingZone $shippingZone */
        $shippingZone = Arr::get($invoiceTransactionData, 'model');
        if ($invoiceTransaction = InvoiceTransaction::where('source_alt_id', $invoiceTransactionData['transaction']['source_alt_id'])->first()) {
            data_set($invoiceTransactionData, 'transaction.model', 'ShippingZone');
            if ($shippingZone) {
                data_set($invoiceTransactionData, 'transaction.model_id', $shippingZone->id);
                data_set($invoiceTransactionData, 'transaction.asset_id', $shippingZone->id);
                data_set($invoiceTransactionData, 'transaction.historic_asset_id', $shippingZone->current_historic_asset_id);
            } else {
                data_set($invoiceTransactionData, 'transaction.model_id', null);
                data_set($invoiceTransactionData, 'transaction.asset_id', null);
                data_set($invoiceTransactionData, 'transaction.historic_asset_id', null);

            }

            $invoiceTransaction = UpdateInvoiceTransaction::make()->action(
                invoiceTransaction: $invoiceTransaction,
                modelData: $invoiceTransactionData['transaction'],
                strict: false
            );
        } else {
            $invoiceTransaction = StoreInvoiceTransactionFromShipping::make()->action(
                invoice: $invoice,
                shippingZone: $shippingZone,
                modelData: $invoiceTransactionData['transaction'],
                strict: false
            );

            $sourceData = explode(':', $invoiceTransaction->source_alt_id);
            DB::connection('aurora')->table('Order No Product Transaction Fact')
                ->where('Order No Product Transaction Fact Key', $sourceData[1])
                ->update(['aiku_invoice_id' => $invoiceTransaction->id]);
        }

        return $invoiceTransaction;
    }

    public function processChargeInvoiceTransaction(Invoice $invoice, array $invoiceTransactionData): InvoiceTransaction
    {
        /** @var Charge $charge */
        $charge = Arr::get($invoiceTransactionData, 'model');
        if ($invoiceTransaction = InvoiceTransaction::where('source_alt_id', $invoiceTransactionData['transaction']['source_alt_id'])->first()) {
            data_set($invoiceTransactionData, 'transaction.model', 'Charge');
            if ($charge) {
                data_set($invoiceTransactionData, 'transaction.model_id', $charge->id);
                data_set($invoiceTransactionData, 'transaction.asset_id', $charge->id);
                data_set($invoiceTransactionData, 'transaction.historic_asset_id', $charge->current_historic_asset_id);
            }

            $invoiceTransaction = UpdateInvoiceTransaction::make()->action(
                invoiceTransaction: $invoiceTransaction,
                modelData: $invoiceTransactionData['transaction'],
                strict: false
            );
        } else {
            $invoiceTransaction = StoreInvoiceTransactionFromCharge::make()->action(
                invoice: $invoice,
                charge: $charge,
                modelData: $invoiceTransactionData['transaction'],
                strict: false
            );

            $sourceData = explode(':', $invoiceTransaction->source_alt_id);
            DB::connection('aurora')->table('Order No Product Transaction Fact')
                ->where('Order No Product Transaction Fact Key', $sourceData[1])
                ->update(['aiku_invoice_id' => $invoiceTransaction->id]);
        }

        return $invoiceTransaction;
    }
}
