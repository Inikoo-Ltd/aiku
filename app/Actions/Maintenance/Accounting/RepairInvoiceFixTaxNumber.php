<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Sept 2025 11:56:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use Illuminate\Console\Command;

class RepairInvoiceFixTaxNumber
{
    use WithActionUpdate;

    protected function handle(Invoice $invoice): void
    {
        if (!$invoice->tax_number) {
            return;
        }


        $customerTaxNumber = $invoice->customer->taxNumber;
        //  dd($customerTaxNumber,$invoice->tax_number);
        if ($customerTaxNumber) {
            $formattedTaxNumber              = $invoice->customer->taxNumber->getFormattedTaxNumber();
            $formattedTaxNumber = preg_replace('/\s+/', '', $formattedTaxNumber);

            $formattedTaxNumberNoCountryCode = substr($formattedTaxNumber, 2);

            if ($formattedTaxNumber == $invoice->tax_number || $formattedTaxNumberNoCountryCode == $invoice->tax_number) {
                if ($invoice->customer->taxNumber->valid || !$this->taxNumberStartWithLetter($invoice->tax_number)) {
                    if ($formattedTaxNumber != $invoice->tax_number) {
                        print "fixing: $invoice->slug $invoice->tax_number ".$invoice->customer->taxNumber->getFormattedTaxNumber()."\n";
                    }

                    $invoice->updateQuietly([
                        'tax_number' => $invoice->customer->taxNumber->getFormattedTaxNumber(),
                    ]);
                }
            }
        }
    }

    private function taxNumberStartWithLetter($string): bool
    {
        // If first character of tax number is a letter, skip processing
        $tn = ltrim((string)$string);
        if ($tn !== '' && ctype_alpha(substr($tn, 0, 1))) {
            return true;
        }

        return false;
    }

    public string $commandSignature = 'repair:invoice_tax_number';

    public function asCommand(Command $command): void
    {
        
        $count = Invoice::count();

        $command->info("pending: $count");

        Invoice::orderBy('date', 'desc')
            ->chunk(1000, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $this->handle($invoice);
                }
            });
    }

}
