<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Oct 2025 10:55:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use Illuminate\Console\Command;

class RepairInvoiceSourceInventoryCategory
{
    use WithActionUpdate;

    protected function handle(Invoice $invoice): void
    {

        $invoiceCategory=$invoice->invoiceCategory;
        if($invoiceCategory){
            $invoice->update(
                [
                    'source_invoice_category_id'=>$invoiceCategory->source_id,
                ]
            );
        }


    }



    public string $commandSignature = 'repair:invoice_source_inventory_category';

    public function asCommand(Command $command): void
    {

        $count = Invoice::whereNotNull('source_id')->count();

        $command->info("pending: $count");

        Invoice::whereNotNull('source_id')->orderBy('date', 'desc')
            ->chunk(1000, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $this->handle($invoice);
                }
            });
    }

}
