<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Sept 2025 11:56:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use Illuminate\Console\Command;

class RepairRefundWithNoAddress
{
    use WithActionUpdate;

    protected function handle(Invoice $refund): void
    {

        $invoice=$refund->originalInvoice;

        if(!$invoice) {
            $invoice=Invoice::withTrashed()->find($refund->original_invoice_id);
        }

        if($invoice) {
            $refund->updateQuietly(
                [
                    'address_id'         => $invoice->address_id,
                    'billing_country_id' => $invoice->billing_country_id,
                ]
            );
        }


    }



    public string $commandSignature = 'repair:refund-missing-address';

    public function asCommand(Command $command): void
    {

        $count = Invoice::withTrashed()->where('type',InvoiceTypeEnum::REFUND)->whereNull('address_id')->count();

        $command->info("pending: $count");

        Invoice::withTrashed()->where('type',InvoiceTypeEnum::REFUND)->whereNull('address_id')->orderBy('date', 'desc')
            ->chunk(1000, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $this->handle($invoice);
                }
            });
    }

}
