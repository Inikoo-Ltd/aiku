<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Apr 2025 15:41:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\Invoice;
use Illuminate\Support\Str;

class RepairInvoiceMissingUuid
{
    use WithActionUpdate;

    protected function handle(Invoice $invoice): void
    {

        if (!$invoice->uuid) {
            $invoice->update(
                [
                    'uuid' => Str::uuid(),
                ]
            );
        }
    }

    public string $commandSignature = 'invoices:add_uuid';

    public function asCommand(): void
    {

        Invoice::withTrashed()->orderBy('date')
            ->chunk(1000, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $this->handle($invoice);
                }
            });

    }

}
