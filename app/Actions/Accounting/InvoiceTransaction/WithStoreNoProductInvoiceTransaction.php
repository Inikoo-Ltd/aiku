<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Sept 2024 20:25:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;

trait WithStoreNoProductInvoiceTransaction
{
    private function processNoProductInvoiceTransaction(Invoice $invoice, array $modelData): InvoiceTransaction
    {
        data_set($modelData, 'date', now(), overwrite: false);

        $modelData['shop_id']         = $invoice->shop_id;
        $modelData['customer_id']     = $invoice->customer_id;
        $modelData['group_id']        = $invoice->group_id;
        $modelData['organisation_id'] = $invoice->organisation_id;


        $modelData = $this->processExchanges($modelData, $invoice->shop);

        /** @var InvoiceTransaction $invoiceTransaction */
        $invoiceTransaction = $invoice->invoiceTransactions()->create($modelData);

        if ($invoiceTransaction->order_id && $invoiceTransaction->transaction_id) {
            $invoiceTransaction->transaction->update([
                'invoice_id' => $invoice->id
            ]);
        }

        return $invoiceTransaction;
    }

    public function getRules(): array
    {
        $rules = [
            'date'            => ['sometimes', 'required', 'date'],
            'tax_category_id' => ['required', 'exists:tax_categories,id'],
            'quantity'        => ['required', 'numeric', 'min:0'],
            'gross_amount'    => ['required', 'numeric'],
            'net_amount'      => ['required', 'numeric'],
            'org_exchange'    => ['sometimes', 'numeric'],
            'grp_exchange'    => ['sometimes', 'numeric'],
            'order_id'        => ['sometimes', 'nullable', 'integer'],
            'transaction_id'  => ['sometimes', 'nullable', 'integer'],
            'submitted_at'    => ['sometimes', 'required', 'date'],

        ];

        if (!$this->strict) {
            $rules['order_id']       = ['sometimes', 'nullable', 'integer'];
            $rules['transaction_id'] = ['sometimes', 'nullable', 'integer'];
            $rules['source_id']      = ['sometimes', 'string', 'max:255'];
            $rules['source_alt_id']  = ['sometimes', 'string', 'max:255'];
            $rules['fetched_at']     = ['sometimes', 'required', 'date'];
            $rules['created_at']     = ['sometimes', 'required', 'date'];
        }


        return $rules;
    }

}
