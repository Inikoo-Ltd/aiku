<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Sept 2025 11:56:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Accounting\Invoice\UpdateInvoice;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use Illuminate\Console\Command;

class RepairInvoiceDateInBulk
{
    use WithActionUpdate;

    protected function handle(Invoice $invoice): void
    {
        print "Repairing invoice {$invoice->slug} date\n";
        UpdateInvoice::run(
            $invoice,
            [
                'date' => '2025-09-30 18:00:00',
                'tax_liability_at' => '2025-09-30 18:00:00',
            ]
        );
    }


    public string $commandSignature = 'repair:invoice_date_bulk';

    public function asCommand(Command $command): void
    {



        Invoice::whereIn('shop_id', [15, 30])
            ->where('date', '>=', '2025-10-01 00:00:00')
            ->whereNotNull('recurring_bill_id')
            ->orderBy('date', 'desc')
            ->chunk(1000, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $this->handle($invoice);
                }
            });
    }

}
