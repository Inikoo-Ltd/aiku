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

class RepairInvoiceMissingTaxNumber
{
    use WithActionUpdate;

    protected function handle(Invoice $invoice): void
    {
        $customer = $invoice->customer;

        if ($customer->taxNumber) {
            $invoice->update([
                'tax_number'        => $customer->taxNumber->number,
                'tax_number_status' => $customer->taxNumber->status,
                'tax_number_valid'  => $customer->taxNumber->valid
            ]);
        } else {
            $invoice->update([
                'tax_number'        => null,
                'tax_number_status' => 'na',
                'tax_number_valid'  => false
            ]);
        }
    }

    public string $commandSignature = 'repair:invoice_missing_tax_number';

    public function asCommand(Command $command): void
    {
        $count = Invoice::where('shop_id', 30)
            ->count();

        $command->info("pending: $count");

        Invoice::where('shop_id', 30)
            ->orderBy('date')
            ->chunk(1000, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $this->handle($invoice);
                }
            });
    }

}
