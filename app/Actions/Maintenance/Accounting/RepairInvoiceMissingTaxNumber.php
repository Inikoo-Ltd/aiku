<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2025 12:48:39 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RepairInvoiceMissingTaxNumber
{
    use WithActionUpdate;

    protected function handle(Invoice $invoice): void
    {
        $customer = $invoice->customer;

        if($customer->taxNumber){
            $invoice->update([
                'tax_number' => $customer->taxNumber->number
            ]);
        }

    }

    public string $commandSignature = 'repair:invoice_missing_delivery';

    public function asCommand(Command $command): void
    {
        $count = Invoice::whereNull('source_id')
            ->whereDate('date', '>', '2025-06-01')
            ->count();

        $command->info("pending: $count");

        Invoice::whereNull('delivery_address_id')
            ->whereDate('date', '>', '2025-06-01')
            ->orderBy('date')
            ->chunk(1000, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $this->handle($invoice);
                }
            });
    }

}
