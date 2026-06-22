<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Aug 2025 12:48:39 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Helpers\TaxNumber\CloneTaxNumberFromCustomer;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use Illuminate\Console\Command;

class RepairInvoiceMissingTaxNumber
{
    use WithActionUpdate;

    protected function handle(Invoice $invoice, Command $command): void
    {
        // Old Logic, just commented out
        // $customer = $invoice->customer;

        // if ($customer->taxNumber) {
        //     $invoice->update([
        //         'tax_number'        => $customer->taxNumber->number,
        //         'tax_number_status' => $customer->taxNumber->status,
        //         'tax_number_valid'  => $customer->taxNumber->valid
        //     ]);
        // } else {
        //     $invoice->update([
        //         'tax_number'        => null,
        //         'tax_number_status' => 'na',
        //         'tax_number_valid'  => false
        //     ]);
        // }

        // TODO Raul please check this repair
        $taxNumber         = null;
        $customerTaxNumber = $invoice->customer->taxNumber;
        // Just create separate entity, no need to run recalculate and stuffs.
        if ($customerTaxNumber && ($invoice->tax_number == $customerTaxNumber->getFormattedTaxNumber())) {
            $taxNumber = CloneTaxNumberFromCustomer::run(
                target: $invoice,
                originalTaxNumber: $customerTaxNumber,
                checkViaThirdParty: false
            );

            $command->info(sprintf(
                'Tax number cloned for invoice #%s',
                $invoice->id
            ));
        } else {
            $command->info(sprintf(
                'Invoice #%s has no customer tax number',
                $invoice->id
            ));
        }
    }

    public string $commandSignature = 'repair:invoice_missing_tax_number';

    public function asCommand(Command $command): void
    {
        // Old Logic commented out
        // $count = Invoice::where('shop_id', 30)
        //     ->count();

        // $command->info("pending: $count");

        // Invoice::where('shop_id', 30)
        //     ->orderBy('date')
        //     ->chunk(1000, function ($invoices) {
        //         foreach ($invoices as $invoice) {
        //             $this->handle($invoice);
        //         }
        //     });

        $query = Invoice::whereDoesntHave('taxNumber');
        $count = $query->clone()->count();

        $query
            ->orderBy('id')
            ->chunk(1000, function ($invoices) use ($command) {
                foreach ($invoices as $invoice) {
                    $this->handle($invoice, $command);
                }
            });
    }

}
