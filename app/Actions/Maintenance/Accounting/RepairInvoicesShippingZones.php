<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 16:30:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Accounting\Invoice\UpdateInvoice;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Billables\ShippingZone;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RepairInvoicesShippingZones
{
    use WithActionUpdate;
    use WithFixedAddressActions;

    public function handle(Invoice $invoice): void
    {

        /** @var InvoiceTransaction $shippingTransaction */
        $shippingTransaction = InvoiceTransaction::where('invoice_id', $invoice->id)
            ->where('model_type', 'ShippingZone')
            ->whereNotNull('model_id')
            ->first();

        if ($shippingTransaction) {
            /** @var ShippingZone $shippingZone */
            $shippingZone = $shippingTransaction->model;

            if ($shippingZone) {
                UpdateInvoice::make()->action(
                    invoice: $invoice,
                    modelData: [
                        'shipping_zone_schema_id' => $shippingZone->shipping_zone_schema_id,
                        'shipping_zone_id'        => $shippingZone->id,
                    ],
                    hydratorsDelay: 30
                );


            }
        }
    }


    public string $commandSignature = 'invoices:shipping_zones';

    public function asCommand(Command $command): void
    {

        $count = Invoice::count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Invoice::orderBy('date', 'desc')
            ->chunk(1000, function (Collection $models) use ($bar) {
                foreach ($models as $model) {
                    $this->handle($model);
                    $bar->advance();
                }
            });
    }

}
