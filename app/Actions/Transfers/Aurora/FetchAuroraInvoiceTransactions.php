<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 19 Oct 2022 19:51:56 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransaction;
use App\Actions\Accounting\InvoiceTransaction\UpdateInvoiceTransaction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Transfers\SourceOrganisationService;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchAuroraInvoiceTransactions
{
    use AsAction;

    public function handle(SourceOrganisationService $organisationSource, int $source_id, Invoice $invoice): ?InvoiceTransaction
    {
        $transactionData = $organisationSource->fetchInvoiceTransaction(
            id: $source_id,
            invoice: $invoice,
            isFulfilment: $invoice->shop->type === ShopTypeEnum::FULFILMENT
        );
        if (! $transactionData) {
            return null;
        }

        if ($invoiceTransaction = InvoiceTransaction::where('source_id', $transactionData['transaction']['source_id'])->first()) {
            $invoiceTransaction = UpdateInvoiceTransaction::make()->action(
                invoiceTransaction: $invoiceTransaction,
                modelData: $transactionData['transaction'],
                strict: false
            );
        } else {
            $invoiceTransaction = StoreInvoiceTransaction::make()->action(
                invoice: $invoice,
                model: $transactionData['model'],
                modelData: $transactionData['transaction'],
                hydratorsDelay: 10,
                strict: false
            );

            $sourceData = explode(':', $invoiceTransaction->source_id);
            DB::connection('aurora')->table('Order Transaction Fact')
                ->where('Order Transaction Fact Key', $sourceData[1])
                ->update(['aiku_invoice_id' => $invoiceTransaction->id]);
        }

        return $invoiceTransaction;
    }
}
