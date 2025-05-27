<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Apr 2025 15:41:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;


class RepairInvoiceMissingDelivery
{
    use WithActionUpdate;

    protected function handle(Invoice $invoice): void
    {

        if($invoice->source_id){
            $sourceData   = explode(':', $invoice->source_id);

            $org=match ($sourceData[0]){
                '1'=>'aw',
                '2'=>'sk',
                '3'=>'es',
                '4'=>'aroma',
                default=>'',
            };

            $signature='fetch:invoices  '.$org.' -s '.$sourceData[1];
            print "$signature\n";
            Artisan::call($signature);
        }

    }

    public string $commandSignature = 'repair:invoice_missing_delivery';

    public function asCommand(Command $command): void
    {

        $count = Invoice::withTrashed()->whereNull('delivery_address_id')->count();

        $command->info("pending: $count");

        Invoice::withTrashed()->whereNull('delivery_address_id')->orderBy('date')
            ->chunk(1000, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $this->handle($invoice);
                }
            });

    }

}
