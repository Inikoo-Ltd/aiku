<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 21 Sept 2025 18:45:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use Illuminate\Console\Command;

class RepairInvoiceMissingCustomerFields
{
    use WithActionUpdate;

    protected function handle(Invoice $invoice): void
    {
        $customer = $invoice->customer;

        $modelData = [];
        data_set($modelData, 'customer_name', $customer->name, false);
        data_set($modelData, 'customer_contact_name', $customer->contact_name, false);
        data_set($modelData, 'identity_document_type', $customer->identity_document_type, false);
        data_set($modelData, 'identity_document_number', $customer->identity_document_number, false);


        $taxNumber = $customer->taxNumber;
        if ($taxNumber) {
            data_set($modelData, 'tax_number', $taxNumber->number, false);
            data_set($modelData, 'tax_number_status', $taxNumber->status, false);
            data_set($modelData, 'tax_number_valid', $taxNumber->valid, false);
        } else {
            data_set($modelData, 'tax_number', null, false);
            data_set($modelData, 'tax_number_status', 'na', false);
            data_set($modelData, 'tax_number_valid', false, false);
        }

        $invoice->updateQuietly($modelData);

    }

    public string $commandSignature = 'repair:invoice_missing_customer_fields';

    public function asCommand(Command $command): void
    {
        $count = Invoice::whereNull('source_id')->count();

        $command->info("pending: $count");

        Invoice::whereNull('delivery_address_id')
            ->orderBy('date')
            ->chunk(1000, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $this->handle($invoice);
                }
            });
    }

}
