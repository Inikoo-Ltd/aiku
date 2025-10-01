<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Oct 2025 10:55:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Accounting\Invoice\CategoriseInvoice;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceCategory;
use Illuminate\Console\Command;

class TestInvoiceInventoryCategory
{
    use WithActionUpdate;

    protected function handle(Invoice $invoice): void
    {

        //$invoice=Invoice::find(275557);
        $aikuInvoiceCategory=CategoriseInvoice::make()->getInvoiceCategory($invoice);
       // dd($aikuInvoiceCategory->slug);

        if($aikuInvoiceCategory->source_id && $aikuInvoiceCategory->source_id!=$invoice->source_invoice_category_id)
        {

            /** @var InvoiceCategory $auroraInvoiceCategory */
            $auroraInvoiceCategory=InvoiceCategory::where('source_id',$invoice->source_invoice_category_id)->first();

            if($auroraInvoiceCategory->slug=='agnescat-faire' && $aikuInvoiceCategory->slug=='aromatics-faire'){
                return;
            }


            if($auroraInvoiceCategory->slug=='aw-dropship' && $aikuInvoiceCategory->slug=='avasam'){
                return;
            }

            print "$invoice->id $invoice->source_invoice_category_id  $auroraInvoiceCategory->slug   ->>  $aikuInvoiceCategory->source_id $aikuInvoiceCategory->slug  \n";
        }



    }



    public string $commandSignature = 'test:aiku_invoice_category';

    public function asCommand(Command $command): void
    {

        $count = Invoice::whereNotNull('source_invoice_category_id')->count();

        $command->info("pending: $count");

        Invoice::whereNotNull('source_invoice_category_id')->orderBy('date', 'desc')
            ->chunk(1000, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $this->handle($invoice);
                }
            });
    }

}
