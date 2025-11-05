<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Sept 2025 11:56:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Helpers\SerialReference\GetSerialReference;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\SerialReference;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairInvoiceNumbers
{
    use WithActionUpdate;

    protected function handle(Invoice $invoice, Command $command): void
    {
        $reference = GetSerialReference::run(
            container: $invoice->shop,
            modelType: SerialReferenceModelEnum::INVOICE
        );

        $oldReference = $invoice->reference;

        $invoice->updateQuietly(
            [
                'reference' => $reference,
            ]
        );

        $invoice->generateSlug();
        $invoice->save();
        $invoice->refresh();

        $command->info("Invoice {$invoice->id}  {$invoice->date->format('c')}  ($invoice->slug)  reference updated from {$oldReference} to {$reference}");
    }


    public string $commandSignature = 'repair:invoice_numbers {shopID} {date} {sequenceNumber}';

    public function asCommand(Command $command): void
    {
        $shop = Shop::find($command->argument('shopID'));


        /** @var SerialReference $serialReference */
        $serialReference = $shop->serialReferences()->where('model', SerialReferenceModelEnum::INVOICE)->firstOrFail();


        $serial = $command->argument('sequenceNumber') - 1;

        DB::table('serial_references')->where('id', $serialReference->id)->update(['serial' => $serial]);


        Invoice::where('shop_id', $command->argument('shopID'))
            ->where('date', '>=', $command->argument('date').' 00:00:00')
            ->orderBy('date')
            ->chunk(1000, function ($invoices) use ($command) {
                foreach ($invoices as $invoice) {
                    $this->handle($invoice, $command);
                }
            });
    }

}
